/**
 * The release version's one source, style.css, and the tag that must name
 * it. Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

import { stylesheetVersion, tagVersion } from '../../scripts/release/version.ts';

const STYLESHEET = [
	'/*',
	'Theme Name: Pixonaut Shop',
	'Version: 0.1.0',
	'Requires PHP: 8.2',
	'*/',
	'',
].join( '\n' );

describe( 'stylesheetVersion', () => {
	it( "is the Version header's value", () => {
		assert.equal( stylesheetVersion( STYLESHEET ), '0.1.0' );
	} );

	it( 'is null without a Version header', () => {
		assert.equal( stylesheetVersion( '/*\nTheme Name: Pixonaut Shop\n*/\n' ), null );
	} );

	it( "reads the theme's own style.css", () => {
		const version = stylesheetVersion( readFileSync( 'style.css', 'utf8' ) );

		assert.ok( version !== null && tagVersion( `v${ version }` ) !== null );
	} );
} );

describe( 'tagVersion', () => {
	it( 'is the version a release tag names', () => {
		assert.equal( tagVersion( 'v1.2.0' ), '1.2.0' );
	} );

	for ( const tag of [ '1.2.0', 'v1.2', 'v1.2.0-rc1', 'release-1.2.0' ] ) {
		it( `is null for ${ tag }`, () => {
			assert.equal( tagVersion( tag ), null );
		} );
	}
} );

describe( 'release-please', () => {
	const config = JSON.parse( readFileSync( 'release-please-config.json', 'utf8' ) );
	const stylesheet = readFileSync( 'style.css', 'utf8' );

	it( 'starts at 1.0.0 on a repository with no releases', () => {
		assert.equal( config.packages[ '.' ][ 'initial-version' ], '1.0.0' );
	} );

	// Empty until the first release; from then on release-please writes the
	// released version there and into style.css in the same pull request.
	it( "records no version, or the one style.css's Version holds", () => {
		const manifest = JSON.parse( readFileSync( '.release-please-manifest.json', 'utf8' ) );

		if ( Object.keys( manifest ).length === 0 ) {
			return;
		}
		assert.deepEqual( Object.keys( manifest ), [ '.' ] );
		assert.equal( manifest[ '.' ], stylesheetVersion( stylesheet ) );
	} );

	it( "sets style.css's Version, between the markers it looks for", () => {
		assert.ok( config.packages[ '.' ][ 'extra-files' ].includes( 'style.css' ) );
		assert.match(
			stylesheet,
			/^x-release-please-start-version\nVersion: [^\n]+\nx-release-please-end$/m,
		);
	} );

	it( 'keeps style.css readable as a version', () => {
		assert.notEqual( stylesheetVersion( stylesheet ), null );
	} );
} );
