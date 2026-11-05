/**
 * `pnpm plugin:use` and `pnpm plugin:api`: choose the build of Pixonaut for
 * WooCommerce the development site runs, and the Pixonaut it talks to, by
 * writing the gitignored `.wp-env.override.json`. Everything outside
 * this process — `gh`, `unzip`, git — comes in through `Deps`, so the tests
 * run it against a fake release with no network.
 */

import { createHash } from 'node:crypto';
import {
	existsSync,
	mkdirSync,
	mkdtempSync,
	readdirSync,
	readFileSync,
	renameSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { isAbsolute, join, resolve } from 'node:path';

import {
	checkoutProblems,
	checksumMatches,
	checksumName,
	defaultCheckout,
	mainFile,
	mappedPlugins,
	type Override,
	PIXONAUT_WC,
	type PixonautAddresses,
	PLUGINS,
	type Plugin,
	parseSource,
	pluginNamed,
	type Release,
	releaseZip,
	sharedPluginBuilds,
	withPixonaut,
	withPluginSource,
} from './source.ts';

/** wp-env's own configuration and the per-machine file merged over it. */
export const WP_ENV_CONFIG = '.wp-env.json';
export const OVERRIDE_FILE = '.wp-env.override.json';

/** Where downloaded releases are unpacked, one directory per tag; gitignored. */
export const RELEASES_DIR = '.wp-env-plugins';

/** What `gh` says when a repository has no such release, or none at all. */
const RELEASE_NOT_FOUND = /release not found/i;

const RESTART = 'Restart wp-env for it to take effect: run `pnpm env:start`.';

export interface Deps {
	/** The theme checkout the command runs in. */
	readonly cwd: string;
	readonly env: Readonly< Record< string, string | undefined > >;
	/** This repository's main checkout, beside which each plugin's is looked for. */
	readonly themeMainCheckout: string;
	/** Runs `gh` and returns what it printed; throws with its error output when it fails. */
	readonly gh: ( args: readonly string[] ) => string;
	/** Unpacks a zip into a directory. */
	readonly unzip: ( zip: string, directory: string ) => void;
}

export function readOverride( cwd: string ): Override {
	const path = join( cwd, OVERRIDE_FILE );

	return existsSync( path ) ? ( JSON.parse( readFileSync( path, 'utf8' ) ) as Override ) : {};
}

function writeOverride( cwd: string, override: Override ): void {
	writeFileSync( join( cwd, OVERRIDE_FILE ), `${ JSON.stringify( override, null, 2 ) }\n` );
}

/** `.wp-env.json`'s plugins, which the development site keeps beside the chosen build. */
function basePlugins( cwd: string ): string[] {
	const config = JSON.parse( readFileSync( join( cwd, WP_ENV_CONFIG ), 'utf8' ) ) as {
		plugins?: unknown;
	};

	return Array.isArray( config.plugins ) ? config.plugins.map( String ) : [];
}

function errorText( error: unknown ): string {
	return error instanceof Error ? error.message : String( error );
}

/** The release `tag`, or the latest one for `null`, as `gh` describes it. */
function viewRelease( plugin: Plugin, tag: string | null, deps: Deps ): Release {
	const { repository } = plugin;
	const which = tag ?? 'latest release';

	try {
		return JSON.parse(
			deps.gh( [
				'release',
				'view',
				...( tag === null ? [] : [ tag ] ),
				'--repo',
				repository,
				'--json',
				'tagName,assets',
			] ),
		) as Release;
	} catch ( error ) {
		if ( RELEASE_NOT_FOUND.test( errorText( error ) ) ) {
			throw new Error(
				tag === null
					? `${ repository } has no releases yet. Use a checkout instead: ${ plugin.command } main`
					: `${ repository } has no release ${ tag }. Its releases: gh release list --repo ${ repository }`,
			);
		}

		throw new Error(
			`gh could not read ${ repository }'s ${ which }: ${ errorText( error ).trim() }. Check you are signed in with access to it: gh auth status`,
		);
	}
}

/**
 * Downloads a release's zip, checks it against its checksum, and unpacks it
 * under `RELEASES_DIR`; returns the unpacked plugin's directory. A tag already
 * unpacked is used as it is.
 */
function installRelease(
	plugin: Plugin,
	tag: string | null,
	deps: Deps,
): { path: string; tag: string } {
	const release = viewRelease( plugin, tag, deps );
	const zip = releaseZip( plugin, release );

	if ( zip === null ) {
		throw new Error(
			`${ release.tagName } has no ${ plugin.slug } zip attached yet; its release workflow may still be running. Try again shortly, or pick another release.`,
		);
	}

	const checksum = checksumName( zip );

	if ( ! release.assets.some( ( asset ) => asset.name === checksum ) ) {
		throw new Error(
			`${ release.tagName } has ${ zip } but no checksum attached (${ checksum }), so it cannot be verified; its release workflow may still be running. Try again shortly, or pick another release.`,
		);
	}

	const releases = join( deps.cwd, RELEASES_DIR );
	const directory = join( releases, release.tagName );
	const unpacked = join( directory, plugin.slug );

	if ( existsSync( join( unpacked, mainFile( plugin ) ) ) ) {
		return { path: unpacked, tag: release.tagName };
	}

	// Everything happens in a staging directory that is renamed into place only
	// once the plugin is verified and unpacked, so an interrupted run never
	// leaves a directory the next one would take for a finished install.
	mkdirSync( releases, { recursive: true } );
	const staging = mkdtempSync( join( releases, `.${ release.tagName }-` ) );

	try {
		unpackRelease( plugin, release.tagName, zip, checksum, staging, deps );
		rmSync( directory, { recursive: true, force: true } );
		renameSync( staging, directory );
	} finally {
		rmSync( staging, { recursive: true, force: true } );
	}

	return { path: unpacked, tag: release.tagName };
}

/** Downloads a release's zip and checksum into `directory`, verifies the zip and unpacks it there. */
function unpackRelease(
	plugin: Plugin,
	tag: string,
	zip: string,
	checksum: string,
	directory: string,
	deps: Deps,
): void {
	deps.gh( [
		'release',
		'download',
		tag,
		'--repo',
		plugin.repository,
		'--pattern',
		zip,
		'--pattern',
		checksum,
		'--dir',
		directory,
		'--clobber',
	] );

	const zipPath = join( directory, zip );
	const checksumPath = join( directory, checksum );

	if ( ! existsSync( checksumPath ) ) {
		throw new Error( `${ checksum } did not download, so ${ zip } cannot be verified; try again.` );
	}

	const digest = createHash( 'sha256' ).update( readFileSync( zipPath ) ).digest( 'hex' );

	if ( ! checksumMatches( readFileSync( checksumPath, 'utf8' ), digest ) ) {
		throw new Error( `${ zip } does not match its checksum; download it again.` );
	}

	deps.unzip( zipPath, directory );

	if ( ! existsSync( join( directory, plugin.slug, mainFile( plugin ) ) ) ) {
		throw new Error( `${ zip } holds no ${ plugin.slug }/ directory with the plugin in it.` );
	}
}

/** The files in a directory, or none when it does not exist. */
function filesIn( directory: string ): string[] {
	return existsSync( directory ) ? readdirSync( directory ) : [];
}

/** `text` with its first letter capitalised, to start a sentence with a plugin's name. */
function capitalised( text: string ): string {
	return text.charAt( 0 ).toUpperCase() + text.slice( 1 );
}

/**
 * The plugin a development-site plugin entry is a build of: the one whose main
 * file it holds, or, for a zip, a URL or a checkout no longer there, the one
 * it is named after.
 */
export function buildOf( cwd: string ): ( entry: string ) => Plugin | null {
	return ( entry ) => {
		const path = isAbsolute( entry ) ? entry : resolve( cwd, entry );

		return (
			PLUGINS.find( ( plugin ) => existsSync( join( path, mainFile( plugin ) ) ) ) ??
			pluginNamed( entry )
		);
	};
}

/**
 * Points the development site at the build of `plugin` that `arg` names and
 * returns what to tell the developer. Throws, with what to do, when the build
 * cannot run.
 */
export function usePlugin( plugin: Plugin, arg: string, deps: Deps ): string {
	const source = parseSource(
		plugin,
		arg,
		defaultCheckout( plugin, deps.env, deps.themeMainCheckout ),
		deps.cwd,
	);
	const override = readOverride( deps.cwd );
	const shared = sharedPluginBuilds( override, buildOf( deps.cwd ) );

	if ( shared.length > 0 ) {
		throw new Error(
			shared
				.map(
					( entry ) =>
						`Remove ${ entry } from the top-level "plugins" in ${ OVERRIDE_FILE }: wp-env would load it on the tests site too, and on the development site whatever you choose here. Then run this again.`,
				)
				.join( '\n' ),
		);
	}

	let pluginPath: string | null = null;
	let described: string;

	switch ( source.kind ) {
		case 'none':
			described =
				plugin === PIXONAUT_WC
					? 'none on the development site; the tests site keeps its fake'
					: 'none on the development site';
			break;
		case 'checkout': {
			const problems = checkoutProblems(
				plugin,
				source.path,
				( relative ) => existsSync( join( source.path, relative ) ),
				filesIn( join( source.path, 'assets/build' ) ),
			);

			if ( problems.length > 0 ) {
				throw new Error( problems.join( '\n' ) );
			}

			pluginPath = source.path;
			described = `the checkout at ${ source.path }`;
			break;
		}
		case 'release': {
			const installed = installRelease( plugin, source.tag, deps );
			pluginPath = installed.path;
			described = `release ${ installed.tag }`;
			break;
		}
	}

	const updated = withPluginSource(
		override,
		basePlugins( deps.cwd ),
		plugin,
		pluginPath,
		buildOf( deps.cwd ),
	);

	writeOverride( deps.cwd, updated );

	const mapped = mappedPlugins( updated ).includes( plugin )
		? ` It is mounted as wp-content/plugins/${ plugin.slug }, which wp-env does not activate: \`pnpm env:activate\` does.`
		: '';

	return `${ capitalised( plugin.name ) }: ${ described }. ${ RESTART }${ mapped }`;
}

/**
 * Points the development site's plugin at a Pixonaut, or back at its own
 * default for `default`, and returns what to tell the developer.
 */
export function usePixonaut( args: readonly string[], cwd: string ): string {
	const [ api, studio ] = args;

	if ( api === undefined ) {
		throw new Error(
			'Name the API: pnpm plugin:api <api-url> [studio-url], or pnpm plugin:api default',
		);
	}

	const addresses: PixonautAddresses | null =
		api === 'default' ? null : { api, studio: studio ?? null };

	writeOverride( cwd, withPixonaut( readOverride( cwd ), addresses ) );

	if ( addresses === null ) {
		return `The development site's plugin uses its own Pixonaut addresses again. ${ RESTART }`;
	}

	const studioNote =
		addresses.studio === null
			? " No studio given: connecting a store goes to the plugin's default studio."
			: '';

	return `The development site's plugin talks to the Pixonaut API at ${ api }.${ studioNote } ${ RESTART }`;
}
