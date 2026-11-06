/**
 * What a change needs run: the rules that turn changed paths into
 * jobs and end-to-end specs, and the map that names each area's specs. A
 * wrong rule skips a suite and still reports green, so the rules are tested
 * like any other code. Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, it } from 'node:test';

import playwrightConfig from '../../playwright.config.ts';
import {
	affected,
	BLOCK_SPECS,
	E2E_DIR,
	E2E_OWNERS,
	EDITOR_SPECS,
	everything,
	fullRunPassed,
	githubOutput,
	HEADER_SCRIPT_SPECS,
	jobOutputs,
	LARGE_SELECTION_SHARDS,
	LOOK_SPECS,
	localCommands,
	MIN_SPECS_TO_SHARD,
	nothing,
	PAGE_SPECS,
	PATTERN_SPECS,
	PRODUCT_SCRIPT_SPECS,
	SITE_WIDE_SPECS,
	specName,
	specNames,
	specPath,
} from '../../scripts/ci/affected.ts';

/** Every spec the suite has, read the way the CI entry point reads it. */
const ALL_SPECS = specNames( readdirSync( E2E_DIR ) );

const NO_JOBS = {
	php: false,
	js: false,
	shellScripts: false,
	integration: false,
	specs: [],
	wholeSuite: false,
	shards: 0,
	unmatched: [],
};

const EVERY_JOB = {
	php: true,
	js: true,
	shellScripts: true,
	integration: true,
	specs: ALL_SPECS,
	wholeSuite: true,
	shards: ALL_SPECS.length >= MIN_SPECS_TO_SHARD ? LARGE_SELECTION_SHARDS : 1,
	unmatched: [],
};

/** A suite large enough to shard, so a selection can be more or less than half of it. */
const LARGE_SUITE = Array.from( { length: MIN_SPECS_TO_SHARD }, ( _, index ) => `spec-${ index }` );

/** A suite too small to shard. */
const SMALL_SUITE = [ 'a', 'b', 'c', 'd' ];

function jobsFor( ...paths: string[] ) {
	return affected( paths, ALL_SPECS );
}

