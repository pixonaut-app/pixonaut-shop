/**
 * Which build of Pixonaut for WooCommerce the development site runs, and the
 * wp-env override that says so. Pure: `use.ts` does the reading, downloading and writing.
 */

import { basename, dirname, isAbsolute, join, resolve } from 'node:path';

/** A plugin the development site can run, chosen by `pnpm plugin:use`. */
export interface Plugin {
	/** Its name in what the commands print. */
	readonly name: string;
	/** Its directory, its main file's name, and its release zip's prefix. */
	readonly slug: string;
	/** Its repository, whose releases `v1.2.3` and `latest` install. */
	readonly repository: string;
	/**
	 * The environment variable naming its main checkout, for `main`. Without
	 * it, the plugin is looked for beside this repository's main checkout.
	 */
	readonly checkoutEnv: string;
	/** The argument that takes it off the development site. */
	readonly none: string;
	/** The command that chooses its build, for what the commands print. */
	readonly command: string;
	/** The Composer autoloader its main file loads first, relative to its root. */
	readonly autoloader: string;
}

export const PIXONAUT_WC: Plugin = {
	name: 'Pixonaut for WooCommerce',
	slug: 'pixonaut-for-woocommerce',
	repository: 'pixonaut-app/pixonaut-for-woocommerce',
	checkoutEnv: 'PIXONAUT_WC_CHECKOUT',
	// The tests site always runs the fake; `fake` takes the real one off the development site.
	none: 'fake',
	command: 'pnpm plugin:use',
	autoloader: 'vendor/autoload.php',
};

/** The plugins the development site can run, in the order it lists them after `.wp-env.json`'s. */
export const PLUGINS: readonly Plugin[] = [ PIXONAUT_WC ];

/** A release tag the plugin's release workflow creates: `v1.2.3` or `v1.2.3-beta.4`. */
const RELEASE_TAG = /^v\d+\.\d+\.\d+(?:-beta\.\d+)?$/;

/** Something that looks meant as a tag, so a typo is not taken for a path. */
const TAG_LIKE = /^v\d/;

/** Where a plugin keeps its built assets, relative to its root. */
const BUILD_DIR = 'assets/build';
/** A plugin's asset sources; one without them has nothing to build. */
const SOURCE_DIR = 'assets/src';

/** A built entry point's dependency file, which the plugin reads before enqueuing it. */
const BUILT_ASSET = /\.asset\.php$/;

/** The plugin's constants for where Pixonaut is (its `Api\Hosts`). */
export const API_CONSTANTS = {
	/** The API as the browser reaches it. */
	api: 'PIXONAUT_WC_API_BASE',
	/** The API as WordPress reaches it, from inside Docker. */
	serverApi: 'PIXONAUT_WC_SERVER_API_BASE',
	/** The studio, where a store is connected. */
	studio: 'PIXONAUT_WC_STUDIO_BASE',
} as const;

/** How a container reaches the host; wp-env maps this name on every platform. */
const DOCKER_HOST = 'host.docker.internal';

/** Host names that mean "this machine" to a browser but "the container" to WordPress. */
const LOOPBACK_HOSTS = [ 'localhost', '127.0.0.1', '[::1]' ];

/** A plugin's main file, relative to its root. */
export function mainFile( plugin: Plugin ): string {
	return `${ plugin.slug }.php`;
}

export type PluginSource =
	| { readonly kind: 'none' }
	| { readonly kind: 'checkout'; readonly path: string }
	| { readonly kind: 'release'; readonly tag: string | null };

/**
 * What `pnpm plugin:use <arg>` asks for: the plugin's `none` (`fake` for
 * Pixonaut for WooCommerce), `main` (the main checkout), a path to any
 * checkout or worktree, a release tag, or `latest`.
 */
export function parseSource(
	plugin: Plugin,
	arg: string,
	mainCheckout: string,
	cwd: string,
): PluginSource {
	if ( arg === plugin.none ) {
		return { kind: 'none' };
	}

	if ( arg === 'main' ) {
		return { kind: 'checkout', path: mainCheckout };
	}

	if ( arg === 'latest' ) {
		return { kind: 'release', tag: null };
	}

	if ( RELEASE_TAG.test( arg ) ) {
		return { kind: 'release', tag: arg };
	}

	if ( TAG_LIKE.test( arg ) ) {
		throw new Error(
			`${ JSON.stringify( arg ) } is not a release tag (v1.2.3 or v1.2.3-beta.4). To use a checkout by that name, write it as a path: ./${ arg }`,
		);
	}

	return { kind: 'checkout', path: isAbsolute( arg ) ? arg : resolve( cwd, arg ) };
}

/**
 * Where a plugin's main checkout is: its `checkoutEnv` when set, or its slug
 * beside this repository's main checkout.
 */
