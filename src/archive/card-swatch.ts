/**
 * A swatch on an archive's card, as the server and the browser both draw
 * it: a button carrying the link and the picture of the design in its value.
 */

export const CARD_SWATCH = 'button.pixonaut-shop-card-swatch';
export const SWATCHES_CLASS = 'pixonaut-shop-card-swatches';
export const SWATCH_CLASS = 'pixonaut-shop-card-swatch';
/** The link the card opens in the swatch's value. */
export const LINK_ATTRIBUTE = 'data-pixonaut-shop-link';
/** The picture of the design in the swatch's value, as JSON. */
export const PICTURE_ATTRIBUTE = 'data-pixonaut-shop-picture';

export interface SwatchPicture {
	src: string;
	srcset: string;
	width: number;
	height: number;
}

export interface Swatch {
	label: string;
	url: string;
	picture: SwatchPicture;
	current: boolean;
}

export function swatchOf( button: Element ): Swatch | null {
	try {
		const picture = JSON.parse( button.getAttribute( PICTURE_ATTRIBUTE ) ?? '' ) as SwatchPicture;
		const url = button.getAttribute( LINK_ATTRIBUTE );

		return url && typeof picture?.src === 'string'
			? {
					label: button.textContent ?? '',
					url,
					picture: { ...picture, srcset: picture.srcset ?? '' },
					current: button.getAttribute( 'aria-pressed' ) === 'true',
				}
			: null;
	} catch {
		return null;
	}
}

/** The swatches of a card, as `CardSwatches.php` prints them. */
export function drawSwatches( swatches: Swatch[], groupLabel: string ): HTMLElement {
	const group = document.createElement( 'div' );
	group.className = SWATCHES_CLASS;
	group.setAttribute( 'role', 'group' );
	group.setAttribute( 'aria-label', groupLabel );

	for ( const swatch of swatches ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = SWATCH_CLASS;
		button.setAttribute( 'aria-pressed', String( swatch.current ) );
		button.setAttribute( LINK_ATTRIBUTE, swatch.url );
		button.setAttribute( PICTURE_ATTRIBUTE, JSON.stringify( swatch.picture ) );
		button.textContent = swatch.label;
		group.append( button );
	}

	return group;
}
