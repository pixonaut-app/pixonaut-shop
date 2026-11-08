/**
 * The category carousel's arrows (`inc/Blocks/CategoryCarousel.php`). The row scrolls by
 * itself, by touch, trackpad, wheel or Tab, and snaps to a category; the server prints both
 * buttons hidden so that, without this script, none does nothing. Here each shows while the
 * row can scroll its way, and a press scrolls it by most of its width, at once where the
 * visitor asked for reduced motion.
 */

const ROOT = '.wp-block-pixonaut-shop-category-carousel';
const LIST = '.wp-block-pixonaut-shop-category-carousel__list';
/** The row's last category's link. */
const LINK = '.wp-block-pixonaut-shop-category-carousel__item:last-child a';
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

/** Smooth scrolling, or none where the visitor asked for reduced motion. */
function motion(): ScrollBehavior {
	return window.matchMedia( REDUCED_MOTION ).matches ? 'auto' : 'smooth';
}

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

		// A press that reached the end hid the button it was on: keep focus on the other. A
		// wider row hides both: focus goes to the row's last category, not the page's start.
		const hidden =
			( hadFocus === next && next.hidden ) || ( hadFocus === previous && previous.hidden );
		if ( hidden ) {
			const other = hadFocus === next ? previous : next;
			if ( ! other.hidden ) {
				other.focus();
			} else {
				list.querySelector< HTMLElement >( LINK )?.focus();
			}
		}
	};

	const scroll = ( direction: number ): void => {
		list.scrollBy( {
			left: direction * forward() * list.clientWidth * PAGE_SHARE,
			behavior: motion(),
		} );
	};

	// The browser scrolls a focused category into view only when it is out of the row, so one
	// already in view could sit under an arrow. Here it is scrolled clear of the row's scroll
	// padding, the arrows' room; `scrollIntoView()` cannot, since on a snapping row it goes to
	// the category's snap position, which may be under an arrow again.
	list.addEventListener( 'focusin', ( event ) => {
		const item = ( event.target as HTMLElement ).closest( 'li' );
		if ( ! item ) {
			return;
		}

		const row = list.getBoundingClientRect();
		const style = getComputedStyle( list );
		const left = row.left + list.clientLeft + Number.parseFloat( style.scrollPaddingLeft );
		const right =
			row.left + list.clientLeft + list.clientWidth - Number.parseFloat( style.scrollPaddingRight );
		const box = item.getBoundingClientRect();
		const by = box.right > right ? box.right - right : Math.min( 0, box.left - left );

		if ( by !== 0 ) {
			list.scrollBy( { left: by, behavior: motion() } );
		}
	} );

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
