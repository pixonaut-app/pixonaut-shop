/**
 * The release zip's two flavors: the GitHub one, which updates itself from
 * the repository's releases, and the wordpress.org one, which WordPress
 * updates from the theme directory and so ships no update checker. Run with
 * Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { afterEach, beforeEach, describe, it } from 'node:test';

import {
	assemble,
	DEFAULT_FLAVOR,
	flavorFromArgs,
	listFiles,
	stagedThemeProblems,
	stripUpdateUri,
	zipName,
} from '../../scripts/release/flavor.ts';

const STYLESHEET = [
	'/*',
	'Theme Name: Pixonaut Shop',
	'Version: 1.2.0',
	'Text Domain: pixonaut-shop',
	'Update URI: https://github.com/pixonaut-app/pixonaut-shop',
	'Tags: e-commerce',
	'*/',
	'',
].join( '\n' );

/** A checkout holding one of everything the allowlist names, and some it does not. */
const CHECKOUT_FILES: Record< string, string > = {
	'style.css': STYLESHEET,
	'theme.json': '{}',
	'functions.php': '<?php',
	'index.php': '<?php',
	'readme.txt': '=== Pixonaut Shop ===',
	'screenshot.png': 'png',
	'package.json': '{}',
	'webpack.config.js': '',
	'assets/fonts/inter/OFL.txt': 'OFL',
	'inc/Bootstrap.php': '<?php',
	'inc/Updates/Updates.php': '<?php // PIXONAUT_SHOP_UPDATE_TOKEN',
	'inc/Updates/GitHubUpdater.php': '<?php // YahnisElsts\\PluginUpdateChecker',
	'inc/Updates/GitHubReleases.php': '<?php',
	'parts/header.html': '',
	'patterns/hero.php': '<?php',
	'templates/index.html': '',
	'styles/natur.json': '{}',
	'languages/pixonaut-shop.pot': '',
	'src/app.css': '',
	'src/blocks/assets.d.ts': '',
	'src/blocks/icon/block.json': '{}',
	'src/header/search.ts': '',
	'build/blocks/icon/block.json': '{}',
	'inc/Blocks/BlockStyles.php': '<?php',
	'inc/Blocks/ThemeBlocks.php': '<?php',
	'inc/Blocks/BlockLoader.php': '<?php register_block_type( $dir );',
	'inc/Blocks/FreeShippingNote.php': '<?php',
	'inc/Blocks/ProductCategories.php': '<?php',
	'inc/Blocks/GroupTiles.php': '<?php',
	'inc/Blocks/CategoryCarousel.php': '<?php',
	'inc/Blocks/CategoryLinks.php': '<?php',
	'inc/Blocks/CategoryList.php': '<?php',
	'inc/Blocks/Icons.php': '<?php',
	'build/app.css': '',
	'build/app.css.map': '',
	'vendor/autoload.php': '<?php',
	'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php': '<?php',
	'vendor/yahnis-elsts/plugin-update-checker/README.md': '',
	'vendor/yahnis-elsts/plugin-update-checker/.gitattributes': '',
	'tests/bootstrap.php': '<?php',
	'docs/DEPLOY.md': '',
	'scripts/docker/host-user.sh': '',
	'.wp-env.json': '{}',
	'node_modules/x/index.js': '',
};

/** The theme's own blocks: their sources, build and the PHP that registers and renders them. */
const THEME_BLOCK_FILES = [
	'src/blocks/icon/block.json',
	'build/blocks/icon/block.json',
	'inc/Blocks/BlockLoader.php',
	'inc/Blocks/FreeShippingNote.php',
	'inc/Blocks/ProductCategories.php',
	'inc/Blocks/GroupTiles.php',
	'inc/Blocks/CategoryCarousel.php',
	'inc/Blocks/CategoryLinks.php',
	'inc/Blocks/CategoryList.php',
	'inc/Blocks/Icons.php',
];

const UPDATER_ENTRY = 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

describe( 'flavorFromArgs', () => {
	it( 'is the GitHub flavor when none is named', () => {
		assert.equal( flavorFromArgs( [ 'stage' ] ), DEFAULT_FLAVOR );
		assert.equal( DEFAULT_FLAVOR, 'github' );
	} );

	it( 'reads --flavor <name> and --flavor=<name>', () => {
		assert.equal( flavorFromArgs( [ '--flavor', 'wporg', 'stage' ] ), 'wporg' );
		assert.equal( flavorFromArgs( [ '--flavor=wporg' ] ), 'wporg' );
	} );

	it( 'refuses a flavor it does not know', () => {
		assert.throws( () => flavorFromArgs( [ '--flavor', 'svn' ] ), /Unknown flavor "svn"/ );
		assert.throws( () => flavorFromArgs( [ '--flavor' ] ), /Unknown flavor/ );
	} );
} );