export function defaultCheckout(
	plugin: Plugin,
	env: Readonly< Record< string, string | undefined > >,
	themeMainCheckout: string,
): string {
	const configured = env[ plugin.checkoutEnv ];

	return configured !== undefined && configured !== ''
		? configured
		: join( dirname( themeMainCheckout ), plugin.slug );
}

/**
 * Why a checkout cannot run on the site yet, each with how to fix it; none
 * when it can.
 *
 * @param exists     Whether a path relative to the checkout exists.
 * @param buildFiles The files in its `assets/build/`, empty when there is none.
 */
export function checkoutProblems(
	plugin: Plugin,
	path: string,
	exists: ( relative: string ) => boolean,
	buildFiles: readonly string[],
): string[] {
	if ( ! exists( mainFile( plugin ) ) ) {
		return [
			`${ path } is not a checkout of ${ plugin.name }: it has no ${ mainFile( plugin ) }. Set ${ plugin.checkoutEnv } to its main checkout, or pass its path.`,
		];
	}

	const problems: string[] = [];

	if ( ! exists( plugin.autoloader ) ) {
		problems.push(
			`${ path } has no ${ plugin.autoloader }, which the plugin loads first. In it, run: pnpm composer install`,
		);
	}

	if ( exists( SOURCE_DIR ) && ! buildFiles.some( ( file ) => BUILT_ASSET.test( file ) ) ) {
		problems.push(
			`${ path } has no built assets in ${ BUILD_DIR }/. In it, run: pnpm install && pnpm build`,
		);
	}

	return problems;
}

/** The zip a plugin's release workflow attaches to a release. */
export function releaseZipName( plugin: Plugin, tag: string ): string {
	return `${ plugin.slug }-${ tag.replace( /^v/, '' ) }.zip`;
}

/** The checksum file the release workflow attaches beside a zip. */
export function checksumName( zip: string ): string {
	return `${ zip }.sha256`;
}

/** A release as `gh release view --json tagName,assets` prints it. */
export interface Release {
	readonly tagName: string;
	readonly assets: readonly { readonly name: string }[];
}

/** The release's own zip, or `null` while it is not attached. */
export function releaseZip( plugin: Plugin, release: Release ): string | null {
	const name = releaseZipName( plugin, release.tagName );

	return release.assets.some( ( asset ) => asset.name === name ) ? name : null;
}

/**
 * Whether a checksum file (`<sha256>  <name>`, as `sha256sum` writes it)
 * matches a digest.
 */
export function checksumMatches( checksumFile: string, sha256: string ): boolean {
	const [ expected ] = checksumFile.trim().split( /\s+/ );

	return expected?.toLowerCase() === sha256.toLowerCase();
}

/** A wp-env override, as JSON. */
export type Override = Record< string, unknown >;

function object( value: unknown ): Record< string, unknown > {
	return typeof value === 'object' && value !== null && ! Array.isArray( value )
		? { ...( value as Record< string, unknown > ) }
		: {};
}

/** The override with `env.development[ key ]` set to `value`, or removed for `undefined`. */
function withDevelopment( override: Override, key: string, value: unknown ): Override {
	const env = object( override.env );
	const development = object( env.development );

	if ( value === undefined ) {
		delete development[ key ];
	} else {
		development[ key ] = value;
	}

	if ( Object.keys( development ).length > 0 ) {
		env.development = development;
	} else {
		delete env.development;
	}

	const updated = { ...override, env };

	if ( Object.keys( env ).length === 0 ) {
		delete ( updated as Record< string, unknown > ).env;
	}

	return updated;
}

/** A plugin entry's last path segment: a directory or zip named after a plugin. */
function buildPattern( plugin: Plugin ): RegExp {
	return new RegExp( `(?:^|/)${ plugin.slug }(?:-[^/]*)?(?:\\.zip)?/*$` );
}

/**
 * The plugin a wp-env plugin entry is a build of, judged by its name alone.
 * `null` for any other plugin.
 */
export function pluginNamed( entry: string ): Plugin | null {
	return PLUGINS.find( ( plugin ) => buildPattern( plugin ).test( entry ) ) ?? null;
}

/** Where wp-env mounts a plugin it is given in `mappings`. */
export function pluginMapping( plugin: Plugin ): string {
	return `wp-content/plugins/${ plugin.slug }`;
}

/**
 * Whether a build at `path` keeps its plugin's slug in wp-env's `plugins`,
 * where wp-env names a plugin after its directory. One that does not (a
 * worktree) would be a different plugin to WordPress.
 */
function keepsSlug( plugin: Plugin, path: string ): boolean {
	return basename( path.replace( /\/+$/, '' ) ) === plugin.slug;
}

