/**
 * The grid of the archive filtered in the browser: no pages, but a page's
 * worth of cards more each time the shopper nears its end, with a row of
 * skeleton cards where the next ones come, so nothing moves as they do.
 * After a few loads on its own it asks first, with "Show more" and how far
 * the shopper has come, so the footer can be reached; at the end it says
 * that every product has been seen. A new list of cards comes in at once,
 * once the pictures of the cards new to it are loaded.
 */

import { CARD } from './cards.ts';

/** How far below the window, in pixels, the grid loads its next cards. */
const AHEAD_PX = 800;
const AHEAD = `0px 0px ${ AHEAD_PX }px 0px`;
const PERCENT = 100;
const FEED_CLASS = 'pixonaut-shop-archive-feed';
const SKELETON_CLASS = 'pixonaut-shop-skeleton-card';
const BUTTON_CLASS = 'wp-element-button';
const OUTLINE_CLASS = 'is-style-outline';

export interface FeedWords {
	loading: string;
	seen: string;
	seenAll: { one: string; other: string };
	showMore: string;
	toTop: string;
}

export interface FeedOptions {
	grid: HTMLElement;
	pageSize: number;
	autoLoads: number;
	words: FeedWords;
	/** Draws the card at a place in the shown list. */
	draw: ( place: number ) => HTMLElement;
	/** Resolves once the cards' pictures are loaded (or have failed, with their fallbacks). */
	ready: ( cards: HTMLElement[] ) => Promise< void >;
	/** Readies cards for the choice they come in for, as they come in. */
	show: ( cards: HTMLElement[] ) => void;
	/** Says how many cards are drawn now, for the address's history. */
	onDrawn: ( drawn: number ) => void;
}

function number( n: number ): string {
	return n.toLocaleString( document.documentElement.lang || undefined );
}

export class Feed {
	private total = 0;
	private drawn = 0;
	private autoLoaded = 0;
	/** Counts the lists drawn, so a list whose pictures came in late never covers a newer one. */
	private generation = 0;
	/** Whether new cards wait for their pictures. */
	private pending = false;
	private readonly footer: HTMLElement;
	private readonly skeletons: HTMLElement;
	private readonly status: HTMLElement;
	private readonly more: HTMLElement;
	private readonly progress: HTMLElement;
	private readonly bar: HTMLElement;
	private readonly end: HTMLElement;
	private readonly endText: HTMLElement;
	private readonly observer: IntersectionObserver;

	private readonly options: FeedOptions;

	constructor( options: FeedOptions ) {
		this.options = options;
		const { grid, words } = options;
		this.footer = document.createElement( 'div' );
		this.footer.className = FEED_CLASS;

		// A row of the grid's own cards, drawn as skeletons.
		this.skeletons = grid.cloneNode( false ) as HTMLElement;
		this.skeletons.classList.add( `${ FEED_CLASS }__skeletons` );
		this.skeletons.setAttribute( 'aria-hidden', 'true' );
		this.skeletons.inert = true;

		this.status = document.createElement( 'p' );
		this.status.className = 'screen-reader-text';
		this.status.setAttribute( 'role', 'status' );

		this.more = document.createElement( 'div' );
		this.more.className = `${ FEED_CLASS }__more`;
		this.progress = document.createElement( 'p' );
		this.progress.className = `${ FEED_CLASS }__progress`;
		const track = document.createElement( 'div' );
		track.className = `${ FEED_CLASS }__bar`;
		track.setAttribute( 'aria-hidden', 'true' );
		this.bar = document.createElement( 'div' );
		track.append( this.bar );
		const wrap = document.createElement( 'div' );
		wrap.className = `wp-block-button ${ OUTLINE_CLASS }`;
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = `wp-block-button__link ${ BUTTON_CLASS }`;
		button.textContent = words.showMore;
		button.addEventListener( 'click', () => this.load( false ) );
		wrap.append( button );
		this.more.append( this.progress, track, wrap );

		this.end = document.createElement( 'div' );
		this.end.className = `${ FEED_CLASS }__end`;
		this.endText = document.createElement( 'p' );
		const top = document.createElement( 'button' );
		top.type = 'button';
		top.className = `${ FEED_CLASS }__top`;
		top.textContent = words.toTop;
		top.addEventListener( 'click', () => {
			const results = grid.closest( '.pixonaut-shop-archive-results' ) ?? grid;
			results.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );
		this.end.append( this.endText, top );

		this.footer.append( this.skeletons, this.status, this.more, this.end );
		grid.after( this.footer );

		this.observer = new IntersectionObserver(
			( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					this.load( true );
				}
			},
			{ rootMargin: AHEAD },
		);
		this.observer.observe( this.skeletons );
	}

