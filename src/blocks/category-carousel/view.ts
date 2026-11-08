/**
 * The category carousel's arrows (`inc/Blocks/CategoryCarousel.php`). The row scrolls by
 * itself, by touch, trackpad, wheel or Tab, and snaps to a category; the server prints both
 * buttons hidden so that, without this script, none does nothing. Here each shows while the
 * row can scroll its way, and a press scrolls it by most of its width, at once where the
 * visitor asked for reduced motion.
 */

const ROOT = '.wp-block-pixonaut-shop-category-carousel';
const LIST = '.wp-block-pixonaut-shop-category-carousel__list';
const PREVIOUS = '.wp-block-pixonaut-shop-category-carousel__arrow.is-previous';
const NEXT = '.wp-block-pixonaut-shop-category-carousel__arrow.is-next';

/**
 * How much of the row's width one press scrolls: less than all of it, so the category cut
 * off at the edge shows whole and a visitor keeps their place.
 */
const PAGE_SHARE = 0.8;

/** Pixels of rounding a browser leaves in a scroll offset at either end. */
const EDGE_SLACK = 1;

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

const RIGHT_TO_LEFT = 'rtl';

/** Tells the stylesheet the arrows work, so the row's own scrollbar can go. */
const ENHANCED_CLASS = 'is-enhanced';

function setUp( root: HTMLElement ): void {
	const list = root.querySelector< HTMLElement >( LIST );
	const previous = root.querySelector< HTMLButtonElement >( PREVIOUS );
	const next = root.querySelector< HTMLButtonElement >( NEXT );

	if ( ! list || ! previous || ! next ) {
		return;
	}

	/** Which way along the row's scroll offset "next" is: negative in a right-to-left page. */
	const forward = (): number => ( getComputedStyle( list ).direction === RIGHT_TO_LEFT ? -1 : 1 );

	const update = (): void => {
		// A right-to-left row's offset runs from 0 down to minus its overflow.
		const travelled = Math.abs( list.scrollLeft );
		const overflow = list.scrollWidth - list.clientWidth;
		const hadFocus = document.activeElement;

		previous.hidden = overflow <= EDGE_SLACK || travelled <= EDGE_SLACK;
		next.hidden = overflow <= EDGE_SLACK || travelled >= overflow - EDGE_SLACK;

		// A press that reached the end hid the button it was on: keep focus on the other.
		if ( hadFocus === next && next.hidden && ! previous.hidden ) {
			previous.focus();
		} else if ( hadFocus === previous && previous.hidden && ! next.hidden ) {
			next.focus();
		}
	};

	const scroll = ( direction: number ): void => {
		list.scrollBy( {
			left: direction * forward() * list.clientWidth * PAGE_SHARE,
			behavior: window.matchMedia( REDUCED_MOTION ).matches ? 'auto' : 'smooth',
		} );
	};

	previous.addEventListener( 'click', () => scroll( -1 ) );
	next.addEventListener( 'click', () => scroll( 1 ) );

	let frame = 0;
	list.addEventListener(
		'scroll',
		() => {
			cancelAnimationFrame( frame );
			frame = requestAnimationFrame( update );
		},
		{ passive: true },
	);
	new ResizeObserver( update ).observe( list );
	root.classList.add( ENHANCED_CLASS );
	update();
}

function setUpAll(): void {
	for ( const root of document.querySelectorAll< HTMLElement >( ROOT ) ) {
		setUp( root );
	}
}

// A block's view script may load in the page's head, before the block.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', setUpAll );
} else {
	setUpAll();
}
