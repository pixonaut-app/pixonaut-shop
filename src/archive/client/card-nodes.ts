/**
 * The cards of the archive filtered in the browser, one element for each
 * card and picture it shows. A card never changes its picture: a choice
 * shows, hides and orders the elements, and a card shown for the first time
 * comes in only once its picture is loaded, so it never shows blank, broken
 * or another card's picture. A card whose filtered picture cannot be loaded
 * shows the card's own instead. A card kept from before is told what the
 * new choice changes on it (link, price, swatches) only as it is shown, so
 * the list still in view never shows what the next one will. A swatch shows
 * the card of its design in that value, with its own element, in the place
 * of the one it is on.
 */

import { CARD_SWATCH, KEPT_ATTRIBUTE } from '../card-swatch.ts';
import { pictureOf, type Shown, swatchLabel } from '../index/engine.ts';
import { CARD, type CardLook, drawCard, FIRST_PICTURE, refreshCard, setPicture } from './cards.ts';

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
	/** What each kept element is to show once it is shown again. */
	private readonly planned = new WeakMap< HTMLElement, Shown >();
	/** Counts the lists shown, so a swatch chosen before one never acts on it. */
	private round = 0;
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
		const image = node.querySelector< HTMLImageElement >( FIRST_PICTURE );
		const design = entry.swatches.length > 1 ? entry.swatches : [];
		const labels = design.map( ( member ) =>
			swatchLabel(
				index,
				member.card,
				design.map( ( other ) => other.card ),
			),
		);
		const printed = Array.from( node.querySelectorAll( CARD_SWATCH ), ( button ) =>
			button.textContent?.trim(),
		);

		// A swatch is told apart by its place among the card's, so the server's must be the index's, in its order.
		if (
			! image ||
			image.getAttribute( 'src' ) !== pictureOf( index, entry, choice ).src ||
			printed.join( '\n' ) !== labels.join( '\n' )
		) {
			return;
		}

		image.addEventListener( 'error', () => this.fallBack( node, image ), { once: true } );
		this.keep( node, entry );
	}

	/** An entry's element: the one kept for its card and picture, or a new one. */
	nodeOf( entry: Shown ): HTMLElement {
		const look = this.look();
		const kept = this.nodes.get( this.keyOf( entry ) );

		if ( kept ) {
			this.planned.set( kept, entry );

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

	/**
	 * Resolves once every element's picture is loaded, or has failed along
	 * with the card's own; the grid keeps what it shows until then.
	 */
	async whenLoaded( nodes: HTMLElement[] ): Promise< void > {
		await Promise.all(
			nodes.filter( ( node ) => ! this.loaded.has( node ) ).map( ( node ) => this.load( node ) ),
		);
	}

	/** Brings kept elements up to the choice they are shown for, as they are shown. */
	show( nodes: HTMLElement[] ): void {
		this.round += 1;
		const look = this.look();

		for ( const node of nodes ) {
			const entry = this.planned.get( node );

			if ( entry ) {
				this.planned.delete( node );
				refreshCard( node, entry, look );
				this.entries.set( node, entry );
			}
		}
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

		const round = this.round;
		const sibling = this.nodeOf( { ...member, swatches: entry.swatches } );
		await this.whenLoaded( [ sibling ] );

		// A list shown meanwhile drew the grid again.
		if ( round !== this.round || ! node.isConnected ) {
			return;
		}

		this.show( [ sibling ] );
		node.replaceWith( sibling );
		sibling.querySelectorAll< HTMLElement >( CARD_SWATCH )[ place ]?.focus();
	}
}
