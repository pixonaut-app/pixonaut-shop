/**
 * Choosing the development site's build of Pixonaut for WooCommerce, and the
 * Pixonaut it talks to. Releases are fetched through a fake `gh`
 * and a fake `unzip`, so nothing here reaches GitHub. Run with Node's own
 * test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, it } from 'node:test';

import {
	API_CONSTANTS,
	checkoutProblems,
	checksumMatches,
	defaultCheckout,
	PIXONAUT_WC,
	parseSource,
	pluginMapping,
	pluginNamed,
	releaseZip,
	serverAddress,
	sharedPluginBuilds,
	withPixonaut,
	withPluginSource,
} from '../../scripts/plugin/source.ts';
import {
	type Deps,
	OVERRIDE_FILE,
	RELEASES_DIR,
	usePixonaut,
	usePlugin,
} from '../../scripts/plugin/use.ts';
import { PluginFixture, WOOCOMMERCE } from './plugin-fixture.ts';

const { slug: PLUGIN_SLUG, checkoutEnv: CHECKOUT_ENV } = PIXONAUT_WC;
const MAIN = '/checkouts/pixonaut-for-woocommerce';

describe( 'parseSource', () => {
	it( 'is the fake for fake', () => {
		assert.deepEqual( parseSource( PIXONAUT_WC, 'fake', MAIN, '/here' ), { kind: 'none' } );
	} );

	it( 'is the main checkout for main', () => {
		assert.deepEqual( parseSource( PIXONAUT_WC, 'main', MAIN, '/here' ), {
			kind: 'checkout',
			path: MAIN,
		} );
	} );

	it( 'is the latest release for latest', () => {
		assert.deepEqual( parseSource( PIXONAUT_WC, 'latest', MAIN, '/here' ), {
			kind: 'release',
			tag: null,
		} );
	} );

	for ( const tag of [ 'v1.2.3', 'v0.4.0-beta.2' ] ) {
		it( `is the release ${ tag }`, () => {
			assert.deepEqual( parseSource( PIXONAUT_WC, tag, MAIN, '/here' ), { kind: 'release', tag } );
		} );
	}

	it( 'refuses a tag-like typo rather than taking it for a path', () => {
		assert.throws( () => parseSource( PIXONAUT_WC, 'v1.2', MAIN, '/here' ), /not a release tag/ );
	} );

	it( 'is a checkout for a path, resolved against the working directory', () => {
		assert.deepEqual( parseSource( PIXONAUT_WC, '../plugin-wt', MAIN, '/a/theme' ), {
			kind: 'checkout',
			path: '/a/plugin-wt',
		} );
		assert.deepEqual( parseSource( PIXONAUT_WC, '/b/plugin', MAIN, '/a/theme' ), {
			kind: 'checkout',
			path: '/b/plugin',
		} );
	} );
} );

describe( 'defaultCheckout', () => {
	it( `is ${ CHECKOUT_ENV } when set`, () => {
		assert.equal(
			defaultCheckout( PIXONAUT_WC, { [ CHECKOUT_ENV ]: '/x/plugin' }, '/p/theme' ),
			'/x/plugin',
		);
	} );

	it( "is the plugin beside the theme's main checkout otherwise", () => {
		assert.equal(
			defaultCheckout( PIXONAUT_WC, { [ CHECKOUT_ENV ]: '' }, '/p/pixonaut-shop' ),
			`/p/${ PLUGIN_SLUG }`,
		);
	} );
} );

describe( 'checkoutProblems', () => {
	const everything = () => true;
	const built = [ 'storefront.js', 'storefront.asset.php' ];

	it( 'finds none in a built checkout', () => {
		assert.deepEqual( checkoutProblems( PIXONAUT_WC, MAIN, everything, built ), [] );
	} );

	it( 'says a directory is not the plugin', () => {
		const [ problem ] = checkoutProblems( PIXONAUT_WC, MAIN, () => false, built );

		assert.match( problem, /not a checkout of Pixonaut for WooCommerce/ );
	} );

	it( 'says how to install vendor/', () => {
		const problems = checkoutProblems(
			PIXONAUT_WC,
			MAIN,
			( path ) => path !== 'vendor/autoload.php',
			built,
		);

		assert.deepEqual( problems.length, 1 );
		assert.match( problems[ 0 ], /pnpm composer install/ );
	} );

	it( 'says how to build the assets', () => {
		const problems = checkoutProblems( PIXONAUT_WC, MAIN, everything, [] );

		assert.deepEqual( problems.length, 1 );
		assert.match( problems[ 0 ], /pnpm install && pnpm build/ );
	} );
} );

describe( 'releaseZip', () => {
	it( "is the release's own zip, not the wordpress.org build or the checksum", () => {
		assert.equal(
			releaseZip( PIXONAUT_WC, {
				tagName: 'v1.2.3',
				assets: [
					{ name: 'pixonaut-for-woocommerce-1.2.3-wporg.zip' },
					{ name: 'pixonaut-for-woocommerce-1.2.3.zip.sha256' },
					{ name: 'pixonaut-for-woocommerce-1.2.3.zip' },
				],
			} ),
			'pixonaut-for-woocommerce-1.2.3.zip',
		);
	} );

	it( 'is null while the zip is not attached', () => {
		assert.equal( releaseZip( PIXONAUT_WC, { tagName: 'v1.2.3', assets: [] } ), null );
	} );
} );

describe( 'checksumMatches', () => {
	it( 'reads a sha256sum line', () => {
		assert.equal( checksumMatches( 'ABC123  x.zip\n', 'abc123' ), true );
		assert.equal( checksumMatches( 'abc123  x.zip\n', 'def456' ), false );
	} );
} );

describe( 'withPluginSource', () => {
	it( "adds the build to the development site's plugins and keeps the rest", () => {
		const override = { config: { X: 1 }, env: { development: { config: { Y: 2 } } } };

		assert.deepEqual(
			withPluginSource( override, [ WOOCOMMERCE ], PIXONAUT_WC, MAIN, pluginNamed ),
			{
				config: { X: 1 },
				env: { development: { config: { Y: 2 }, plugins: [ WOOCOMMERCE, MAIN ] } },
			},
		);
	} );

	it( 'never touches the tests site', () => {
		const override = { env: { tests: { config: { Z: 3 } } } };

		assert.deepEqual(
			withPluginSource( override, [ WOOCOMMERCE ], PIXONAUT_WC, MAIN, pluginNamed ).env,
			{
				tests: { config: { Z: 3 } },
				development: { plugins: [ WOOCOMMERCE, MAIN ] },
			},
		);
	} );

	it( 'goes back to .wp-env.json for the fake, leaving no empty object behind', () => {
		const chosen = withPluginSource( {}, [ WOOCOMMERCE ], PIXONAUT_WC, MAIN, pluginNamed );

		assert.deepEqual(
			withPluginSource( chosen, [ WOOCOMMERCE ], PIXONAUT_WC, null, pluginNamed ),
			{},
		);
	} );
} );

describe( 'sharedPluginBuilds', () => {
	it( 'names the top-level plugins that are a build of Pixonaut for WooCommerce', () => {
		assert.deepEqual(
			sharedPluginBuilds(
				{
					plugins: [
						WOOCOMMERCE,
						'../pixonaut-for-woocommerce',
						'/x/.wp-env-plugins/v1.2.3/pixonaut-for-woocommerce/',
						'https://example.com/pixonaut-for-woocommerce-1.2.3.zip',
					],
				},
				pluginNamed,
			),
			[
				'../pixonaut-for-woocommerce',
				'/x/.wp-env-plugins/v1.2.3/pixonaut-for-woocommerce/',
				'https://example.com/pixonaut-for-woocommerce-1.2.3.zip',
			],
		);
	} );

	it( 'names none without top-level plugins, or with only unrelated ones', () => {
		assert.deepEqual( sharedPluginBuilds( {}, pluginNamed ), [] );
		assert.deepEqual(
			sharedPluginBuilds( { plugins: [ WOOCOMMERCE, '../query-monitor' ] }, pluginNamed ),
			[],
		);
	} );
} );

describe( 'withPixonaut', () => {
	it( 'points the browser at the API and WordPress at it through Docker', () => {
		assert.deepEqual(
			withPixonaut( {}, { api: 'http://localhost:8797/', studio: 'http://localhost:3210' } ),
			{
				env: {
					development: {
						config: {
							[ API_CONSTANTS.api ]: 'http://localhost:8797',
							[ API_CONSTANTS.serverApi ]: 'http://host.docker.internal:8797',
							[ API_CONSTANTS.studio ]: 'http://localhost:3210',
						},
					},
				},
			},
		);
	} );

	it( 'keeps a deployed address as it is', () => {
		assert.equal(
			serverAddress( 'https://api.staging.example.com' ),
			'https://api.staging.example.com',
		);
	} );

	it( 'removes only its own constants for the default', () => {
		const chosen = withPixonaut(
			{ env: { development: { config: { WP_DEBUG: true } } } },
			{
				api: 'http://localhost:8797',
				studio: null,
			},
		);

		assert.deepEqual( withPixonaut( chosen, null ), {
			env: { development: { config: { WP_DEBUG: true } } },
		} );
	} );
} );

describe( 'usePlugin', () => {
	let shop: PluginFixture;

	beforeEach( () => {
		shop = new PluginFixture();
	} );

	afterEach( () => {
		shop.remove();
	} );

	it( 'runs the main checkout, beside the theme, and says to restart', () => {
		const main = join( shop.theme, PLUGIN_SLUG );
		shop.checkout( PIXONAUT_WC, main );

		const message = usePlugin( PIXONAUT_WC, 'main', shop.deps() );

		assert.deepEqual( shop.developmentPlugins(), [ WOOCOMMERCE, main ] );
		assert.match( message, /Restart wp-env/ );
	} );

	it( `runs the checkout ${ CHECKOUT_ENV } names`, () => {
		const elsewhere = join( shop.theme, 'elsewhere' );
		shop.checkout( PIXONAUT_WC, elsewhere );

		usePlugin( PIXONAUT_WC, 'main', shop.deps( { [ CHECKOUT_ENV ]: elsewhere } ) );

		assert.deepEqual( shop.override(), {
			env: { development: { mappings: { [ pluginMapping( PIXONAUT_WC ) ]: elsewhere } } },
		} );
	} );

	it( 'refuses an unbuilt checkout, says how to build it, and changes nothing', () => {
		const main = join( shop.theme, PLUGIN_SLUG );
		shop.checkout( PIXONAUT_WC, main, { built: false } );

		assert.throws(
			() => usePlugin( PIXONAUT_WC, 'main', shop.deps() ),
			/pnpm install && pnpm build/,
		);
		assert.equal( existsSync( join( shop.theme, OVERRIDE_FILE ) ), false );
	} );

	it( 'downloads, checks and unpacks a release, and runs it', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3' );

		const message = usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() );
		const plugin = join( shop.theme, RELEASES_DIR, 'v1.2.3', PLUGIN_SLUG );

		assert.deepEqual( shop.developmentPlugins(), [ WOOCOMMERCE, plugin ] );
		assert.ok( existsSync( join( plugin, `${ PLUGIN_SLUG }.php` ) ) );
		assert.match( message, /release v1\.2\.3/ );
	} );

	it( 'runs the latest release', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3' );

		assert.match( usePlugin( PIXONAUT_WC, 'latest', shop.deps() ), /release v1\.2\.3/ );
		assert.deepEqual( shop.ghCalls[ 0 ].slice( 0, 3 ), [ 'release', 'view', '--repo' ] );
	} );

	it( 'reuses a release it already unpacked', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3' );
		usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() );
		shop.ghCalls = [];

		usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() );

		assert.equal( shop.ghCalls.filter( ( call ) => call[ 1 ] === 'download' ).length, 0 );
	} );

	it( 'leaves no half-unpacked release behind for the next run to reuse', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3' );
		const fake = shop.deps();
		const interrupted: Deps = {
			...fake,
			unzip: ( zip, directory ) => {
				fake.unzip( zip, directory );
				throw new Error( 'unzip: disk full' );
			},
		};

		assert.throws( () => usePlugin( PIXONAUT_WC, 'v1.2.3', interrupted ), /disk full/ );
		assert.equal( existsSync( join( shop.theme, RELEASES_DIR, 'v1.2.3' ) ), false );
		assert.deepEqual( readdirSync( join( shop.theme, RELEASES_DIR ) ), [] );
		assert.equal( existsSync( join( shop.theme, OVERRIDE_FILE ) ), false );
		shop.ghCalls = [];

		usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() );

		assert.equal( shop.ghCalls.filter( ( call ) => call[ 1 ] === 'download' ).length, 1 );
		assert.deepEqual( shop.developmentPlugins(), [
			WOOCOMMERCE,
			join( shop.theme, RELEASES_DIR, 'v1.2.3', PLUGIN_SLUG ),
		] );
	} );

	it( 'says so when the repository has no releases yet', () => {
		assert.throws(
			() => usePlugin( PIXONAUT_WC, 'latest', shop.deps() ),
			/has no releases yet\. Use a checkout instead: pnpm plugin:use main/,
		);
	} );

	it( 'says so when the release does not exist', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3' );

		assert.throws(
			() => usePlugin( PIXONAUT_WC, 'v9.9.9', shop.deps() ),
			/has no release v9\.9\.9/,
		);
	} );

	it( 'says so while the zip is not attached', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3', { zip: false } );

		assert.throws(
			() => usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() ),
			/no pixonaut-for-woocommerce zip attached/,
		);
	} );

	it( 'refuses a zip with no checksum attached, unpacks nothing and changes nothing', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3', { checksum: false } );

		assert.throws( () => usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() ), /no checksum attached/ );
		assert.equal( existsSync( join( shop.theme, RELEASES_DIR, 'v1.2.3', PLUGIN_SLUG ) ), false );
		assert.equal( existsSync( join( shop.theme, OVERRIDE_FILE ) ), false );
	} );

	it( 'refuses a zip whose checksum did not download, and unpacks nothing', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3' );
		const fake = shop.deps();
		const withoutChecksum: Deps = {
			...fake,
			gh: ( args ) => {
				const printed = fake.gh( args );

				if ( args[ 1 ] === 'download' ) {
					rmSync(
						join(
							args[ args.indexOf( '--dir' ) + 1 ],
							`${ shop.zipName( PIXONAUT_WC, 'v1.2.3' ) }.sha256`,
						),
					);
				}

				return printed;
			},
		};

		assert.throws(
			() => usePlugin( PIXONAUT_WC, 'v1.2.3', withoutChecksum ),
			/sha256 did not download/,
		);
		assert.equal( existsSync( join( shop.theme, RELEASES_DIR, 'v1.2.3', PLUGIN_SLUG ) ), false );
		assert.equal( existsSync( join( shop.theme, OVERRIDE_FILE ) ), false );
	} );

	it( 'refuses a zip that does not match its checksum', () => {
		shop.release( PIXONAUT_WC, 'v1.2.3', { goodChecksum: false } );

		assert.throws(
			() => usePlugin( PIXONAUT_WC, 'v1.2.3', shop.deps() ),
			/does not match its checksum/,
		);
	} );

	it( 'refuses a top-level plugins entry that is the plugin, and changes nothing', () => {
		const main = join( shop.theme, PLUGIN_SLUG );
		shop.checkout( PIXONAUT_WC, main );
		const shared = `${ JSON.stringify( { plugins: [ WOOCOMMERCE, main ] }, null, 2 ) }\n`;
		writeFileSync( join( shop.theme, OVERRIDE_FILE ), shared );

		for ( const arg of [ 'fake', 'main' ] ) {
			assert.throws(
				() => usePlugin( PIXONAUT_WC, arg, shop.deps() ),
				( error: Error ) =>
					error.message.includes( `Remove ${ main } from the top-level "plugins"` ),
			);
			assert.equal( readFileSync( join( shop.theme, OVERRIDE_FILE ), 'utf8' ), shared );
		}
	} );

	it( 'keeps unrelated top-level plugins', () => {
		const main = join( shop.theme, PLUGIN_SLUG );
		shop.checkout( PIXONAUT_WC, main );
		writeFileSync(
			join( shop.theme, OVERRIDE_FILE ),
			JSON.stringify( { plugins: [ '../query-monitor' ] } ),
		);

		usePlugin( PIXONAUT_WC, 'main', shop.deps() );

		assert.deepEqual( shop.override().plugins, [ '../query-monitor' ] );
		assert.deepEqual( shop.developmentPlugins(), [ WOOCOMMERCE, main ] );
	} );

	it( 'goes back to the fake and keeps the Pixonaut addresses', () => {
		const main = join( shop.theme, PLUGIN_SLUG );
		shop.checkout( PIXONAUT_WC, main );
		usePlugin( PIXONAUT_WC, 'main', shop.deps() );
		usePixonaut( [ 'http://localhost:8797' ], shop.theme );

		usePlugin( PIXONAUT_WC, 'fake', shop.deps() );

		assert.equal( shop.developmentPlugins(), undefined );
		assert.deepEqual(
			Object.keys(
				( shop.override() as { env: { development: { config: object } } } ).env.development.config,
			).sort(),
			[ API_CONSTANTS.api, API_CONSTANTS.serverApi ].sort(),
		);
	} );
} );
