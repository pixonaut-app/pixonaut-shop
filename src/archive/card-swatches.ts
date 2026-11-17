/**
 * The swatches on an archive's card of a design shown in several of the
 * chosen values (`inc/Archive/CardSwatches.php` on the server,
 * `src/archive/client/cards.ts` in the browser): choosing one shows the
 * design in that value, its picture, the link the card opens and its name
 * where it has one of its own, and, on a
 * card drawn in the browser, its price, in place. Such a card shows no
 * second picture on hover, which would cover the one chosen (`_archive.css`).
 */

import { CARD_SWATCH, swatchOf } from './card-swatch.ts';

const CARD = 'li.wc-block-product';
const FIRST_PICTURE = '.wp-block-woocommerce-product-image img:not(.pixonaut-shop-second-picture)';
const LINKS = '.wp-block-woocommerce-product-image a, .wp-block-post-title a';
const PRICE = '.wc-block-components-product-price';
const TITLE = '.wp-block-post-title a';

function choose( button: HTMLButtonElement ): void {
	const card = button.closest( CARD );
	const swatch = swatchOf( button );

	if ( ! card || ! swatch ) {
		return;
	}

	for ( const other of card.querySelectorAll( CARD_SWATCH ) ) {
		other.setAttribute( 'aria-pressed', String( other === button ) );
	}

	for ( const link of card.querySelectorAll< HTMLAnchorElement >( LINKS ) ) {
		link.href = swatch.url;
	}

	const picture = card.querySelector< HTMLImageElement >( FIRST_PICTURE );

	if ( picture ) {
		picture.src = swatch.picture.src;
		picture.width = swatch.picture.width;
		picture.alt = swatch.picture.alt;
		picture.height = swatch.picture.height;

		if ( swatch.picture.srcset === '' ) {
			picture.removeAttribute( 'srcset' );
		} else {
			picture.srcset = swatch.picture.srcset;
		}
	}

	const title = card.querySelector( TITLE );

	if ( title && swatch.title !== undefined ) {
		title.textContent = swatch.title;
	}

	const price = card.querySelector( PRICE );

	if ( price && swatch.price !== undefined ) {
		const markup = document.createElement( 'template' );
		markup.innerHTML = swatch.price;
		price.replaceChildren( markup.content );
	}
}

document.addEventListener( 'click', ( event ) => {
	const button = event.target instanceof Element ? event.target.closest( CARD_SWATCH ) : null;

	if ( button instanceof HTMLButtonElement ) {
		choose( button );
	}
} );
