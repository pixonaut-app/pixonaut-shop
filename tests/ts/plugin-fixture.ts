/**
 * A theme checkout in a temporary directory, with fake releases of the
 * plugin `pnpm plugin:use` installs, a fake `gh` that
 * serves them and a fake `unzip`, so nothing reaches GitHub.
 */

import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { mainFile, PLUGINS, type Plugin } from '../../scripts/plugin/source.ts';
import { type Deps, OVERRIDE_FILE, WP_ENV_CONFIG } from '../../scripts/plugin/use.ts';

export const WOOCOMMERCE = 'https://downloads.wordpress.org/plugin/woocommerce.zip';

/** How a release looks while, or after, its release workflow publishes it. */
interface ReleaseState {
	readonly zip: boolean;
	readonly checksum: boolean;
	readonly goodChecksum: boolean;
}

export class PluginFixture {
	readonly theme = mkdtempSync( join( tmpdir(), 'pixonaut-shop-plugin-' ) );
	/** Each plugin's releases, oldest first: the last is the latest. */
	readonly releases = new Map< Plugin, Map< string, ReleaseState > >();
	ghCalls: string[][] = [];

	constructor() {
		writeFileSync(
			join( this.theme, WP_ENV_CONFIG ),
			JSON.stringify( { plugins: [ WOOCOMMERCE ], env: { tests: {} } } ),
		);
	}

	remove(): void {
		rmSync( this.theme, { recursive: true, force: true } );
	}

	/** A release of `plugin`, as its release workflow publishes it. */
	release(
		plugin: Plugin,
		tag: string,
		{ zip = true, checksum = true, goodChecksum = true } = {},
	): void {
		const releases = this.releases.get( plugin ) ?? new Map();

		releases.set( tag, { zip, checksum, goodChecksum } );
		this.releases.set( plugin, releases );
	}

	zipName( plugin: Plugin, tag: string ): string {
		return `${ plugin.slug }-${ tag.replace( /^v/, '' ) }.zip`;
	}

	deps( env: Record< string, string > = {} ): Deps {
		return {
			cwd: this.theme,
			env,
			themeMainCheckout: join( this.theme, 'main' ),
			gh: ( args ) => {
				this.ghCalls.push( [ ...args ] );

				const repository = args[ args.indexOf( '--repo' ) + 1 ];
				const plugin = PLUGINS.find( ( each ) => each.repository === repository );

				assert.ok( plugin, `gh was asked about ${ repository }` );

				const releases = this.releases.get( plugin ) ?? new Map< string, ReleaseState >();
				const [ command, action, maybeTag ] = args;
				const tag = maybeTag?.startsWith( 'v' ) ? maybeTag : [ ...releases.keys() ].at( -1 );
				const found = tag === undefined ? undefined : releases.get( tag );

				assert.equal( command, 'release' );

				if ( tag === undefined || found === undefined ) {
					throw new Error( 'release not found' );
				}

				const zip = this.zipName( plugin, tag );

				if ( action === 'view' ) {
					return JSON.stringify( {
						tagName: tag,
						assets: [
							...( found.zip ? [ { name: zip } ] : [] ),
							...( found.checksum ? [ { name: `${ zip }.sha256` } ] : [] ),
						],
					} );
				}

				// download <tag> --repo <repo> --pattern <zip> --pattern <sha256> --dir <dir>
				const directory = args[ args.indexOf( '--dir' ) + 1 ];
				// What the fake zip holds: its text names the plugin and the tag.
				const honest = Buffer.from( `zip of ${ plugin.slug } ${ tag }` );

				writeFileSync(
					join( directory, zip ),
					found.goodChecksum ? honest : Buffer.from( 'corrupted' ),
				);
				if ( found.checksum ) {
					writeFileSync(
						join( directory, `${ zip }.sha256` ),
						`${ createHash( 'sha256' ).update( honest ).digest( 'hex' ) }  ${ zip }\n`,
					);
				}

				return '';
			},
			unzip: ( zip, directory ) => {
				const contents = readFileSync( zip, 'utf8' );
				const [ , , slug ] = contents.split( ' ' );
				const unpacked = join( directory, slug );

				mkdirSync( unpacked, { recursive: true } );
				writeFileSync( join( unpacked, `${ slug }.php` ), `<?php // ${ contents }\n` );
			},
		};
	}

	override(): Record< string, unknown > {
		return JSON.parse( readFileSync( join( this.theme, OVERRIDE_FILE ), 'utf8' ) );
	}

	developmentPlugins(): unknown {
		return ( this.override() as { env?: { development?: { plugins?: unknown } } } ).env?.development
			?.plugins;
	}

	/** A checkout of `plugin` at `path`, with asset sources or none, built or not. */
	checkout( plugin: Plugin, path: string, { sources = true, built = true } = {} ): void {
		mkdirSync( join( path, 'vendor' ), { recursive: true } );

		if ( sources ) {
			mkdirSync( join( path, 'assets/src' ), { recursive: true } );
		}

		writeFileSync( join( path, mainFile( plugin ) ), '<?php\n' );
		writeFileSync( join( path, plugin.autoloader ), '<?php\n' );

		if ( built ) {
			mkdirSync( join( path, 'assets/build' ), { recursive: true } );
			writeFileSync( join( path, 'assets/build/storefront.asset.php' ), '<?php\n' );
		}
	}
}
