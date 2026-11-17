/**
 * Keeps the keyboard inside an open dialog (`filters-drawer.ts`): Tab from its
 * last control goes to its first, and Shift+Tab from its first to its last.
 */

/** What a keyboard can reach, as the browser decides it in a dialog's markup. */
const FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled]):not([type="hidden"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'summary',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

/** The controls in the container a customer can reach now: those on screen, not inside a closed section. */
export function focusableIn( container: Element ): HTMLElement[] {
	return Array.from( container.querySelectorAll< HTMLElement >( FOCUSABLE ) ).filter(
		( element ) => element.getClientRects().length > 0,
	);
}

/**
 * Where the focus goes instead of where Tab takes it, or null to let Tab take
 * it: round to the other end at the first or the last control, and to the
 * first (or, going back, the last) from anywhere outside them.
 */
export function nextInCycle< T >(
	items: readonly T[],
	active: unknown,
	backwards: boolean,
): T | null {
	if ( items.length === 0 ) {
		return null;
	}

	const first = items[ 0 ];
	const last = items[ items.length - 1 ];
	const at = items.indexOf( active as T );

	if ( at === -1 ) {
		return backwards ? last : first;
	}

	if ( backwards && active === first ) {
		return last;
	}

	if ( ! backwards && active === last ) {
		return first;
	}

	return null;
}
