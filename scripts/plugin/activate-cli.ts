/**
 * Activates the plugin builds the development site mounts under `mappings`:
 * wp-env activates only those in `plugins`. Part of `pnpm env:activate`; does
 * nothing when none is mounted.
 */

import { execFileSync } from 'node:child_process';

import { mappedPlugins } from './source.ts';
import { readOverride } from './use.ts';

const activate = mappedPlugins( readOverride( process.cwd() ) );

if ( activate.length > 0 ) {
	execFileSync(
		'pnpm',
		[ 'env:cli', 'plugin', 'activate', ...activate.map( ( plugin ) => plugin.slug ) ],
		{ stdio: 'inherit' },
	);
}
