/**
 * The cards of the archive filtered in the browser, drawn from one the
 * server rendered: a copy of it keeps every block and setting a store gave
 * the grid's cards in the Site Editor, with the name, link, pictures,
 * summary, sale badge and price of another card in it.
 */

import { drawSwatches, SWATCHES_CLASS } from '../card-swatch.ts';
import { type Choice, linkOf, pictureOf, type Shown, swatchLabel } from '../index/engine.ts';
import { type PriceTemplates, priceMarkup } from '../index/price-markup.ts';
import type { ArchiveIndex, Picture } from '../index/types.ts';

export const CARD = 'li.wc-block-product';
const IMAGE_BLOCK = '.wp-block-woocommerce-product-image';
const IMAGE_LINK = `${ IMAGE_BLOCK } a`;
const FIRST_PICTURE = '.wp-block-woocommerce-product-image img:not(.pixonaut-shop-second-picture)';
const SECOND_PICTURE = 'img.pixonaut-shop-second-picture';
const SECOND_PICTURE_CLASS = 'pixonaut-shop-second-picture';
const PICTURE_INNER = '.wc-block-components-product-image__inner-container';
const SALE_BADGE = '.wp-block-woocommerce-product-sale-badge';
const TITLE_LINK = '.wp-block-post-title a';
const SUMMARY = '.wc-block-components-product-summary';
const PRICE = '.wc-block-components-product-price';
/** WooCommerce's classes for a product's id and for a product on sale, on the card. */
const POST_CLASS = /^post-\d+$/;
const SALE_CLASS = 'sale';
const PLACEHOLDER_CLASS = 'woocommerce-placeholder';
/** The Interactivity API's attributes, which a copy made after the page started does nothing with. */
const DIRECTIVE = /^data-wp-/;

export interface CardLook {
	index: ArchiveIndex;
	prices: PriceTemplates;
	saleBadge: string;
	choice: Choice;
	/** The name of a card's swatches, `%s` for the design. */
	swatchGroup: string;
}

function withoutDirectives( element: Element ): void {
	for ( const node of [ element, ...element.querySelectorAll( '*' ) ] ) {
		for ( const name of node.getAttributeNames() ) {
			if ( DIRECTIVE.test( name ) ) {
				node.removeAttribute( name );
			}
		}
	}
}

/** A card the server rendered, as the model for the others: its own values are replaced on each copy. */
export function modelOf( card: Element ): HTMLElement {
	const model = card.cloneNode( true ) as HTMLElement;
	withoutDirectives( model );
	model.querySelector( SECOND_PICTURE )?.remove();
	model.querySelector( SALE_BADGE )?.remove();
	model.querySelector( `.${ SWATCHES_CLASS }` )?.remove();

	return model;
}

function setPicture( image: HTMLImageElement, picture: Picture, alt: string ): void {
	image.src = picture.src;
	image.width = picture.width;
	image.height = picture.height;
	image.alt = alt;
	image.classList.remove( PLACEHOLDER_CLASS );
	image.removeAttribute( 'data-image-id' );

	if ( picture.srcset === '' ) {
		image.removeAttribute( 'srcset' );
		image.removeAttribute( 'sizes' );
	} else {
		image.srcset = picture.srcset;
	}
}

function fromMarkup( markup: string ): DocumentFragment {
	const template = document.createElement( 'template' );
	template.innerHTML = markup;

	return template.content;
}

/** A card for one shown entry, drawn on a copy of the model. */
export function drawCard( model: HTMLElement, entry: Shown, look: CardLook ): HTMLElement {
	const card = model.cloneNode( true ) as HTMLElement;
	const { index, choice } = look;
	const link = linkOf( entry, window.location.href, choice );
	const picture = pictureOf( index, entry, choice );
	const onSale = entry.card.variations.some(
		( variation ) => variation.price < variation.regularPrice,
	);

	for ( const name of Array.from( card.classList ) ) {
		if ( POST_CLASS.test( name ) ) {
			card.classList.remove( name );
		}
	}

	card.classList.add( `post-${ entry.card.productId }` );
	card.classList.toggle( SALE_CLASS, onSale );

	for ( const anchor of card.querySelectorAll< HTMLAnchorElement >(
		`${ IMAGE_LINK }, ${ TITLE_LINK }`,
	) ) {
		anchor.href = link;
	}

	const title = card.querySelector( TITLE_LINK );

	if ( title ) {
		title.textContent = entry.card.title;
	}

	const first = card.querySelector< HTMLImageElement >( FIRST_PICTURE );

	if ( first ) {
		setPicture( first, picture, picture.alt );
		first.loading = 'lazy';

		if ( entry.card.secondImage ) {
			const second = first.cloneNode() as HTMLImageElement;
			second.className = SECOND_PICTURE_CLASS;
			second.removeAttribute( 'style' );
			setPicture( second, entry.card.secondImage, '' );
			first.after( second );
		}
	}

	if ( onSale && look.saleBadge !== '' ) {
		card.querySelector( PICTURE_INNER )?.append( fromMarkup( look.saleBadge ) );
	}

	const summary = card.querySelector( SUMMARY );

	if ( summary ) {
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = entry.card.summary;
		summary.replaceChildren( ...( entry.card.summary === '' ? [] : [ paragraph ] ) );
	}

	// A design shown in several of the chosen values: a swatch for each, this one pressed.
	if ( entry.swatches.length > 1 ) {
		const swatches = entry.swatches.map( ( member ) => {
			const memberPicture = pictureOf( index, member, choice );

			return {
				label: swatchLabel( index, member.card ),
				url: linkOf( member, window.location.href, choice ),
				picture: {
					src: memberPicture.src,
					srcset: memberPicture.srcset,
					width: memberPicture.width,
					height: memberPicture.height,
				},
				current: member === entry,
			};
		} );

		card
			.querySelector( IMAGE_BLOCK )
			?.after( drawSwatches( swatches, look.swatchGroup.replace( '%s', entry.card.title ) ) );
	}

	const price = card.querySelector( PRICE );

	if ( price ) {
		price.replaceChildren(
			fromMarkup( priceMarkup( entry.variations, look.prices, index.currency ) ),
		);
	}

	return card;
}
