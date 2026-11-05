/**
 * What `pnpm plugin:use` runs: `usePlugin` for one plugin with the real `gh`, `unzip` and git.
 */

import { execFileSync } from 'node:child_process';
import { dirname } from 'node:path';

import type { Plugin } from './source.ts';
import { usePlugin } from './use.ts';

/** Runs a command and returns its output; its error output becomes the error's message. */
function run( command: string, args: readonly string[] ): string {
	try {
		return execFileSync( command, args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } );
	} catch ( error ) {
		const stderr = ( error as { stderr?: unknown } ).stderr;

		throw new Error( typeof stderr === 'string' && stderr !== '' ? stderr : String( error ) );
	}
}

/** This repository's main checkout, also from inside a worktree. */
function themeMainCheckout(): string {
	return dirname(
		run( 'git', [ 'rev-parse', '--path-format=absolute', '--git-common-dir' ] ).trim(),
	);
}

/** Chooses `plugin`'s build from the command line's first argument and prints the outcome. */
export function useFromCommandLine( plugin: Plugin ): void {
	const [ arg ] = process.argv.slice( 2 );

	try {
		if ( arg === undefined ) {
			throw new Error(
				`Name the build: ${ plugin.command } <${ plugin.none }|main|/path/to/checkout|v1.2.3|latest>`,
			);
		}

		console.log(
			usePlugin( plugin, arg, {
				cwd: process.cwd(),
				env: process.env,
				themeMainCheckout: themeMainCheckout(),
				gh: ( args ) => run( 'gh', args ),
				unzip: ( zip, directory ) => {
					run( 'unzip', [ '-q', '-o', zip, '-d', directory ] );
				},
			} ),
		);
	} catch ( error ) {
		console.error( error instanceof Error ? error.message : String( error ) );
		process.exitCode = 1;
	}
}
