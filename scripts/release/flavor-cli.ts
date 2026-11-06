/**
 * The release's flavor steps, run from a built checkout:
 *
 *   node scripts/release/flavor-cli.ts assemble [--flavor wporg] <stage-dir>
 *     stages the theme at <stage-dir>/pixonaut-shop/ and checks it;
 *   node scripts/release/flavor-cli.ts check [--flavor wporg] <theme-dir>
 *     checks a staged or unzipped theme;
 *   node scripts/release/flavor-cli.ts zip-name [--flavor wporg] <version>
 *     prints the zip's file name.
 *
 * The flavor is `github` unless named (see flavor.ts).
 */

import {
	assemble,
	type Flavor,
	flavorFromArgs,
	stagedThemeProblems,
	withoutFlavor,
	zipName,
} from './flavor.ts';

const USAGE = 'Usage: flavor-cli.ts assemble|check|zip-name [--flavor <name>] <path-or-version>';

const args = process.argv.slice( 2 );
const flavor = flavorFromArgs( args );
const [ command, target ] = withoutFlavor( args );

if ( target === undefined ) {
	throw new Error( USAGE );
}

function check( theme: string, checked: Flavor ): void {
	const problems = stagedThemeProblems( theme, checked );

	for ( const problem of problems ) {
		console.log( `::error::${ problem }` );
	}

	if ( problems.length > 0 ) {
		process.exitCode = 1;
	}
}

switch ( command ) {
	case 'assemble':
		check( assemble( process.cwd(), target, flavor ), flavor );
		break;
	case 'check':
		check( target, flavor );
		break;
	case 'zip-name':
		console.log( zipName( target, flavor ) );
		break;
	default:
		throw new Error( USAGE );
}