describe( 'affected', () => {
	for ( const path of [
		'docs/TESTING.md',
		'README.md',
		'CHANGELOG.md',
		'LICENSE',
		'release-please-config.json',
		'.release-please-manifest.json',
	] ) {
		it( `runs nothing for ${ path }, which no suite reads`, () => {
			assert.deepEqual( jobsFor( path ), NO_JOBS );
		} );
	}

	for ( const path of [
		'patterns/footer.php',
		'templates/single-product.html',
		'parts/footer.html',
		'languages/sv_SE.po',
		'theme.json',
		'styles/natur.json',
		'style.css',
	] ) {
		it( `runs php and integration for ${ path }, which the unit suite reads`, () => {
			const result = jobsFor( path );

			assert.equal( result.php, true );
			assert.equal( result.integration, true );
			assert.deepEqual( result.unmatched, [] );
		} );
	}

	it( 'runs the unit suite, and no page, for readme.txt, which the unit suite reads', () => {
		const result = jobsFor( 'readme.txt' );

		assert.equal( result.php, true );
		assert.equal( result.integration, false );
		assert.deepEqual( result.specs, [] );
		assert.deepEqual( result.unmatched, [] );
	} );

	it( 'runs the whole e2e suite for an area mapped to all', () => {
		const result = jobsFor( 'inc/Assets/AssetManager.php' );

		assert.equal( result.php, true );
		assert.equal( result.integration, true );
		assert.equal( result.js, false );
		assert.equal( result.wholeSuite, true );
	} );

	it( 'runs the whole e2e suite for the block styles, the menu heading and the block loader, on every page', () => {
		for ( const path of [
			'inc/Blocks/BlockStyles.php',
			'inc/Blocks/MenuHeading.php',
			'inc/Blocks/BlockLoader.php',
		] ) {
			const result = jobsFor( path );

			assert.equal( result.php, true );
			assert.equal( result.integration, true );
			assert.equal( result.wholeSuite, true );
		}
	} );

	for ( const path of [
		'src/blocks/icon/block.json',
		'src/blocks/icon/edit.tsx',
		'src/blocks/product-categories/render.php',
		'src/blocks/product-categories/style.css',
	] ) {
		it( `builds, checks and runs the block specs for ${ path }`, () => {
			assert.deepEqual( jobsFor( path ), {
				...NO_JOBS,
				php: true,
				js: true,
				integration: true,
				specs: [ 'blocks', 'mini-cart', 'site-editor' ],
				shards: 1,
			} );
		} );
	}

	it( 'runs no e2e spec for the update checker, which the integration suite covers', () => {
		assert.deepEqual( jobsFor( 'inc/Updates/GitHubUpdater.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
		} );
	} );

	it( "runs no e2e spec for a page's excerpt, which the integration suite covers", () => {
		assert.deepEqual( jobsFor( 'inc/Content/PageExcerpt.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
		} );
	} );

	it( 'runs the account spec for the account dashboard', () => {
		assert.deepEqual( jobsFor( 'inc/Account/Dashboard.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [ 'account' ],
			shards: 1,
		} );
	} );

	it( 'runs the cart, the mini-cart, the checkout, the product pages and order pages for the callbacks on the plugin hooks the fake fires', () => {
		assert.deepEqual( jobsFor( 'inc/Pixonaut/Integration.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [
				'buy-row',
				'cart',
				'checkout',
				'mini-cart',
				'order-received',
				'product-options',
				'product-page',
			],
			shards: 1,
		} );
	} );

	it( 'runs the order received page for what fills it in when it renders', () => {
		assert.deepEqual( jobsFor( 'inc/Orders/OrderReceivedBand.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [ 'order-received' ],
			shards: 1,
		} );
	} );

	it( 'runs the shop and the searches for what an archive without products shows', () => {
		assert.deepEqual( jobsFor( 'inc/Archive/ShownWhenEmpty.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [ 'search', 'shop' ],
			shards: 1,
		} );
	} );

	it( 'runs the header for what loads the script its search runs', () => {
		assert.deepEqual( jobsFor( 'inc/Header/HeaderSearch.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [ 'header' ],
			shards: 1,
		} );
	} );

	it( 'runs the product page and its options for the price row and the swatches in its add-to-cart form', () => {
		assert.deepEqual( jobsFor( 'inc/Product/PriceRow.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [ 'buy-row', 'product-options', 'product-page' ],
			shards: 1,
		} );
	} );

	it( "runs a pattern's own specs and the page specs for a pattern change", () => {
		const result = jobsFor( 'patterns/not-found.php' );

		assert.deepEqual(
			result.specs,
			ALL_SPECS.filter(
				( name ) => PAGE_SPECS.includes( name ) || PATTERN_SPECS[ 'not-found' ].includes( name ),
			),
		);
		assert.equal( result.shellScripts, false );
	} );

	for ( const path of [ 'templates/page.html', 'parts/header.html', 'languages/sv_SE.po' ] ) {
		it( `runs only the page specs for ${ path }`, () => {
			assert.deepEqual(
				jobsFor( path ).specs,
				ALL_SPECS.filter( ( name ) => PAGE_SPECS.includes( name ) ),
			);
		} );
	}

	for ( const path of [
		'src/app.css',
		'src/styles/_product.css',
		'theme.json',
		'styles/natur.json',
		'style.css',
		'assets/fonts/inter/inter-latin-400-normal.woff2',
	] ) {
		it( `runs only the specs that look at the pages for ${ path }`, () => {
			assert.deepEqual(
				jobsFor( path ).specs,
				ALL_SPECS.filter( ( name ) => LOOK_SPECS.includes( name ) ),
			);
		} );
	}

	for ( const path of [ 'src/app.css', 'src/styles/_cart.css' ] ) {
		it( `builds the stylesheet for a change to ${ path }`, () => {
			assert.equal( jobsFor( path ).js, true );
			assert.deepEqual( jobsFor( path ).unmatched, [] );
		} );
	}

	it( 'builds an editor script and opens the Site Editor for a change to it', () => {
		const result = jobsFor( 'src/editor/menu-heading.js' );

		assert.equal( result.js, true );
		assert.equal( result.php, false );
		assert.deepEqual(
			result.specs,
			ALL_SPECS.filter( ( name ) => EDITOR_SPECS.includes( name ) ),
		);
		assert.deepEqual( result.unmatched, [] );
	} );

	for ( const path of [ 'src/product/swatches.ts', 'src/product/swatch-choices.ts' ] ) {
		it( `builds the product page's script and opens its variable products for a change to ${ path }`, () => {
			const result = jobsFor( path );

			assert.equal( result.js, true );
			assert.equal( result.php, false );
			assert.deepEqual(
				result.specs,
				ALL_SPECS.filter( ( name ) => PRODUCT_SCRIPT_SPECS.includes( name ) ),
			);
			assert.deepEqual( result.unmatched, [] );
		} );
	}

	it( "builds the header's script and opens the header for a change to it", () => {
		const result = jobsFor( 'src/header/search.ts' );

		assert.equal( result.js, true );
		assert.equal( result.php, false );
		assert.deepEqual(
			result.specs,
			ALL_SPECS.filter( ( name ) => HEADER_SCRIPT_SPECS.includes( name ) ),
		);
		assert.deepEqual( result.unmatched, [] );
	} );

	it( 'runs the unit suite for a font file, which it checks theme.json names', () => {
		const result = jobsFor( 'assets/fonts/inter/OFL.txt' );

		assert.equal( result.php, true );
		assert.deepEqual( result.unmatched, [] );
	} );

	it( 'runs the cart, the mini-cart, the checkout, the product pages and order pages, integration and PHP checks for the fake plugin', () => {
		assert.deepEqual( jobsFor( 'tests/e2e/fake-pixonaut.php' ), {
			...NO_JOBS,
			php: true,
			integration: true,
			specs: [
				'buy-row',
				'cart',
				'checkout',
				'mini-cart',
				'order-received',
				'product-options',
				'product-page',
			],
			shards: 1,
		} );
	} );

	it( 'runs the whole e2e suite for a shared e2e helper', () => {
		const result = jobsFor( 'tests/e2e/support/wp-cli.ts' );

		assert.equal( result.js, true );
		assert.equal( result.php, false );
		assert.equal( result.wholeSuite, true );
		assert.deepEqual( result.unmatched, [] );
	} );

	it( 'runs the PHP checks, integration and the whole e2e suite for the shared fixtures', () => {
		const result = jobsFor( 'tests/Support/ShopFixtures.php' );

		assert.equal( result.php, true );
		assert.equal( result.integration, true );
		assert.equal( result.wholeSuite, true );
	} );

	it( "runs only the JavaScript job for CI's classifier and the release scripts", () => {
		assert.deepEqual(
			jobsFor(
				'scripts/ci/affected.ts',
				'scripts/ci/affected-local-cli.ts',
				'scripts/release/version.ts',
				'scripts/plugin/use.ts',
			),
			{ ...NO_JOBS, js: true },
		);
	} );

	it( 'runs the catalogue check for its own scripts', () => {
		assert.deepEqual( jobsFor( 'scripts/ci/pot-check-cli.ts' ), {
			...NO_JOBS,
			js: true,
			integration: true,
		} );
	} );

	it( 'runs only shellcheck for a Docker helper script', () => {
		assert.deepEqual( jobsFor( 'scripts/docker/host-user.sh' ), {
			...NO_JOBS,
			shellScripts: true,
		} );
	} );

	for ( const path of [
		'foo/bar.txt',
		'pnpm-lock.yaml',
		'composer.lock',
		'package.json',
		'.github/workflows/ci.yml',
		'.github/actions/wp-env/action.yml',
		'tests/e2e/global-setup.ts',
		'playwright.config.ts',
		'.wp-env.json',
		'webpack.config.js',
	] ) {
		it( `runs everything for ${ path }, and names it`, () => {
			assert.deepEqual( jobsFor( path ), { ...EVERY_JOB, unmatched: [ path ] } );
		} );
	}

	it( 'runs everything for an inc/ area the map does not know, and names it', () => {
		assert.deepEqual( jobsFor( 'inc/NewArea/Thing.php' ), {
			...EVERY_JOB,
			unmatched: [ 'inc/NewArea/Thing.php' ],
		} );
	} );

	it( 'names only the paths no rule knew', () => {
		assert.deepEqual( jobsFor( 'docs/x.md', 'package.json' ).unmatched, [ 'package.json' ] );
	} );

	it( 'runs everything for a spec whose name CI cannot pass on', () => {
		const path = 'tests/e2e/Has Space.spec.ts';

		assert.deepEqual( jobsFor( path ), { ...EVERY_JOB, unmatched: [ path ] } );
	} );

	it( 'runs the changed spec, and the tests that hold every spec to an owner', () => {
		assert.deepEqual( jobsFor( 'tests/e2e/not-found.spec.ts' ), {
			...NO_JOBS,
			js: true,
			specs: [ 'not-found' ],
			shards: 1,
		} );
	} );

	it( 'runs no spec for a spec that no longer exists', () => {
		assert.deepEqual( jobsFor( 'tests/e2e/removed.spec.ts' ), { ...NO_JOBS, js: true } );
	} );

	it( 'runs only the JavaScript job for a TypeScript test', () => {
		assert.deepEqual( jobsFor( 'tests/ts/ci-pot.test.ts' ), { ...NO_JOBS, js: true } );
	} );

	it( 'unions the jobs of every changed path', () => {
		assert.deepEqual( jobsFor( 'tests/e2e/not-found.spec.ts', 'docs/x.md', 'tests/Unit/X.php' ), {
			...NO_JOBS,
			php: true,
			js: true,
			specs: [ 'not-found' ],
			shards: 1,
		} );
	} );

	it( 'shards a selection of more than half a large suite', () => {
		const result = affected( LARGE_SUITE.slice( 0, 6 ).map( specPath ), LARGE_SUITE );

		assert.equal( result.wholeSuite, false );
		assert.equal( result.shards, LARGE_SELECTION_SHARDS );
	} );

	it( 'runs half a large suite or less in one job', () => {
		assert.equal( affected( LARGE_SUITE.slice( 0, 5 ).map( specPath ), LARGE_SUITE ).shards, 1 );
	} );

	it( 'runs a small suite in one job, even whole', () => {
		const result = affected( SMALL_SUITE.map( specPath ), SMALL_SUITE );

		assert.equal( result.wholeSuite, true );
		assert.equal( result.shards, 1 );
	} );
} );

