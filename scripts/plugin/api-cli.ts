/**
 * `pnpm plugin:api <api-url> [studio-url]`, or `pnpm plugin:api default`:
 * the Pixonaut the development site's plugin talks to.
 */

import { usePixonaut } from './use.ts';

try {
	console.log( usePixonaut( process.argv.slice( 2 ), process.cwd() ) );
} catch ( error ) {
	console.error( error instanceof Error ? error.message : String( error ) );
	process.exitCode = 1;
}
