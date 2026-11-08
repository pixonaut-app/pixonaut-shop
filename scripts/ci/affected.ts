/**
 * Which CI jobs, and which end-to-end specs, a change affects.
 *
 * Pure: `affected-local-cli.ts` reads git and hands the changed paths here,
 * for the suites a change needs run locally; CI itself runs everything, and `affected-cli.ts` writes that as its outputs. A path no rule
 * knows runs everything, so a new kind of file costs a full run until someone
 * gives it a rule, never a skipped suite.
 */

/** The end-to-end specs one path can break: every spec, or these by name. */
export type SpecSelection = 'all' | readonly string[];

/** Which of the jobs other than end-to-end run. */
interface JobFlags {
	readonly php: boolean;
	readonly js: boolean;
	readonly shellScripts: boolean;
	readonly integration: boolean;
}

/** What one changed path needs run. */
interface Jobs extends JobFlags {
	readonly e2e: SpecSelection;
}

/** What a whole change needs run, with the specs resolved to real files. */
export interface Affected extends JobFlags {
	/** Spec names (a spec file's name without `SPEC_SUFFIX`), in `allSpecs` order. */
	readonly specs: readonly string[];
	/** Whether `specs` is every spec, which Playwright runs without naming them. */
	readonly wholeSuite: boolean;
	/** How many e2e jobs to split the specs over; 0 when there are none. */
	readonly shards: number;
	/** The changed paths no rule knew, each of which ran everything. */
	readonly unmatched: readonly string[];
}

/** Where the end-to-end specs live; a test holds it to Playwright's `testDir`. */
export const E2E_DIR = 'tests/e2e';

const SPEC_SUFFIX = '.spec.ts';

/**
 * The names a spec may have. A name reaches the e2e job's command line and
 * the job outputs, so nothing that could split a word or a line gets there.
 */
const SPEC_NAME = /^[a-z0-9-]+$/;

/**
 * A selection of more than half the suite runs over two e2e jobs, each with
 * its own wp-env: on one job it would take nearly twice a full run. The
 * runner has 2 vCPUs shared with wp-env's containers, so more Playwright
 * workers in one job would not help; a second job does.
 */
export const LARGE_SELECTION_SHARDS = 2;

/** Half the suite or less is short enough for one job. */
const SMALL_SELECTION_SHARDS = 1;

/**
 * Below this many specs the whole suite is shorter than starting a second
 * wp-env, so it always runs in one job.
 */
export const MIN_SPECS_TO_SHARD = 10;

/** The spec a path names, or `null` when it is not a spec file directly in `E2E_DIR`. */
export function specName( path: string ): string | null {
	const prefix = `${ E2E_DIR }/`;

	if ( ! path.startsWith( prefix ) || ! path.endsWith( SPEC_SUFFIX ) ) {
		return null;
	}

	const name = path.slice( prefix.length, -SPEC_SUFFIX.length );

	return SPEC_NAME.test( name ) ? name : null;
}

/** The spec names among the entries of `E2E_DIR`. */
export function specNames( files: readonly string[] ): string[] {
	return files
		.map( ( file ) => specName( `${ E2E_DIR }/${ file }` ) )
		.filter( ( name ): name is string => name !== null );
}

/** The file a spec name stands for, as Playwright takes it on the command line. */
export function specPath( name: string ): string {
	return `${ E2E_DIR }/${ name }${ SPEC_SUFFIX }`;
}

const NOTHING: Jobs = { php: false, js: false, shellScripts: false, integration: false, e2e: [] };

const EVERYTHING: Jobs = {
	php: true,
	js: true,
	shellScripts: true,
	integration: true,
	e2e: 'all',
};

/**
 * What a change to the theme's own blocks can break: the blocks in the editor
 * and the patterns built from them opening without invalid blocks, the
 * mini-cart drawer, which holds the free-shipping note, and the category
 * carousel's arrows, which its view script works.
 */
export const BLOCK_SPECS: readonly string[] = [
	'blocks',
	'mini-cart',
	'site-editor',
	'category-carousel',
];

/**
 * What Pixonaut for WooCommerce's output, and the fake plugin that stands in
 * for it, can break: the product page with its designer, a variable
 * product's options beside it, and the rows it adds to a line in the cart,
 * the mini-cart drawer, the checkout's summary and the order received page.
 */
export const PLUGIN_SPECS: readonly string[] = [
	'buy-row',
	'cart',
	'checkout',
	'mini-cart',
	'order-received',
	'product-options',
	'product-page',
];