describe( 'specName', () => {
	it( 'is the name of a spec directly in the e2e directory', () => {
		assert.equal( specName( 'tests/e2e/product-page.spec.ts' ), 'product-page' );
	} );

	for ( const path of [
		'tests/e2e/support/wp-cli.spec.ts',
		'tests/e2e/has space.spec.ts',
		'tests/e2e/two\nlines.spec.ts',
		'tests/e2e/global-setup.ts',
	] ) {
		it( `is nothing for ${ JSON.stringify( path ) }`, () => {
			assert.equal( specName( path ), null );
		} );
	}
} );

describe( 'jobOutputs', () => {
	it( 'names a selection of specs by their files', () => {
		assert.deepEqual( jobOutputs( affected( [ 'tests/e2e/a.spec.ts' ], SMALL_SUITE ) ), {
			php: 'false',
			js: 'true',
			shell_scripts: 'false',
			integration: 'false',
			e2e: 'true',
			e2e_specs: 'tests/e2e/a.spec.ts',
			e2e_shards: '[1]',
		} );
	} );

	it( 'names no spec for the whole suite, which Playwright runs without arguments', () => {
		const outputs = jobOutputs( everything( LARGE_SUITE ) );

		assert.equal( outputs.e2e, 'true' );
		assert.equal( outputs.e2e_specs, '' );
		assert.equal( outputs.e2e_shards, '[1,2]' );
	} );

	it( 'runs no e2e job without specs', () => {
		const outputs = jobOutputs( nothing() );

		assert.equal( outputs.e2e, 'false' );
		assert.equal( outputs.e2e_shards, '[]' );
	} );
} );

