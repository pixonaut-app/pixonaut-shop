/**
 * Release's guard: fails unless the tag being released names the version
 * style.css holds at that tag. Run as
 * `node scripts/release/check-tag-cli.ts v1.2.0` from the tag's checkout.
 */

import { readFileSync } from 'node:fs';

import { stylesheetVersion, tagVersion } from './version.ts';

const [ tag ] = process.argv.slice( 2 );

if ( tag === undefined ) {
	throw new Error( 'Name the tag: check-tag-cli.ts v1.2.0' );
}

const version = tagVersion( tag );
const stylesheet = stylesheetVersion( readFileSync( 'style.css', 'utf8' ) );

if ( version === null ) {
	console.log( `::error::${ tag } is not a release tag (vX.Y.Z).` );
	process.exitCode = 1;
} else if ( version !== stylesheet ) {
	console.log(
		`::error::${ tag } is not the version in style.css (${ stylesheet }). The release pull request sets it; merge that and release again.`,
	);
	process.exitCode = 1;
}