/** What a change to the header's scripts can break: its search and the mega header's menu. */
export const HEADER_SCRIPT_SPECS: readonly string[] = [ 'header', 'mega-menu' ];

/**
 * The specs each `inc/` entry can break. `'all'` wherever they cannot be
 * named with confidence: narrowing an entry later is easy, and a wrong narrow
 * entry skips a suite that should have run. The tests require an entry for
 * every entry in `inc/`, so a new one fails until someone decides.
 */
export const E2E_OWNERS: Readonly< Record< string, SpecSelection > > = {
	// My account's dashboard and its orders table.
	'inc/Account': [ 'account' ],
	// The shop and its category and tag archives, and the searches: how many
	// products a page shows, and what an archive or a search without any
	// shows instead.
	// The category picture the mega header's menu shares with the categories block.
	'inc/Archive': [ 'mega-menu', 'search', 'shop' ],
	'inc/Assets': 'all',
	// The block styles, which reach every page's global stylesheet, the
	// menu heading, on every page's footer, and the theme's own blocks.
	'inc/Blocks': 'all',
	'inc/Bootstrap.php': 'all',
	// A page's excerpt, which no spec opens a page to see; the integration
	// suite renders a page with and without one.
	// The campaign band in the header's announcement part, which the header
	// spec opens.
	'inc/Campaign': [ 'header' ],
	'inc/Content': [],
	'inc/Dependencies': 'all',
	// Which template the homepage renders: the front page, or the posts list.
	'inc/FrontPage': [ 'front-page' ],
	// What loads the header search's script, and the mega header's menu.
	'inc/Header': HEADER_SCRIPT_SPECS,
	'inc/I18n': 'all',
	// What the order received page fills in when it renders: its pill.
	'inc/Orders': [ 'order-received' ],
	// Callbacks on Pixonaut for WooCommerce's hooks, which only the fake
	// plugin fires on the tests site: the cart, the mini-cart, the checkout's
	// summary, the product page and the order received page.
	'inc/Pixonaut': PLUGIN_SPECS,
	// The price row the product page's add-to-cart form prints, a variable
	// product's options as swatches, and the lowest price beside a reduced
	// price on the product page, its card and its cart line.
	'inc/Product': [ 'buy-row', 'lowest-price', 'product-options', 'product-page' ],
	'inc/Support': 'all',
	// The update checker makes no request without a token, which no site the
	// suite visits has; the integration suite tests it against a fake GitHub.
	'inc/Updates': [],
};

/**
 * Specs no single area owns: they cover the templates every store shares,
 * and run whenever an entry above is `'all'`. The tests require every spec to
 * be here or named by an owner, so a new spec cannot end up running only on
 * full runs without anyone deciding that.
 */
export const SITE_WIDE_SPECS: readonly string[] = [
	'header',
	'not-found',
	'product-page',
	'site-editor',
	'styles',
];

/**
 * What a change to a template, part, pattern or translation can break: every
 * template opening in the Site Editor without invalid blocks, the front
 * page, the header's menu and the mega header's, the mini-cart drawer, the not-found page, the
 * order received page, the product page, the searches, the shop, the cart
 * and the checkout, and the lowest price beside a reduced price.
 */
export const PAGE_SPECS: readonly string[] = [
	'buy-row',
	'cart',
	'checkout',
	'front-page',
	'header',
	'lowest-price',
	'mega-menu',
	'mini-cart',
	'not-found',
	'order-received',
	'product-options',
	'product-page',
	'search',
	'shop',
	'site-editor',
];

/**
 * What a change to the styles can break: the pages the specs look at, the
 * header's menu collapsing at its breakpoint, the mega header's panel and
 * drawer, the mini-cart drawer, and the
 * typeface and custom properties every page carries.
 */
export const LOOK_SPECS: readonly string[] = [
	'account',
	'buy-row',
	'cart',
	'checkout',
	'front-page',
	'header',
	'lowest-price',
	'mega-menu',
	'mini-cart',
	'not-found',
	'order-received',
	'product-options',
	'product-page',
	'search',
	'shop',
	'styles',
];

/** What a change to an editor script can break: the Site Editor opening every template. */
export const EDITOR_SPECS: readonly string[] = [ 'site-editor' ];

/** What a change to the product page's script can break: a variable product's options as swatches. */
export const PRODUCT_SCRIPT_SPECS: readonly string[] = [ 'product-options' ];

