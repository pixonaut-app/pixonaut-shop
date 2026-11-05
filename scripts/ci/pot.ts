/**
 * A translation catalogue reduced to what makes it stale or current: its
 * strings, contexts, plurals and translators' comments. `wp i18n make-pot`
 * also writes each string's `#: file:line` references and a fresh
 * `POT-Creation-Date` on every run, and orders entries by the first file it
 * meets each string in; none of that is a string change, so references and
 * the date are dropped and the order is ignored.
 */

const REFERENCE_LINE = /^#: /;
const CREATION_DATE_HEADER = /^"POT-Creation-Date: /;

/** Entries are separated by one or more blank lines. */
const ENTRY_SEPARATOR = /\n\n+/;

/** A catalogue's entries, sorted, each without its references or creation date. */
export function potEntries( pot: string ): string[] {
	return pot
		.split( ENTRY_SEPARATOR )
		.map( ( entry ) =>
			entry
				.split( '\n' )
				.filter(
					( line ) =>
						line !== '' && ! REFERENCE_LINE.test( line ) && ! CREATION_DATE_HEADER.test( line ),
				)
				.join( '\n' ),
		)
		.filter( ( entry ) => entry !== '' )
		.sort();
}

export function potStrings( pot: string ): string {
	return potEntries( pot ).join( '\n\n' );
}