describe( 'localCommands', () => {
	it( 'runs nothing for a change that needs no job', () => {
		assert.deepEqual( localCommands( nothing() ), [] );
	} );

	it( "runs the php job's checks", () => {
		assert.deepEqual( localCommands( affected( [ 'tests/Unit/X.php' ], SMALL_SUITE ) ), [
			'pnpm fmt:php:check',
			'pnpm stan',
			'pnpm lint:php',
			'pnpm test:unit',
		] );
	} );

	it( 'builds before integration, then checks the catalogue', () => {
		assert.deepEqual( localCommands( affected( [ 'tests/Integration/XTest.php' ], SMALL_SUITE ) ), [
			'pnpm fmt:php:check',
			'pnpm stan',
			'pnpm lint:php',
			'pnpm test:unit',
			'pnpm build',
			'pnpm test:integration',
			'pnpm i18n:pot',
			'pnpm i18n:check',
		] );
	} );

	it( 'builds once when both the js job and the site need it, and names the specs', () => {
		assert.deepEqual( localCommands( affected( [ 'tests/e2e/a.spec.ts' ], SMALL_SUITE ) ), [
			'pnpm lint',
			'pnpm typecheck',
			'pnpm test:ts',
			'pnpm build',
			'pnpm test:e2e tests/e2e/a.spec.ts',
		] );
	} );

	it( 'runs the whole e2e suite without naming specs', () => {
		const commands = localCommands( everything( SMALL_SUITE ) );

		assert.equal( commands.at( -1 ), 'pnpm test:e2e' );
		assert.ok( commands.includes( 'pnpm lint:sh' ) );
		assert.equal( commands.filter( ( command ) => command === 'pnpm build' ).length, 1 );
	} );
} );

