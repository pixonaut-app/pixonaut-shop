import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

import { choiceFrom } from '../../src/archive/index/address.ts';
import { counts, linkOf, pictureOf, shownCards } from '../../src/archive/index/engine.ts';
import type { ArchiveIndex } from '../../src/archive/index/types.ts';

/**
 * The archive filtered in the browser shows, for every address of the
 * plugin's fixtures, the cards, links, pictures and counts its
 * server-rendered archive shows (`fixtures/archive-index/README.md`).
 */

interface Expected {
	query: Record< string, string > | [];
	cards: string[];
	links: string[];
	images: string[];
	counts?: Record< string, Record< string, number > >;
}

const DIRECTORY = new URL( './fixtures/archive-index/', import.meta.url );
const INDEX_SUFFIX = '.index.json';
const DEFAULT_SORT = 'menu_order';

/** A link with its arguments in one order: the server writes them in any. */
function normalised( link: string ): string {
	const url = new URL( link );
	const query = [ ...url.searchParams ].sort().map( ( pair ) => pair.join( '=' ) );

	return `${ url.origin }${ url.pathname }?${ query.join( '&' ) }`;
}

for ( const file of readdirSync( DIRECTORY ).filter( ( name ) => name.endsWith( INDEX_SUFFIX ) ) ) {
	const name = file.slice( 0, -INDEX_SUFFIX.length );
	const index = JSON.parse( readFileSync( new URL( file, DIRECTORY ), 'utf8' ) ) as ArchiveIndex;
	const expected = JSON.parse(
		readFileSync( new URL( `${ name }.expected.json`, DIRECTORY ), 'utf8' ),
	) as Expected[];
	const base = new URL( index.cards[ 0 ]?.url ?? 'http://localhost/' ).origin;

	describe( `the ${ name } fixture`, () => {
		for ( const entry of expected ) {
			const query = Array.isArray( entry.query ) ? {} : entry.query;

			it( `shows what the server shows at ?${ new URLSearchParams( query ) }`, () => {
				const url = new URL( `${ base }/?${ new URLSearchParams( query ) }` );
				const choice = choiceFrom( url, index.filters, DEFAULT_SORT );
				const shown = shownCards( index, choice );

				assert.deepEqual(
					shown.map( ( card ) => card.card.id ),
					entry.cards,
				);
				assert.deepEqual(
					shown.map( ( card ) => normalised( linkOf( card, base, choice ) ) ),
					entry.links.map( normalised ),
				);
				assert.deepEqual(
					shown.map( ( card ) => pictureOf( index, card, choice ).src ),
					entry.images,
				);

				const got = counts( index, choice );

				for ( const [ key, values ] of Object.entries( entry.counts ?? {} ) ) {
					for ( const [ id, count ] of Object.entries( values ) ) {
						assert.equal( got.get( key )?.get( id ) ?? 0, count, `${ key }=${ id }` );
					}
				}
			} );
		}
	} );
}