describe( 'zipName', () => {
	it( "is the name the GitHub updater downloads for the GitHub flavor, and another name for wordpress.org's", () => {
		assert.equal( zipName( '1.2.0', 'github' ), 'pixonaut-shop-1.2.0.zip' );
		assert.equal( zipName( '1.2.0', 'wporg' ), 'pixonaut-shop-1.2.0-wporg.zip' );
	} );

	it( "is never matched by the GitHub updater's asset pattern for the wordpress.org flavor", () => {
		const updater = readFileSync( 'inc/Updates/GitHubUpdater.php', 'utf8' );
		const match = /ASSET_PATTERN = '\/(.+)\/';/.exec( updater );

		assert.ok( match, 'GitHubUpdater declares no ASSET_PATTERN' );

		const pattern = new RegExp( match[ 1 ] );

		assert.match( zipName( '1.2.0', 'github' ), pattern );
		assert.doesNotMatch( zipName( '1.2.0', 'wporg' ), pattern );
		assert.doesNotMatch( `${ zipName( '1.2.0', 'wporg' ) }.sha256`, pattern );
	} );
} );

describe( 'stripUpdateUri', () => {
	it( 'removes the Update URI header line and nothing else', () => {
		const stripped = stripUpdateUri( STYLESHEET );

		assert.doesNotMatch( stripped, /Update URI/ );
		assert.equal( stripped, STYLESHEET.replace( /^Update URI:.*\n/m, '' ) );
	} );

	it( 'refuses a stylesheet without one, so a renamed header cannot slip through', () => {
		assert.throws( () => stripUpdateUri( '/*\nTheme Name: X\n*/\n' ), /no Update URI/ );
	} );

	it( "finds the theme's own Update URI header", () => {
		assert.doesNotMatch( stripUpdateUri( readFileSync( 'style.css', 'utf8' ) ), /Update URI/ );
	} );
} );

describe( 'assemble', () => {
	let checkout: string;
	let stage: string;

	beforeEach( () => {
		checkout = mkdtempSync( join( tmpdir(), 'flavor-checkout-' ) );
		stage = mkdtempSync( join( tmpdir(), 'flavor-stage-' ) );

		for ( const [ path, content ] of Object.entries( CHECKOUT_FILES ) ) {
			mkdirSync( dirname( join( checkout, path ) ), { recursive: true } );
			writeFileSync( join( checkout, path ), content );
		}
	} );

	afterEach( () => {
		rmSync( checkout, { recursive: true, force: true } );
		rmSync( stage, { recursive: true, force: true } );
	} );

	it( 'stages the GitHub flavor with the update checker, and without development files', () => {
		const theme = assemble( checkout, stage, 'github' );
		const files = listFiles( theme );

		assert.equal( theme, join( stage, 'pixonaut-shop' ) );
		assert.ok( files.has( UPDATER_ENTRY ) );
		assert.ok( files.has( 'inc/Updates/GitHubUpdater.php' ) );
		assert.ok( files.has( 'build/app.css' ) );
		assert.match( readFileSync( join( theme, 'style.css' ), 'utf8' ), /Update URI/ );

		for ( const left of [
			'build/app.css.map',
			'vendor/yahnis-elsts/plugin-update-checker/README.md',
			'vendor/yahnis-elsts/plugin-update-checker/.gitattributes',
			'tests/bootstrap.php',
			'docs/DEPLOY.md',
			'scripts/docker/host-user.sh',
			'.wp-env.json',
			'node_modules/x/index.js',
			'src/app.css',
		] ) {
			assert.ok( ! files.has( left ), `${ left } is in the GitHub flavor` );
		}

		assert.deepEqual( stagedThemeProblems( theme, 'github' ), [] );
	} );

	it( 'stages the wordpress.org flavor without any of the update checker', () => {
		const theme = assemble( checkout, stage, 'wporg' );
		const files = listFiles( theme );

		assert.ok( ! [ ...files ].some( ( path ) => path.startsWith( 'vendor/' ) ) );
		assert.ok( ! [ ...files ].some( ( path ) => path.startsWith( 'inc/Updates/' ) ) );
		assert.doesNotMatch( readFileSync( join( theme, 'style.css' ), 'utf8' ), /Update URI/ );
		assert.deepEqual( stagedThemeProblems( theme, 'wporg' ), [] );
	} );

	it( "stages the GitHub flavor with the theme's own blocks", () => {
		const files = listFiles( assemble( checkout, stage, 'github' ) );

		for ( const kept of THEME_BLOCK_FILES.filter( ( path ) => ! path.startsWith( 'src/' ) ) ) {
			assert.ok( files.has( kept ), `${ kept } is missing from the GitHub flavor` );
		}
	} );

	it( "stages the wordpress.org flavor without the theme's own blocks, which are plugin territory there", () => {
		const theme = assemble( checkout, stage, 'wporg' );
		const files = listFiles( theme );

		for ( const left of [ ...THEME_BLOCK_FILES, 'src/blocks/assets.d.ts' ] ) {
			assert.ok( ! files.has( left ), `${ left } is in the wordpress.org flavor` );
		}

		assert.ok( files.has( 'inc/Blocks/BlockStyles.php' ) );
		assert.ok( files.has( 'inc/Blocks/ThemeBlocks.php' ) );
		assert.deepEqual( stagedThemeProblems( theme, 'wporg' ), [] );
	} );

	it( 'ships the unminified sources, the readme and the screenshot in the wordpress.org flavor', () => {
		const files = listFiles( assemble( checkout, stage, 'wporg' ) );

		for ( const kept of [
			'readme.txt',
			'screenshot.png',
			'src/app.css',
			'src/header/search.ts',
			'package.json',
			'webpack.config.js',
			'assets/fonts/inter/OFL.txt',
		] ) {
			assert.ok( files.has( kept ), `${ kept } is missing from the wordpress.org flavor` );
		}
	} );

	it( 'refuses to stage the wordpress.org flavor without its readme or screenshot', () => {
		rmSync( join( checkout, 'screenshot.png' ) );

		assert.throws( () => assemble( checkout, stage, 'wporg' ), /screenshot\.png/ );
	} );
} );

