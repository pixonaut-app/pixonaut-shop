/**
 * Unticking a value in an archive's drawer clears the values chosen of the
 * dependent filters it alone opened (`inc/Archive/NestedAttributeFilters.php`):
 * unticking "Frame" clears the frame colours chosen under it, unless another
 * value still ticked, such as "Hanger", opens the colours too.
 *
 * The chosen values are unticked as a customer would untick them, before the
 * value itself, so WooCommerce asks for the results without any of them at
 * once. The archive filtered in the browser (`src/archive/client-archive.ts`) clears them itself.
 */

import { CLIENT_ARCHIVE_ATTRIBUTE } from './client-archive-flag.ts';
import { openedAlone } from './opened-alone.ts';

const PANEL = '.pixonaut-shop-archive-filters';
const ITEM = '.wc-block-product-filter-checkbox-list__item';
const LIST = '.wc-block-product-filter-checkbox-list';
const INPUT = 'input.wc-block-product-filter-checkbox-list__input';
/** On a value: the dependent attributes it opens, space separated. */
const OPENS = 'data-pixonaut-shop-opens';
/** On a nested group: the attribute it is. */
const NESTED_ATTRIBUTE = 'data-pixonaut-shop-attribute';

function opensOf( item: Element | null ): string[] {
	return ( item?.getAttribute( OPENS ) ?? '' ).split( ' ' ).filter( ( name ) => name !== '' );
}

/** The chosen values of the dependents a value just unticked alone opened. */
export function valuesToClear( input: HTMLInputElement ): HTMLInputElement[] {
	const item = input.closest( ITEM );
	const list = item?.parentElement?.closest( LIST );

	if ( ! item || ! list ) {
		return [];
	}

	const stillTicked = Array.from( list.querySelectorAll< HTMLInputElement >( `:scope ${ INPUT }` ) )
		.filter(
			( other ) =>
				other !== input && other.checked && other.closest( ITEM )?.closest( LIST ) === list,
		)
		.map( ( other ) => opensOf( other.closest( ITEM ) ) );
	const cleared = openedAlone( opensOf( item ), stillTicked );
	const panel = input.closest( PANEL );

	return cleared.flatMap( ( dependent ) =>
		Array.from(
			panel?.querySelectorAll< HTMLInputElement >(
				`[${ NESTED_ATTRIBUTE }="${ CSS.escape( dependent ) }"] ${ INPUT }:checked`,
			) ?? [],
		),
	);
}

function onChange( event: Event ): void {
	const input = event.target;

	if (
		document.documentElement.hasAttribute( CLIENT_ARCHIVE_ATTRIBUTE ) ||
		! ( input instanceof HTMLInputElement ) ||
		input.checked ||
		! input.closest( `${ PANEL } [${ OPENS }]` )
	) {
		return;
	}

	for ( const value of valuesToClear( input ) ) {
		value.checked = false;
		value.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
}

// In the capture phase, before WooCommerce asks for the results without the value.
document.addEventListener( 'change', onChange, true );
