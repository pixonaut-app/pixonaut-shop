/**
 * The cards of the archive filtered in the browser, one element for each
 * card and picture it shows. A card never changes its picture: a choice
 * shows, hides and orders the elements, and a card shown for the first time
 * comes in only once its picture is loaded, so it never shows blank, broken
 * or another card's picture. A card whose filtered picture cannot be loaded
 * shows the card's own instead. A swatch shows the card of its design in
 * that value, with its own element, in the place of the one it is on.
 */

import { CARD_SWATCH, KEPT_ATTRIBUTE } from '../card-swatch.ts';
import { pictureOf, type Shown } from '../index/engine.ts';
import { CARD, type CardLook, drawCard, FIRST_PICTURE, refreshCard, setPicture } from './cards.ts';

/** How long, in milliseconds, new cards wait for their pictures before they come in anyway. */
const PICTURE_WAIT_MS = 3000;

/**
 * Whether a card's picture loads, loaded now, though the card is not in the
 * page yet, so it is there from the frame the card comes in.
 */
function loads( image: HTMLImageElement ): Promise< boolean > {
	image.loading = 'eager';

	return image.decode().then(
		() => true,
		() => false,
	);
}

export class CardNodes {
	/** Each card's element, by the card and its picture. */
	private readonly nodes = new Map< string, HTMLElement >();
	/** What each element shows now. */
	private readonly entries = new WeakMap< HTMLElement, Shown >();
	/** The elements whose pictures are loaded, or given up on. */
	private readonly loaded = new WeakSet< HTMLElement >();
	/** How wide a card's picture is shown, measured where one is. */
	private sizes = '';

	private readonly model: HTMLElement;
	private readonly grid: HTMLElement;
	private readonly look: () => CardLook;

	constructor( model: HTMLElement, grid: HTMLElement, look: () => CardLook ) {
		this.model = model;
		this.grid = grid;
		this.look = look;
	}

	private keyOf( entry: Shown ): string {
		const { index, choice } = this.look();

		return `${ entry.card.id } ${ pictureOf( index, entry, choice ).src }`;
	}

	/** Keeps a card the server drew as an entry's element, when it shows the entry's picture and swatches. */
	adopt( node: HTMLElement, entry: Shown ): void {
		const { index, choice } = this.look();
		const image = node.querySelector( FIRST_PICTURE );

		if (
			image?.getAttribute( 'src' ) !== pictureOf( index, entry, choice ).src ||
			node.querySelectorAll( CARD_SWATCH ).length !==
				( entry.swatches.length > 1 ? entry.swatches.length : 0 )
		) {
			return;
		}

		this.keep( node, entry );
		this.loaded.add( node );
	}

	/** An entry's element: the one kept for its card and picture, or a new one. */
	nodeOf( entry: Shown ): HTMLElement {
		const look = this.look();
		const kept = this.nodes.get( this.keyOf( entry ) );

		if ( kept ) {
			refreshCard( kept, entry, look );
			this.entries.set( kept, entry );

			return kept;
		}

		const node = drawCard( this.model, entry, look, this.measure() );
		const image = node.querySelector< HTMLImageElement >( FIRST_PICTURE );

		// A picture that fails once the card is in (one loaded as it nears the window): the card's own, once.
		image?.addEventListener( 'error', () => this.fallBack( node, image ), { once: true } );
		this.keep( node, entry );

		return node;
	}

	private keep( node: HTMLElement, entry: Shown ): void {
		node.setAttribute( KEPT_ATTRIBUTE, '' );
		this.nodes.set( this.keyOf( entry ), node );
		this.entries.set( node, entry );
	}

	/** Shows the card's own picture in place of one that cannot be loaded; false when it already did. */
	private fallBack( node: HTMLElement, image: HTMLImageElement ): boolean {
		const own = this.entries.get( node )?.card.image;

		if ( ! own || image.getAttribute( 'src' ) === own.src ) {
			return false;
		}

		setPicture( image, own, own.alt );

		return true;
	}

	/** The width a card's picture is shown at, as `sizes`; the last one measured while no card is in view. */
	private measure(): string {
		const width = this.grid
			.querySelector( `${ CARD } ${ FIRST_PICTURE }` )
			?.getBoundingClientRect().width;

		if ( width ) {
			this.sizes = `${ Math.ceil( width ) }px`;
		}

		return this.sizes;
	}

	/** Resolves once every new element's picture is loaded, or after a while. */
	whenLoaded( nodes: HTMLElement[] ): Promise< void > {
		const waiting = nodes.filter( ( node ) => ! this.loaded.has( node ) );

		if ( waiting.length === 0 ) {
			return Promise.resolve();
		}

		return Promise.race( [
			Promise.all( waiting.map( ( node ) => this.load( node ) ) ).then( () => undefined ),
			new Promise< void >( ( resolve ) => window.setTimeout( resolve, PICTURE_WAIT_MS ) ),
		] );
	}

	private async load( node: HTMLElement ): Promise< void > {
		const image = node.querySelector< HTMLImageElement >( FIRST_PICTURE );

		if ( image && ! ( await loads( image ) ) && this.fallBack( node, image ) ) {
			await loads( image );
		}

		this.loaded.add( node );
	}

	/** Shows, in a card's place, the card of its design a swatch on it names. */
	async choose( button: HTMLButtonElement ): Promise< void > {
		const node = button.closest< HTMLElement >( CARD );
		const entry = node ? this.entries.get( node ) : undefined;

		if ( ! node || ! entry ) {
			return;
		}

		const place = Array.from( node.querySelectorAll( CARD_SWATCH ) ).indexOf( button );
		const member = entry.swatches[ place ];

		if ( ! member || member.card.id === entry.card.id ) {
			return;
		}

		const sibling = this.nodeOf( { ...member, swatches: entry.swatches } );
		await this.whenLoaded( [ sibling ] );

		// A choice made meanwhile drew the grid again.
		if ( ! node.isConnected || this.entries.get( node ) !== entry ) {
			return;
		}

		node.replaceWith( sibling );
		sibling.querySelectorAll< HTMLElement >( CARD_SWATCH )[ place ]?.focus();
	}
}
