/**
 * CI's `changes` job: writes the job outputs every other job is gated on.
 * CI runs only nightly and by hand, and both run everything; a
 * nightly on a commit a full run already passed runs nothing, so a night
 * without merges costs one short job. Run through `pnpm ci:affected`.
 */

import { execFileSync } from 'node:child_process';
import { appendFileSync, readdirSync } from 'node:fs';

import {
	describeJobs,
	E2E_DIR,
	everything,
	fullRunPassed,
	githubOutput,
	jobOutputs,
	nothing,
	specNames,
} from './affected.ts';

/** How long the GitHub lookup may take before the nightly just runs. */
const LOOKUP_TIMEOUT_MS = 15_000;

/** GitHub's page size ceiling; one commit rarely has more full runs. */
const RUNS_PER_PAGE = 100;

function required( name: string ): string {
	const value = process.env[ name ];

	if ( value === undefined || value === '' ) {
		throw new Error( `${ name } is not set; this runs in GitHub Actions only.` );
	}

	return value;
}

const env = {
	event: required( 'EVENT_NAME' ),
	token: required( 'GITHUB_TOKEN' ),
	repository: required( 'GITHUB_REPOSITORY' ),
	apiUrl: required( 'GITHUB_API_URL' ),
	output: required( 'GITHUB_OUTPUT' ),
	summary: required( 'GITHUB_STEP_SUMMARY' ),
};

/**
 * Whether a full run of `ci.yml` already passed `sha`. Any failure to look
 * it up counts as no, so the nightly runs.
 */
async function alreadyPassed( sha: string ): Promise< boolean > {
	const query = new URLSearchParams( {
		head_sha: sha,
		status: 'success',
		per_page: String( RUNS_PER_PAGE ),
	} );

	try {
		const response = await fetch(
			`${ env.apiUrl }/repos/${ env.repository }/actions/workflows/ci.yml/runs?${ query }`,
			{
				headers: { authorization: `Bearer ${ env.token }`, accept: 'application/vnd.github+json' },
				signal: AbortSignal.timeout( LOOKUP_TIMEOUT_MS ),
			},
		);

		if ( ! response.ok ) {
			throw new Error( `HTTP ${ response.status }` );
		}

		return fullRunPassed( await response.json(), sha );
	} catch ( error ) {
		console.log(
			`::warning::Could not look up earlier runs of ${ sha } (${ error }); running everything.`,
		);
		return false;
	}
}

async function main(): Promise< void > {
	const sha = execFileSync( 'git', [ 'rev-parse', 'HEAD' ], { encoding: 'utf8' } ).trim();
	// Only the nightly may find nothing to do; a manual run always runs.
	const skip = env.event === 'schedule' && ( await alreadyPassed( sha ) );
	const result = skip ? nothing() : everything( specNames( readdirSync( E2E_DIR ) ) );
	const summary = skip
		? `Skipped: \`${ sha }\` already passed a full run, and main has not moved since.`
		: `Running everything on \`${ sha }\`. ${ describeJobs( result ) }`;

	appendFileSync( env.output, githubOutput( jobOutputs( result ) ) );
	appendFileSync( env.summary, `${ summary }\n` );
	console.log( summary );
}

await main();
