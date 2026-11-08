/**
 * The release zip comes in two flavors:
 *
 *   github  (default) updates itself from the repository's GitHub releases
 *           through plugin-update-checker, and is the zip attached to them
 *           that the updater downloads.
 *   wporg   for the wordpress.org theme directory, which delivers a listed
 *           theme's updates itself and allows no updater of its own: no
 *           plugin-update-checker, no `inc/Updates/`, no `Update URI` header.
 *           Nor does it ship the theme's own blocks, which the directory
 *           leaves to plugins; the patterns that hold them fall back without
 *           them (`inc/Blocks/ThemeBlocks.php`). It carries the unminified sources the build is made from, which
 *           the directory asks for.
 *
 * `assemble` stages either from a built checkout (after `pnpm build`, and for
 * the GitHub flavor `composer install --no-dev`); `stagedThemeProblems`
 * checks what was staged, so a file the allowlist lets through by mistake
 * fails the release instead of reaching a store.
 */

import { cpSync, existsSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { basename, join, relative, sep } from 'node:path';

export const FLAVORS = [ 'github', 'wporg' ] as const;

export type Flavor = ( typeof FLAVORS )[ number ];

export const DEFAULT_FLAVOR: Flavor = 'github';

/** The theme's directory name, fixed for good, and so the zip's top directory. */
export const SLUG = 'pixonaut-shop';

/** The update checker library, a Composer dependency of the GitHub flavor only. */
const UPDATER_PACKAGE = 'yahnis-elsts/plugin-update-checker';

/** The update checker's entry point, which the GitHub flavor's zip must hold. */
const UPDATER_ENTRY = `vendor/${ UPDATER_PACKAGE }/plugin-update-checker.php`;

/** The theme's own updater, which only the GitHub flavor ships. */
const UPDATER_SOURCES = 'inc/Updates';

/** The class the GitHub flavor's updater starts from, which its zip must hold. */
const UPDATER_CLASS = `${ UPDATER_SOURCES }/GitHubUpdater.php`;

/**
 * The theme's own blocks: their sources and build, and the PHP that
 * registers and renders them. Only the GitHub flavor ships them.
 */
const THEME_BLOCK_PATHS = [
	'src/blocks/',
	'build/blocks/',
	'inc/Blocks/BlockLoader.php',
	'inc/Blocks/FreeShippingNote.php',
	'inc/Blocks/ProductCategories.php',
	'inc/Blocks/GroupTiles.php',
	'inc/Blocks/CategoryCarousel.php',
	'inc/Blocks/CategoryLinks.php',
	'inc/Blocks/CategoryList.php',
	'inc/Blocks/Icons.php',
];

/** Text in any file of the wordpress.org flavor that means a block is registered. */
const BLOCK_REGISTRATION = /register_block_type/;

/** Text in any file of the wordpress.org flavor that means an updater was left in. */
const UPDATER_REMNANT = /PluginUpdateChecker|plugin-update-checker|PIXONAUT_SHOP_UPDATE_TOKEN/;

/** Files whose text is searched for an updater's remnant. */
const TEXT_FILE = /\.(php|js|ts|tsx|css|json|html|txt)$/;

const UPDATE_URI_LINE = /^[ \t]*Update URI:.*\r?\n/m;

/** Top-level files every flavor ships when the checkout has them. */
const THEME_FILES = [ 'style.css', 'theme.json', 'functions.php', 'index.php' ];

/** Top-level directories every flavor ships whole. */
const THEME_DIRECTORIES = [
	'assets',
	'inc',
	'parts',
	'patterns',
	'templates',
	'styles',
	'languages',
];

/** Shipped when there is one; the wordpress.org flavor requires both. */
const LISTING_FILES = [ 'readme.txt', 'screenshot.png' ];

/** What the wordpress.org flavor adds: the sources `build/` is compiled from, and how. */
const SOURCE_FILES = [ 'src', 'package.json', 'webpack.config.js' ];

/** What a block theme cannot work without. */
const REQUIRED_FILES = [ 'style.css', 'theme.json', 'templates/index.html' ];

/** Directories under vendor/ that only a package's own development needs. */
const VENDOR_DEVELOPMENT_DIRECTORIES = new Set( [
	'tests',
	'test',
	'docs',
	'doc',
	'examples',
	'.github',
] );

/** Files under vendor/ that only a package's own development needs. */
const VENDOR_DEVELOPMENT_FILE =
	/\.md$|^phpunit\.xml|^phpstan|^\.php-cs-fixer|^\.gitignore$|^\.gitattributes$|^\.editorconfig$|^Makefile$|\.dist$/;

/** Files the theme directory refuses, beside any hidden file or directory. */
const REFUSED_FILE = /\.(sh|map|zip)$/;

/**
 * The flavor named by `--flavor <name>` or `--flavor=<name>`, else the default.
 */
export function flavorFromArgs( args: readonly string[] ): Flavor {
	const index = args.findIndex( ( arg ) => arg === '--flavor' || arg.startsWith( '--flavor=' ) );

	if ( index === -1 ) {
		return DEFAULT_FLAVOR;
	}

	const name = args[ index ].includes( '=' )
		? args[ index ].slice( '--flavor='.length )
		: args[ index + 1 ];

	if ( ! ( FLAVORS as readonly string[] ).includes( name ) ) {
		throw new Error( `Unknown flavor "${ name }": use one of ${ FLAVORS.join( ', ' ) }.` );
	}

	return name as Flavor;
}

/** The arguments other than `--flavor` and its value. */
export function withoutFlavor( args: readonly string[] ): string[] {
	return args.filter(
		( arg, index ) =>
			arg !== '--flavor' && ! arg.startsWith( '--flavor=' ) && args[ index - 1 ] !== '--flavor',
	);
}

/**
 * The zip's file name. Only the GitHub flavor's matches the asset pattern the
 * theme's updater downloads (`GitHubUpdater::ASSET_PATTERN`), so a
 * wordpress.org zip attached to the same release is never offered to a store.
 */
export function zipName( version: string, flavor: Flavor ): string {
	return flavor === DEFAULT_FLAVOR
		? `${ SLUG }-${ version }.zip`
		: `${ SLUG }-${ version }-${ flavor }.zip`;
}

/** style.css without its `Update URI` header line. */
export function stripUpdateUri( stylesheet: string ): string {
	if ( ! UPDATE_URI_LINE.test( stylesheet ) ) {
		throw new Error( 'style.css has no Update URI line to strip.' );
	}

	return stylesheet.replace( UPDATE_URI_LINE, '' );
}

/** Every file under `root`, hidden ones too, as `/`-separated paths relative to it. */
export function listFiles( root: string ): Set< string > {
	const files = new Set< string >();

	for ( const entry of readdirSync( root, { recursive: true, withFileTypes: true } ) ) {
		if ( entry.isFile() ) {
			files.add( relative( root, join( entry.parentPath, entry.name ) ).split( sep ).join( '/' ) );
		}
	}

	return files;
}

/**
 * Stages the theme for a flavor at `<stage>/pixonaut-shop/` from a built
 * checkout, and returns that directory. Only what is named here is copied:
 * tests, docs, tooling and node_modules stay out by construction.
 */
export function assemble( checkout: string, stage: string, flavor: Flavor ): string {
	const theme = join( stage, SLUG );
	const copy = ( path: string ) =>
		cpSync( join( checkout, path ), join( theme, path ), { recursive: true } );

	if ( flavor !== DEFAULT_FLAVOR ) {
		for ( const path of LISTING_FILES ) {
			if ( ! existsSync( join( checkout, path ) ) ) {
				throw new Error( `The ${ flavor } flavor needs ${ path }.` );
			}
		}
	}

	rmSync( theme, { recursive: true, force: true } );

	for ( const path of [ ...THEME_FILES, ...LISTING_FILES ] ) {
		if ( existsSync( join( checkout, path ) ) ) {
			copy( path );
		}
	}

	for ( const path of THEME_DIRECTORIES ) {
		copy( path );
	}

	// build/ is generated; its source maps are for development only.
	cpSync( join( checkout, 'build' ), join( theme, 'build' ), {
		recursive: true,
		filter: ( source ) =>
			! source.endsWith( '.map' ) && basename( source ) !== '.php-cs-fixer.cache',
	} );

	if ( flavor === DEFAULT_FLAVOR ) {
		cpSync( join( checkout, 'vendor' ), join( theme, 'vendor' ), {
			recursive: true,
			filter: ( source ) =>
				! isVendorDevelopmentFile( relative( join( checkout, 'vendor' ), source ) ),
		} );

		return theme;
	}

	for ( const path of SOURCE_FILES ) {
		copy( path );
	}

	rmSync( join( theme, UPDATER_SOURCES ), { recursive: true, force: true } );

	for ( const path of THEME_BLOCK_PATHS ) {
		rmSync( join( theme, path ), { recursive: true, force: true } );
	}

	writeFileSync(
		join( theme, 'style.css' ),
		stripUpdateUri( readFileSync( join( theme, 'style.css' ), 'utf8' ) ),
	);

	return theme;
}

function isVendorDevelopmentFile( path: string ): boolean {
	const parts = path.split( sep );
	const name = parts[ parts.length - 1 ];

	return (
		parts.slice( 0, -1 ).some( ( part ) => VENDOR_DEVELOPMENT_DIRECTORIES.has( part ) ) ||
		VENDOR_DEVELOPMENT_DIRECTORIES.has( name ) ||
		VENDOR_DEVELOPMENT_FILE.test( name )
	);
}

/**
 * What is wrong with a staged (or unzipped) theme for a flavor, one line
 * each: a file no theme may ship, a file a block theme needs, and for the
 * GitHub flavor its updater missing, for the wordpress.org flavor any
 * remnant of it.
 */
export function stagedThemeProblems( theme: string, flavor: Flavor ): string[] {
	const files = listFiles( theme );
	const problems: string[] = [];
	const stylesheet = files.has( 'style.css' )
		? readFileSync( join( theme, 'style.css' ), 'utf8' )
		: '';
	const hasUpdateUri = UPDATE_URI_LINE.test( stylesheet );

	for ( const path of files ) {
		if (
			path.split( '/' ).some( ( part ) => part.startsWith( '.' ) ) ||
			REFUSED_FILE.test( path )
		) {
			problems.push( `${ path }: not allowed in a theme` );
		}
	}

	for ( const path of REQUIRED_FILES ) {
		if ( ! files.has( path ) ) {
			problems.push( `${ path }: missing` );
		}
	}

	if ( flavor === DEFAULT_FLAVOR ) {
		for ( const path of [ UPDATER_ENTRY, UPDATER_CLASS ] ) {
			if ( ! files.has( path ) ) {
				problems.push( `${ path }: missing` );
			}
		}

		if ( ! hasUpdateUri ) {
			problems.push( 'style.css: no Update URI header for the updater' );
		}

		return problems;
	}

	for ( const path of LISTING_FILES ) {
		if ( ! files.has( path ) ) {
			problems.push( `${ path }: missing` );
		}
	}

	if ( hasUpdateUri ) {
		problems.push( `style.css: Update URI header in the ${ flavor } flavor` );
	}

	for ( const path of files ) {
		if ( path.startsWith( 'vendor/' ) || path.startsWith( `${ UPDATER_SOURCES }/` ) ) {
			problems.push( `${ path }: update checker in the ${ flavor } flavor` );
		} else if ( THEME_BLOCK_PATHS.some( ( block ) => path.startsWith( block ) ) ) {
			problems.push( `${ path }: the theme's own block in the ${ flavor } flavor` );
		} else if (
			TEXT_FILE.test( path ) &&
			BLOCK_REGISTRATION.test( readFileSync( join( theme, path ), 'utf8' ) )
		) {
			problems.push( `${ path }: registers a block in the ${ flavor } flavor` );
		} else if (
			TEXT_FILE.test( path ) &&
			UPDATER_REMNANT.test( readFileSync( join( theme, path ), 'utf8' ) )
		) {
			problems.push( `${ path }: names the update checker in the ${ flavor } flavor` );
		}
	}

	return problems;
}