	/** Takes over the cards the server drew for the first page. */
	adopt( total: number, drawn: number ): void {
		this.total = total;
		this.drawn = Math.min( drawn, total );
		this.autoLoaded = 0;
		this.update();
	}

	/**
	 * Draws a new list's first cards: a page's worth, or as many as `keep`.
	 * They replace the grid's in one go once their pictures are in; resolves
	 * then, or never for a list a newer one replaced first.
	 */
	reset( total: number, keep: number ): Promise< void > {
		this.total = total;
		this.drawn = Math.min( Math.max( keep, this.options.pageSize ), total );
		this.autoLoaded = 0;
		const cards = Array.from( { length: this.drawn }, ( _, place ) => this.options.draw( place ) );
		this.generation += 1;
		const generation = this.generation;
		this.pending = true;
		this.options.grid.setAttribute( 'aria-busy', 'true' );

		return this.options.ready( cards ).then( () => {
			if ( generation !== this.generation ) {
				return new Promise< void >( () => undefined );
			}

			this.pending = false;
			this.options.grid.removeAttribute( 'aria-busy' );
			this.options.show( cards );
			this.options.grid.replaceChildren( ...cards );
			this.update();
			window.requestAnimationFrame( () => this.loadWhileInReach() );
		} );
	}

	get drawnCount(): number {
		return this.drawn;
	}

	/** Draws the next page's cards; they come in once their pictures are loaded. */
	private load( auto: boolean ): void {
		if (
			this.pending ||
			this.drawn >= this.total ||
			( auto && this.autoLoaded >= this.options.autoLoads )
		) {
			return;
		}

		const next = Math.min( this.drawn + this.options.pageSize, this.total );
		const cards = Array.from( { length: next - this.drawn }, ( _, i ) =>
			this.options.draw( this.drawn + i ),
		);
		const generation = this.generation;
		this.pending = true;
		this.options.grid.setAttribute( 'aria-busy', 'true' );

		void this.options.ready( cards ).then( () => {
			// A new list replaced the grid meanwhile.
			if ( generation !== this.generation ) {
				return;
			}

			this.pending = false;
			this.options.grid.removeAttribute( 'aria-busy' );
			this.options.show( cards );
			this.options.grid.append( ...cards );
			this.drawn = next;
			this.autoLoaded = auto ? this.autoLoaded + 1 : 0;

			if ( ! auto ) {
				// The first new card takes the focus the button had.
				cards[ 0 ]?.querySelector< HTMLElement >( 'a' )?.focus( { preventScroll: true } );
			}

			this.update();
			this.options.onDrawn( this.drawn );

			// The observer says so only when the skeletons come into reach, not while they stay in it.
			if ( auto ) {
				window.requestAnimationFrame( () => this.loadWhileInReach() );
			}
		} );
	}

	private loadWhileInReach(): void {
		if (
			! this.skeletons.hidden &&
			this.skeletons.getBoundingClientRect().top < window.innerHeight + AHEAD_PX
		) {
			this.load( true );
		}
	}

	private update(): void {
		const left = this.total - this.drawn;
		const onItsOwn = left > 0 && this.autoLoaded < this.options.autoLoads;
		const { words, pageSize } = this.options;

		this.skeletons.hidden = ! onItsOwn;
		this.more.hidden = left === 0 || onItsOwn;
		this.end.hidden = left > 0 || this.total === 0;
		this.status.textContent = onItsOwn ? words.loading : '';

		if ( onItsOwn ) {
			const model = this.options.grid.querySelector( CARD );
			const row = Math.min( left, this.columns(), pageSize );

			if ( model && this.skeletons.children.length !== row ) {
				this.skeletons.replaceChildren(
					...Array.from( { length: row }, () => {
						const skeleton = model.cloneNode( true ) as HTMLElement;
						skeleton.classList.add( SKELETON_CLASS );

						return skeleton;
					} ),
				);
			}
		}

		this.progress.textContent = words.seen
			.replace( '%1$s', number( this.drawn ) )
			.replace( '%2$s', number( this.total ) );
		this.bar.style.width = `${ this.total === 0 ? 0 : ( this.drawn / this.total ) * PERCENT }%`;
		this.endText.textContent = (
			this.total === 1 ? words.seenAll.one : words.seenAll.other
		).replace( '%s', number( this.total ) );
	}

	/** How many cards a row of the grid holds, as laid out now. */
	private columns(): number {
		const tops = Array.from( this.options.grid.querySelectorAll( CARD ) )
			.slice( 0, this.options.pageSize )
			.map( ( card ) => Math.round( card.getBoundingClientRect().top ) );

		return Math.max( 1, tops.filter( ( top ) => top === tops[ 0 ] ).length );
	}
}
