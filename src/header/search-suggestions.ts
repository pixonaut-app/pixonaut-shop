/**
 * The header search's suggestions (`src/header/search.ts`), worked out from
 * data alone so they are tested without a page: the results page's answer
 * for the panel as the suggestions it lists, and where the arrow keys move
 * among them.
 */

/** One suggestion, as the panel shows it: a product, or one card of a product the results page lists once per card. */
export interface Suggestion {
	id: number;
	name: string;
	url: string;
	image: string | null;
	/** The store's price HTML, as the results page prints it, kept to inline markup by the server. */
	price: string;
}

/** What the results page's search answers for the panel: its count and its first suggestions. */
export interface Found {
	total: number;
	suggestions: Suggestion[];
}

function isRecord( value: unknown ): value is Record< string, unknown > {
	return typeof value === 'object' && value !== null;
}

function suggestionFrom( value: unknown ): Suggestion | null {
	if (
		! isRecord( value ) ||
		typeof value.id !== 'number' ||
		typeof value.name !== 'string' ||
		typeof value.url !== 'string' ||
		typeof value.price !== 'string'
	) {
		return null;
	}

	return {
		id: value.id,
		name: value.name,
		url: value.url,
		image: typeof value.image === 'string' && value.image !== '' ? value.image : null,
		price: value.price,
	};
}

/**
 * The search's answer (`inc/Header/SearchSuggestions.php`) as what the panel
 * shows; null for an answer of any other shape. A suggestion it cannot read
 * is left out, and the count is never fewer than the suggestions.
 */
export function foundFrom( answer: unknown ): Found | null {
	if ( ! isRecord( answer ) || ! Array.isArray( answer.suggestions ) ) {
		return null;
	}

	const suggestions = answer.suggestions
		.map( suggestionFrom )
		.filter( ( suggestion ): suggestion is Suggestion => suggestion !== null );
	const total =
		typeof answer.total === 'number' && Number.isFinite( answer.total ) ? answer.total : 0;

	return { suggestions, total: Math.max( total, suggestions.length ) };
}

/** The keys that move among the suggestions. */
const NEXT = 'ArrowDown';
const PREVIOUS = 'ArrowUp';

/** The field itself, where no suggestion is active. */
const FIELD = -1;

/**
 * The suggestion a key makes active from the one at `current` (-1: none,
 * the field), of `count`: past either end, back to the field. `null` for a
 * key the field keeps, such as the arrows that move its caret.
 */
export function optionStep( key: string, current: number, count: number ): number | null {
	if ( count === 0 ) {
		return null;
	}

	switch ( key ) {
		case NEXT:
			return current === count - 1 ? FIELD : current + 1;
		case PREVIOUS:
			if ( current === FIELD ) {
				return count - 1;
			}
			return current - 1;
		default:
			return null;
	}
}