/**
 * The override with `plugin`'s build on the development site replaced by
 * `pluginPath`, or removed with `null`; everything else in it is kept.
 *
 * Its `plugins` are `.wp-env.json`'s, then any other plugin already there,
 * then each plugin's build in `PLUGINS` order; `buildOf` says which plugin an
 * entry already there is a build of. A build that would not keep its slug
 * there is mounted under it in `mappings` instead, which `pnpm env:activate`
 * activates. With nothing left, the development site goes back to
 * `.wp-env.json`'s plugins alone, where the tests site's fake is the only
 * Pixonaut for WooCommerce.
 */
export function withPluginSource(
	override: Override,
	basePlugins: readonly string[],
	plugin: Plugin,
	pluginPath: string | null,
	buildOf: ( entry: string ) => Plugin | null,
): Override {
	const development = object( object( override.env ).development );
	const entries = Array.isArray( development.plugins )
		? development.plugins.map( String ).filter( ( entry ) => ! basePlugins.includes( entry ) )
		: [];
	const mappings = object( development.mappings );
	const builds = new Map< Plugin, string >();
	const others: string[] = [];

	for ( const entry of entries ) {
		const owner = buildOf( entry );

		if ( owner === null ) {
			others.push( entry );
		} else {
			builds.set( owner, entry );
		}
	}

	for ( const each of PLUGINS ) {
		const mapped = mappings[ pluginMapping( each ) ];

		if ( mapped !== undefined ) {
			builds.set( each, String( mapped ) );
			delete mappings[ pluginMapping( each ) ];
		}
	}

	builds.delete( plugin );

	if ( pluginPath !== null ) {
		builds.set( plugin, pluginPath );
	}

	const listed: string[] = [];
	let mapping = false;

	for ( const each of PLUGINS ) {
		const build = builds.get( each );

		if ( build === undefined ) {
			continue;
		}

		mapping = mapping || ! keepsSlug( each, build );

		if ( mapping ) {
			mappings[ pluginMapping( each ) ] = build;
		} else {
			listed.push( build );
		}
	}

	const withPlugins = withDevelopment(
		override,
		'plugins',
		listed.length + others.length === 0 ? undefined : [ ...basePlugins, ...others, ...listed ],
	);

	return withDevelopment(
		withPlugins,
		'mappings',
		Object.keys( mappings ).length > 0 ? mappings : undefined,
	);
}

/**
 * The plugins whose build the override mounts under `mappings`, in `PLUGINS`
 * order. wp-env activates only what is in `plugins`, so `pnpm env:activate`
 * activates these.
 */
export function mappedPlugins( override: Override ): Plugin[] {
	const mappings = object( object( object( override.env ).development ).mappings );

	return PLUGINS.filter( ( plugin ) => mappings[ pluginMapping( plugin ) ] !== undefined );
}

/**
 * The override's top-level `plugins` entries that are a build of either
 * plugin, as `buildOf` judges. wp-env applies top-level plugins to both
 * sites, so such an entry puts the real plugin on the tests site beside its
 * fake and keeps it on the development site whatever its command chooses.
 */
export function sharedPluginBuilds(
	override: Override,
	buildOf: ( entry: string ) => Plugin | null,
): string[] {
	const { plugins } = override;

	return Array.isArray( plugins )
		? plugins.map( String ).filter( ( entry ) => buildOf( entry ) !== null )
		: [];
}

/** The address WordPress, inside Docker, reaches `url` at. */
export function serverAddress( url: string ): string {
	const parsed = new URL( url );

	if ( LOOPBACK_HOSTS.includes( parsed.hostname ) ) {
		parsed.hostname = DOCKER_HOST;
	}

	return parsed.toString().replace( /\/$/, '' );
}

/** Where the development site's plugin finds Pixonaut. */
export interface PixonautAddresses {
	readonly api: string;
	readonly studio: string | null;
}

/**
 * The override with the plugin's Pixonaut constants set for the development
 * site, or removed with `null`, so the plugin uses its own defaults. Other
 * constants in the override are kept.
 */
export function withPixonaut( override: Override, addresses: PixonautAddresses | null ): Override {
	const config = object( object( object( override.env ).development ).config );

	for ( const name of Object.values( API_CONSTANTS ) ) {
		delete config[ name ];
	}

	if ( addresses !== null ) {
		config[ API_CONSTANTS.api ] = new URL( addresses.api ).toString().replace( /\/$/, '' );
		config[ API_CONSTANTS.serverApi ] = serverAddress( addresses.api );

		if ( addresses.studio !== null ) {
			config[ API_CONSTANTS.studio ] = new URL( addresses.studio ).toString().replace( /\/$/, '' );
		}
	}

	return withDevelopment(
		override,
		'config',
		Object.keys( config ).length > 0 ? config : undefined,
	);
}