describe( 'stagedThemeProblems', () => {
	let theme: string;

	function put( path: string, content = '' ): void {
		mkdirSync( dirname( join( theme, path ) ), { recursive: true } );
		writeFileSync( join( theme, path ), content );
	}

	beforeEach( () => {
		theme = mkdtempSync( join( tmpdir(), 'flavor-theme-' ) );
		put( 'style.css', stripUpdateUri( STYLESHEET ) );
		put( 'readme.txt' );
		put( 'screenshot.png' );
		put( 'templates/index.html' );
		put( 'theme.json', '{}' );
		put( 'inc/Bootstrap.php', '<?php' );
	} );

	afterEach( () => {
		rmSync( theme, { recursive: true, force: true } );
	} );

	it( 'passes a clean wordpress.org theme', () => {
		assert.deepEqual( stagedThemeProblems( theme, 'wporg' ), [] );
	} );

	for ( const [ remnant, path, content ] of [
		[ 'the update checker library', UPDATER_ENTRY, '<?php' ],
		[ "the theme's updater", 'inc/Updates/GitHubUpdater.php', '<?php' ],
		[ 'a vendor directory', 'vendor/autoload.php', '<?php' ],
		[
			"the library's namespace in any file",
			'inc/Other.php',
			'<?php use YahnisElsts\\PluginUpdateChecker;',
		],
		[ "the library's name in any file", 'build/x.js', '// plugin-update-checker' ],
		[
			"the update token's constant",
			'functions.php',
			"<?php define('PIXONAUT_SHOP_UPDATE_TOKEN', 'x');",
		],
		[ 'a block registered by the theme', 'inc/Other.php', '<?php register_block_type( __DIR__ );' ],
		[ "a block's build", 'build/blocks/icon/block.json', '{}' ],
		[ "a block's sources", 'src/blocks/icon/block.json', '{}' ],
	] as const ) {
		it( `fails the wordpress.org flavor on ${ remnant }`, () => {
			put( path, content );

			assert.ok(
				stagedThemeProblems( theme, 'wporg' ).some( ( problem ) => problem.startsWith( path ) ),
				`${ path } was not reported`,
			);
		} );
	}

	it( 'fails the wordpress.org flavor on an Update URI header', () => {
		put( 'style.css', STYLESHEET );

		assert.ok( stagedThemeProblems( theme, 'wporg' ).some( ( p ) => p.includes( 'Update URI' ) ) );
	} );

	it( 'fails the wordpress.org flavor without its readme or screenshot', () => {
		rmSync( join( theme, 'readme.txt' ) );
		rmSync( join( theme, 'screenshot.png' ) );

		const problems = stagedThemeProblems( theme, 'wporg' );

		assert.ok( problems.includes( 'readme.txt: missing' ) );
		assert.ok( problems.includes( 'screenshot.png: missing' ) );
	} );

	it( 'fails the GitHub flavor without its update checker', () => {
		const problems = stagedThemeProblems( theme, 'github' );

		assert.ok( problems.includes( `${ UPDATER_ENTRY }: missing` ) );
		assert.ok( problems.includes( 'inc/Updates/GitHubUpdater.php: missing' ) );
		assert.ok( problems.some( ( p ) => p.includes( 'Update URI' ) ) );
	} );

	for ( const [ kind, path ] of [
		[ 'a hidden file', 'inc/.DS_Store' ],
		[ 'a shell script', 'bin/run.sh' ],
		[ 'a source map', 'build/app.js.map' ],
		[ 'a zip', 'assets/x.zip' ],
		[ 'a PHP-CS-Fixer cache', 'build/.php-cs-fixer.cache' ],
	] as const ) {
		it( `fails either flavor on ${ kind }`, () => {
			put( path );

			assert.ok( stagedThemeProblems( theme, 'wporg' ).some( ( p ) => p.startsWith( path ) ) );
		} );
	}
} );
