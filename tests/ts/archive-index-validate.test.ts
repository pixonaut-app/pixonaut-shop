import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

import { isArchiveIndex } from '../../src/archive/index/validate.ts';

const fixture = (): Record< string, unknown > =>
	JSON.parse(
		readFileSync(
			new URL( './fixtures/archive-index/split-framing.index.json', import.meta.url ),
			'utf8',
		),
	) as Record< string, unknown >;

describe( 'isArchiveIndex', () => {
	it( 'takes an index as the plugin publishes it', () => {
		assert.equal( isArchiveIndex( fixture() ), true );
	} );

	it( 'refuses an index a card of which lacks a field the archive reads', () => {
		const index = fixture();
		const cards = index.cards as Record< string, unknown >[];
		delete cards[ 1 ].onlyFiltered;

		assert.equal( isArchiveIndex( index ), false );
	} );

	it( 'refuses a variation without a price, and an index without a currency', () => {
		const index = fixture();
		const cards = index.cards as { variations: Record< string, unknown >[] }[];
		cards[ 0 ].variations[ 0 ].price = '450';

		assert.equal( isArchiveIndex( index ), false );
		assert.equal( isArchiveIndex( { ...fixture(), currency: null } ), false );
		assert.equal( isArchiveIndex( null ), false );
	} );
} );