/**
 * The specs a pattern has of its own, run with `PAGE_SPECS` when it changes.
 * A pattern with no entry runs `PAGE_SPECS` only. Keys are the file names in
 * `patterns/` without `.php`.
 */
export const PATTERN_SPECS: Readonly< Record< string, readonly string[] > > = {
	'not-found': [ 'not-found' ],
	promises: [ 'blocks' ],
};

/** The specs a changed pattern file needs. */
function patternSpecs( name: string ): readonly string[] {
	return [ ...PAGE_SPECS, ...( PATTERN_SPECS[ name ] ?? [] ) ];
}

/** An owned area's jobs, or `null` when the map does not know it. */
function owned( key: string, jobs: Partial< JobFlags > ): Jobs | null {
	const specs = E2E_OWNERS[ key ];

	return specs === undefined ? null : { ...NOTHING, ...jobs, e2e: specs };
}

interface Rule {
	readonly pattern: RegExp;
	/** The path's jobs, or `null` when the rule does not know it after all. */
	readonly jobs: ( match: RegExpExecArray ) => Jobs | null;
}

function rule( pattern: RegExp, jobs: Partial< Jobs > ): Rule {
	return { pattern, jobs: () => ( { ...NOTHING, ...jobs } ) };
}

/** First match wins; a path matching none, or that its rule does not know, runs `EVERYTHING`. */
const RULES: readonly Rule[] = [
	// Documentation: no suite reads it.
	rule(
		/^docs\/|\.md$|^LICENSE$|^release-please-config\.json$|^\.release-please-manifest\.json$/,
		{},
	),
	rule( /^scripts\/docker\//, { shellScripts: true } ),
	{
		pattern: /^(inc\/[^/]+)/,
		jobs: ( [ , key ] ) => owned( key, { php: true, integration: true } ),
	},
	// The theme's blocks, built into build/blocks/: their editor scripts and
	// stylesheets, the block.json the unit suite reads and the render.php
	// PHPCS and PHPStan check.
	rule( /^src\/blocks\//, { php: true, js: true, integration: true, e2e: BLOCK_SPECS } ),
	// The theme's own stylesheet and its partials, built into build/ and
	// loaded on every page.
	rule( /^src\/.+\.css$/, { js: true, e2e: LOOK_SPECS } ),
	// The product page's script, built into build/ and loaded on a product
	// page whose add-to-cart form has the swatches style.
	rule( /^src\/product\/.+\.ts$/, { js: true, e2e: PRODUCT_SCRIPT_SPECS } ),
	// The header search's script, built into build/ and loaded on a page
	// whose search block is only its button.
	rule( /^src\/header\/.+\.ts$/, { js: true, e2e: HEADER_SCRIPT_SPECS } ),
	// The theme's editor scripts, built into build/ and loaded in the Site
	// Editor only.
	rule( /^src\/.+\.js$/, { js: true, e2e: EDITOR_SPECS } ),
	// js: tests/ts holds every spec to an owner, so adding or removing one
	// runs the check that guards it. A spec whose name `specName` rejects is
	// one CI cannot name, so it runs everything.
	{
		pattern: /^tests\/e2e\/([^/]+)\.spec\.ts$/,
		jobs: ( [ , name ] ) =>
			SPEC_NAME.test( name ) ? { ...NOTHING, js: true, e2e: [ name ] } : null,
	},
	// The fake plugin wp-env maps into the tests site, which both suites run
	// on: integration sees the hooks it fires, e2e the markup it prints.
	rule( /^tests\/e2e\/fake-pixonaut\.php$/, {
		php: true,
		integration: true,
		e2e: PLUGIN_SPECS,
	} ),
	// The tests site's plugin that shows the mega header to the mega menu's
	// spec alone.
	rule( /^tests\/e2e\/mega-menu\.php$/, {
		php: true,
		integration: true,
		e2e: [ 'mega-menu' ],
	} ),
	// Helpers the specs share: any spec may import one.
	rule( /^tests\/e2e\/support\//, { js: true, e2e: 'all' } ),
	rule( /^tests\/Unit\//, { php: true } ),
	rule(
		/^tests\/(Integration|Doubles)\/|^tests\/(bootstrap|TestCase)\.php$|^phpunit-integration\.xml\.dist$/,
		{ php: true, integration: true },
	),
	// The e2e suite's global setup loads tests/Support through wp-cli, and the
	// integration suite's bootstrap prepares the shop with it.
	rule( /^tests\/Support\//, { php: true, integration: true, e2e: 'all' } ),
	rule( /^tests\/ts\//, { js: true } ),
	// CI's and the release's own scripts, tested under tests/ts; the
	// catalogue check also runs in the integration job. CI itself runs
	// everything, so a broken classifier cannot skip a suite there.
	rule( /^scripts\/ci\/pot[^/]*\.ts$/, { js: true, integration: true } ),
	rule( /^scripts\/(ci|release|plugin)\//, { js: true } ),
	rule( /^(functions|index)\.php$/, { php: true, integration: true, e2e: 'all' } ),
	// The unit suite reads patterns, templates and parts for what they may not
	// hold, and PHPCS checks patterns/*.php for escaping and text domains.
	{
		pattern: /^patterns\/([^/]+)\.php$/,
		jobs: ( [ , name ] ) => ( {
			...NOTHING,
			php: true,
			integration: true,
			e2e: patternSpecs( name ),
		} ),
	},
	rule( /^(templates|parts)\//, { php: true, integration: true, e2e: PAGE_SPECS } ),
	// The unit suite reads the Swedish catalogue; integration translates with it.
	rule( /^languages\//, { php: true, integration: true, e2e: PAGE_SPECS } ),
	// The unit suite reads theme.json, the variations and style.css's header.
	rule( /^(theme\.json|styles\/[^/]+\.json|style\.css)$/, {
		php: true,
		integration: true,
		e2e: LOOK_SPECS,
	} ),
	// The unit suite checks theme.json's font faces name files that exist.
	rule( /^assets\/fonts\//, { php: true, e2e: LOOK_SPECS } ),
	rule( /^screenshot\.png$/, {} ),
	// The unit suite reads the readme the theme directory shows.
	rule( /^readme\.txt$/, { php: true } ),
];

function jobsFor( path: string ): Jobs | null {
	for ( const { pattern, jobs } of RULES ) {
		const match = pattern.exec( path );

		if ( match !== null ) {
			return jobs( match );
		}
	}

	return null;
}

/**
 * The jobs and specs a change needs: every changed path's, in union. Specs
 * are limited to `allSpecs`, the files that exist, so a deleted spec is
 * dropped and nothing but a real spec name ever reaches a command line.
 */
export function affected( paths: readonly string[], allSpecs: readonly string[] ): Affected {
	const known: Jobs[] = [];
	const unmatched: string[] = [];

	for ( const path of paths ) {
		const jobs = jobsFor( path );

		if ( jobs === null ) {
			unmatched.push( path );
		} else {
			known.push( jobs );
		}
	}

	return union( unmatched.length > 0 ? [ ...known, EVERYTHING ] : known, allSpecs, unmatched );
}

/** Every job and the whole suite: what CI itself runs. */
export function everything( allSpecs: readonly string[] ): Affected {
	return union( [ EVERYTHING ], allSpecs, [] );
}

/** No job at all: a nightly on a commit a full run already passed. */
export function nothing(): Affected {
	return union( [], [], [] );
}

/** How many e2e jobs `specs` runs over, out of a suite of `allSpecs`. */
function shardsFor( specs: readonly string[], allSpecs: readonly string[] ): number {
	if ( specs.length === 0 ) {
		return 0;
	}

	if ( allSpecs.length >= MIN_SPECS_TO_SHARD && specs.length * 2 > allSpecs.length ) {
		return LARGE_SELECTION_SHARDS;
	}

	return SMALL_SELECTION_SHARDS;
}

function union(
	pathJobs: readonly Jobs[],
	allSpecs: readonly string[],
	unmatched: readonly string[],
): Affected {
	const wantsEverySpec = pathJobs.some( ( jobs ) => jobs.e2e === 'all' );
	const named = new Set( pathJobs.flatMap( ( jobs ) => ( jobs.e2e === 'all' ? [] : jobs.e2e ) ) );
	const specs = wantsEverySpec ? [ ...allSpecs ] : allSpecs.filter( ( name ) => named.has( name ) );

	return {
		php: pathJobs.some( ( jobs ) => jobs.php ),
		js: pathJobs.some( ( jobs ) => jobs.js ),
		shellScripts: pathJobs.some( ( jobs ) => jobs.shellScripts ),
		integration: pathJobs.some( ( jobs ) => jobs.integration ),
		specs,
		wholeSuite: specs.length > 0 && specs.length === allSpecs.length,
		shards: shardsFor( specs, allSpecs ),
		unmatched,
	};
}

/** The job outputs the workflow's jobs are gated on, as `ci.yml` reads them. */
export function jobOutputs( result: Affected ): Record< string, string > {
	return {
		php: String( result.php ),
		js: String( result.js ),
		shell_scripts: String( result.shellScripts ),
		integration: String( result.integration ),
		e2e: String( result.shards > 0 ),
		// Empty for the whole suite, which Playwright runs without arguments.
		e2e_specs: result.wholeSuite ? '' : result.specs.map( specPath ).join( ' ' ),
		e2e_shards: JSON.stringify(
			Array.from( { length: result.shards }, ( _, index ) => index + 1 ),
		),
	};
}

/**
 * The commands that run locally what CI's jobs run for `result`, in order:
 * each job's steps, then the site the integration and e2e jobs share. The
 * theme's stylesheet loads from `build/`, so the site is built before either
 * runs, as CI's wp-env action does; `pnpm build` runs once even when the js
 * job wants it too. Both need this checkout's wp-env running.
 */
export function localCommands( result: Affected ): string[] {
	const needsSite = result.integration || result.shards > 0;
	const commands: string[] = [];

	if ( result.php ) {
		commands.push( 'pnpm fmt:php:check', 'pnpm stan', 'pnpm lint:php', 'pnpm test:unit' );
	}

	if ( result.js ) {
		commands.push( 'pnpm lint', 'pnpm typecheck', 'pnpm test:ts', 'pnpm build' );
	}

	if ( result.shellScripts ) {
		commands.push( 'pnpm lint:sh' );
	}

	if ( needsSite && ! result.js ) {
		commands.push( 'pnpm build' );
	}

	if ( result.integration ) {
		commands.push( 'pnpm test:integration', 'pnpm i18n:pot', 'pnpm i18n:check' );
	}

	if ( result.shards > 0 ) {
		commands.push(
			[ 'pnpm test:e2e', ...( result.wholeSuite ? [] : result.specs.map( specPath ) ) ].join( ' ' ),
		);
	}

	return commands;
}

/** The jobs `result` runs, named as `ci.yml` names them, for a summary line. */
export function describeJobs( result: Affected ): string {
	const jobs = Object.entries( {
		PHP: result.php,
		JavaScript: result.js,
		'Shell scripts': result.shellScripts,
		'Integration and translation catalogue': result.integration,
	} )
		.filter( ( [ , runs ] ) => runs )
		.map( ( [ job ] ) => job );

	const over = result.shards > 1 ? ` over ${ result.shards } jobs` : '';
	let e2e = 'none';
	if ( result.wholeSuite ) {
		e2e = `the whole suite${ over }`;
	} else if ( result.specs.length > 0 ) {
		e2e = `${ result.specs.join( ', ' ) }${ over }`;
	}

	return `Jobs: ${ jobs.length > 0 ? jobs.join( ', ' ) : 'none' }. End-to-end: ${ e2e }.`;
}

/**
 * Outputs in `$GITHUB_OUTPUT`'s `key=value` lines. Throws on a value with a
 * line break, which would start an output of its own.
 */
export function githubOutput( outputs: Readonly< Record< string, string > > ): string {
	return Object.entries( outputs )
		.map( ( [ key, value ] ) => {
			if ( value.includes( '\n' ) ) {
				throw new Error( `The ${ key } output holds a line break.` );
			}

			return `${ key }=${ value }\n`;
		} )
		.join( '' );
}

/** The events whose runs run everything: the nightly and a manual run. */
const FULL_RUN_EVENTS: readonly string[] = [ 'schedule', 'workflow_dispatch' ];

/** The branch the nightly tests. */
const BASE_BRANCH = 'main';

/**
 * Whether GitHub's listing of `ci.yml`'s runs holds a full run on `main`
 * that passed commit `sha`, so the nightly has nothing new to test. A
 * listing that is not the documented shape counts as no such run, and the
 * nightly runs.
 */
export function fullRunPassed( body: unknown, sha: string ): boolean {
	if (
		typeof body !== 'object' ||
		body === null ||
		! ( 'workflow_runs' in body ) ||
		! Array.isArray( body.workflow_runs )
	) {
		return false;
	}

	return body.workflow_runs.some(
		( run: unknown ) =>
			typeof run === 'object' &&
			run !== null &&
			'event' in run &&
			typeof run.event === 'string' &&
			FULL_RUN_EVENTS.includes( run.event ) &&
			'head_branch' in run &&
			run.head_branch === BASE_BRANCH &&
			'head_sha' in run &&
			run.head_sha === sha &&
			'conclusion' in run &&
			run.conclusion === 'success',
	);
}
