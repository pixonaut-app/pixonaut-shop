/**
 * A product card's second picture (`inc/Product/SecondPicture.php`), which
 * the card shows over its first while it is hovered.
 *
 * A picture that fails to load is taken out of its card, so the browser's
 * mark for a broken picture never shows over the first one. Pictures load
 * lazily and a product collection swaps its cards when it changes page, so
 * the failures are caught on the document as they happen, whichever card
 * they come from.
 */

const SECOND_PICTURE = 'img.pixonaut-shop-second-picture';

/** Whether the picture has finished loading without a picture to show. */
function hasFailed( picture: HTMLImageElement ): boolean {
	return picture.complete && picture.naturalWidth === 0;
}

// A picture's `error` does not bubble: it is caught on its way down.
document.addEventListener(
	'error',
	( event ) => {
		if ( event.target instanceof HTMLImageElement && event.target.matches( SECOND_PICTURE ) ) {
			event.target.remove();
		}
	},
	true,
);

// The ones that failed before this script ran.
for ( const picture of document.querySelectorAll< HTMLImageElement >( SECOND_PICTURE ) ) {
	if ( hasFailed( picture ) ) {
		picture.remove();
	}
}
