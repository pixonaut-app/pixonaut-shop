/**
 * Where focus goes in the mega header's panel (`src/header/mega-menu.ts`),
 * worked out from positions alone, so the keys' rules are tested without a
 * page.
 */

/** The keys that move between the panel's groups. */
const NEXT = 'ArrowDown';
const PREVIOUS = 'ArrowUp';
const FIRST = 'Home';
const LAST = 'End';

/**
 * The group a key moves focus to from the group at `current`, of `count`
 * groups, round from either end; `null` for a key that moves nothing.
 */
export function groupStep( key: string, current: number, count: number ): number | null {
	if ( count === 0 ) {
		return null;
	}

	switch ( key ) {
		case NEXT:
			return ( current + 1 ) % count;
		case PREVIOUS:
			return ( current - 1 + count ) % count;
		case FIRST:
			return 0;
		case LAST:
			return count - 1;
		default:
			return null;
	}
}

/**
 * Where Tab takes focus while the panel is open, as an index among its
 * `count` focusable elements: round from the last to the first, or the
 * first to the last with Shift, and back in from outside (`current` -1).
 * `null` where the browser's own Tab stays inside the panel.
 */
export function trapStep( current: number, count: number, backwards: boolean ): number | null {
	if ( count === 0 ) {
		return null;
	}

	const first = 0;
	const last = count - 1;

	if ( current === -1 ) {
		return backwards ? last : first;
	}

	if ( backwards ) {
		return current === first ? last : null;
	}

	return current === last ? first : null;
}
