/**
 * CI's stale-catalogue check: fails when the POT `pnpm i18n:pot` just
 * regenerated has different strings from the committed one. Run through
 * `pnpm i18n:check`, which names the catalogue.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { isDeepStrictEqual } from 'node:util';

import { potEntries } from './pot.ts';

const [ catalogue ] = process.argv.slice( 2 );

if ( catalogue === undefined ) {
	throw new Error( 'Name the catalogue to check, as `pnpm i18n:check` does.' );
}

const committed = potEntries(
	execFileSync( 'git', [ 'show', `HEAD:${ catalogue }` ], { encoding: 'utf8' } ),
);
const regenerated = potEntries( readFileSync( catalogue, 'utf8' ) );

/** Each entry on one side only, every line of it prefixed with `marker`. */
function only( entries: readonly string[], other: readonly string[], marker: string ): string[] {
	const others = new Set( other );

	return entries
		.filter( ( entry ) => ! others.has( entry ) )
		.map( ( entry ) =>
			entry
				.split( '\n' )
				.map( ( line ) => `${ marker }${ line }` )
				.join( '\n' ),
		);
}

if ( ! isDeepStrictEqual( committed, regenerated ) ) {
	console.log( `::error::${ catalogue } is out of date. Run 'pnpm i18n' and commit the result.` );
	for ( const entry of [
		...only( committed, regenerated, '- ' ),
		...only( regenerated, committed, '+ ' ),
	] ) {
		console.log( `${ entry }\n` );
	}
	process.exitCode = 1;
}
