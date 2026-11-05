/**
 * CI's stale-catalogue check compares the committed POT with a regenerated
 * one by their strings alone: a line reference that moved, or an entry
 * make-pot now meets first in another file, is not a stale catalogue. Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { potEntries, potStrings } from '../../scripts/ci/pot.ts';

const HEADER = [
	'msgid ""',
	'msgstr ""',
	'"Project-Id-Version: Pixonaut Shop 0.1.0\\n"',
	'"POT-Creation-Date: 2026-09-29T16:05:35+00:00\\n"',
	'"X-Domain: pixonaut-shop\\n"',
	'',
];

const ENTRY = [
	"#. translators: %s: the customer's name",
	'#: inc/Dependencies/WooCommerceNotice.php:426',
	'#: patterns/footer.php:12',
	'msgctxt "button"',
	'msgid "Add %s"',
	'msgid_plural "Add %s each"',
	'msgstr[0] ""',
	'msgstr[1] ""',
	'',
];

const OTHER_ENTRY = [ '#: style.css', 'msgid "Pixonaut Shop"', 'msgstr ""', '' ];

function pot( ...lines: string[][] ): string {
	return lines.flat().join( '\n' );
}

/** `ENTRY` with one line replaced. */
function entryWith( from: string, to: string ): string[] {
	return ENTRY.map( ( line ) => ( line === from ? to : line ) );
}

describe( 'potStrings', () => {
	const committed = potStrings( pot( HEADER, ENTRY, OTHER_ENTRY ) );

	it( 'is the same when only a line reference moved', () => {
		const moved = entryWith(
			'#: inc/Dependencies/WooCommerceNotice.php:426',
			'#: inc/Dependencies/WooCommerceNotice.php:435',
		);

		assert.equal( potStrings( pot( HEADER, moved, OTHER_ENTRY ) ), committed );
	} );

	it( 'is the same when only the creation date changed', () => {
		const regenerated = HEADER.map( ( line ) =>
			line.startsWith( '"POT-Creation-Date:' )
				? '"POT-Creation-Date: 2026-09-30T08:00:00+00:00\\n"'
				: line,
		);

		assert.equal( potStrings( pot( regenerated, ENTRY, OTHER_ENTRY ) ), committed );
	} );

	for ( const [ what, from, to ] of [
		[ 'a msgid', 'msgid "Add %s"', 'msgid "Buy %s"' ],
		[ 'a msgctxt', 'msgctxt "button"', 'msgctxt "link"' ],
		[ 'a plural', 'msgid_plural "Add %s each"', 'msgid_plural "Add %s apiece"' ],
		[
			"a translators' comment",
			"#. translators: %s: the customer's name",
			'#. translators: %s: a name',
		],
	] ) {
		it( `differs when ${ what } changed`, () => {
			assert.notEqual( potStrings( pot( HEADER, entryWith( from, to ), OTHER_ENTRY ) ), committed );
		} );
	}

	it( 'differs when a string was removed', () => {
		assert.notEqual( potStrings( pot( HEADER, ENTRY ) ), committed );
	} );

	it( 'is the same when two entries swapped places', () => {
		assert.equal( potStrings( pot( HEADER, OTHER_ENTRY, ENTRY ) ), committed );
	} );
} );

describe( 'potEntries', () => {
	it( 'is each entry without its references, empty ones dropped', () => {
		const onlyReferences = [ '#: inc/X.php:1', '' ];

		assert.deepEqual( potEntries( pot( onlyReferences, OTHER_ENTRY ) ), [
			'msgid "Pixonaut Shop"\nmsgstr ""',
		] );
	} );
} );
