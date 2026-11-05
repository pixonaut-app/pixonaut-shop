/**
 * Which of CI's jobs and end-to-end specs this checkout's change reaches, and
 * the commands that run them locally. The decisions are
 * `affected.ts`'s. CI itself runs everything, nightly and by hand,
 * so this is what checks a change before it merges.
 * Run through `pnpm ci:affected:local [base]`; the base defaults to
 * `origin/main`, so fetch it first.
 *
 * The change is everything since the branch left the base — commits, staged
 * and unstaged edits, and untracked files — so it can be asked before
 * committing as well as before a push.
 */

import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';

import { affected, describeJobs, E2E_DIR, localCommands, specNames } from './affected.ts';

const DEFAULT_BASE = 'origin/main';

function git( ...args: string[] ): string {
	return execFileSync( 'git', args, { encoding: 'utf8' } );
}

/** Paths from a `-z` listing. */
function pathsIn( listing: string ): string[] {
	return listing.split( '\0' ).filter( ( path ) => path !== '' );
}

const base = process.argv[ 2 ] ?? DEFAULT_BASE;
const forkPoint = git( 'merge-base', base, 'HEAD' ).trim();

// Against the working tree, not HEAD, so uncommitted edits count. Renames
// are a deletion and an addition, so the area a file left counts.
const paths = [
	...new Set( [
		...pathsIn( git( 'diff', '--no-renames', '--name-only', '-z', forkPoint ) ),
		...pathsIn( git( 'ls-files', '--others', '--exclude-standard', '-z' ) ),
	] ),
].sort();

const result = affected( paths, specNames( readdirSync( E2E_DIR ) ) );

console.log( `${ paths.length } changed paths since ${ base }. ${ describeJobs( result ) }` );

for ( const path of result.unmatched ) {
	console.log( `No rule knows ${ JSON.stringify( path ) }, so everything runs.` );
}

const commands = localCommands( result );

if ( commands.length > 0 ) {
	console.log( '\nRun:' );
	for ( const command of commands ) {
		console.log( `  ${ command }` );
	}
}