describe( 'githubOutput', () => {
	it( 'is one key=value line per output', () => {
		assert.equal( githubOutput( { php: 'true', e2e_specs: '' } ), 'php=true\ne2e_specs=\n' );
	} );

	it( 'refuses a value that would start another output', () => {
		assert.throws( () => githubOutput( { e2e_specs: 'x\nphp=false' } ) );
	} );
} );

describe( 'everything and nothing', () => {
	it( 'is every job and the whole suite', () => {
		assert.deepEqual( everything( ALL_SPECS ), EVERY_JOB );
	} );

	it( 'is no job at all', () => {
		assert.deepEqual( nothing(), NO_JOBS );
	} );
} );

describe( 'the e2e map', () => {
	// The theme's blocks' specs belong to `src/blocks/`, outside `inc/`.
	const ownedSpecs = [
		...BLOCK_SPECS,
		...Object.values( E2E_OWNERS ).flatMap( ( owned ) => ( owned === 'all' ? [] : owned ) ),
	];

	it( 'reads the directory Playwright runs', () => {
		assert.equal( resolve( E2E_DIR ), resolve( playwrightConfig.testDir ?? '' ) );
	} );

	it( 'has an entry for every entry in inc/', () => {
		const missing = readdirSync( 'inc' )
			.map( ( entry ) => `inc/${ entry }` )
			.filter( ( key ) => ! Object.hasOwn( E2E_OWNERS, key ) );

		assert.deepEqual( missing, [] );
	} );

	it( 'maps only patterns that exist', () => {
		const patterns = readdirSync( 'patterns' );
		const unknown = Object.keys( PATTERN_SPECS ).filter(
			( key ) => ! patterns.includes( `${ key }.php` ),
		);

		assert.deepEqual( unknown, [] );
	} );

	it( 'names only specs that exist in the pattern, page and look lists', () => {
		const unknown = [
			...Object.values( PATTERN_SPECS ).flat(),
			...PAGE_SPECS,
			...LOOK_SPECS,
		].filter( ( name ) => ! ALL_SPECS.includes( name ) );

		assert.deepEqual( unknown, [] );
	} );

	it( 'names only specs that exist', () => {
		const unknown = [ ...ownedSpecs, ...SITE_WIDE_SPECS ].filter(
			( name ) => ! ALL_SPECS.includes( name ),
		);

		assert.deepEqual( unknown, [] );
	} );

	it( 'gives every spec an owner or lists it as site-wide', () => {
		const unowned = ALL_SPECS.filter(
			( name ) => ! ownedSpecs.includes( name ) && ! SITE_WIDE_SPECS.includes( name ),
		);

		assert.deepEqual( unowned, [] );
	} );
} );

describe( 'fullRunPassed', () => {
	const SHA = 'a'.repeat( 40 );
	const RUN = {
		event: 'schedule',
		head_branch: 'main',
		head_sha: SHA,
		conclusion: 'success',
	};

	for ( const [ what, run ] of [
		[ 'a nightly run', RUN ],
		[ 'a manual run on main', { ...RUN, event: 'workflow_dispatch' } ],
	] as const ) {
		it( `is true after ${ what } passed this commit`, () => {
			assert.equal( fullRunPassed( { workflow_runs: [ run ] }, SHA ), true );
		} );
	}

	for ( const [ what, run ] of [
		[ 'another commit', { ...RUN, head_sha: 'b'.repeat( 40 ) } ],
		[ 'a failed run', { ...RUN, conclusion: 'failure' } ],
		[ 'a run still in progress', { ...RUN, conclusion: null } ],
		[ 'a manual run on another branch', { ...RUN, event: 'workflow_dispatch', head_branch: 'x' } ],
		[ 'a push run', { ...RUN, event: 'push' } ],
		[ 'a pull request run', { ...RUN, event: 'pull_request' } ],
	] as const ) {
		it( `is false for ${ what }`, () => {
			assert.equal( fullRunPassed( { workflow_runs: [ run ] }, SHA ), false );
		} );
	}

	for ( const [ what, body ] of [
		[ 'no runs', { workflow_runs: [] } ],
		[ 'an error body', { message: 'Bad credentials' } ],
		[ 'a listing that is not an array', { workflow_runs: {} } ],
		[ 'no body', null ],
	] as const ) {
		it( `is false, so everything runs, for ${ what }`, () => {
			assert.equal( fullRunPassed( body, SHA ), false );
		} );
	}
} );
