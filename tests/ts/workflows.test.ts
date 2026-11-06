/**
 * What a public repository's workflows may do with a stranger's pull request.
 * Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, it } from 'node:test';

const WORKFLOWS = '.github/workflows';
const PULL_REQUEST = join( WORKFLOWS, 'pull-request.yml' );
const RELEASE = join( WORKFLOWS, 'release.yml' );

describe( 'workflows', () => {
	for ( const file of readdirSync( WORKFLOWS ) ) {
		const workflow = readFileSync( join( WORKFLOWS, file ), 'utf8' );

		it( `${ file } never runs with pull_request_target`, () => {
			assert.doesNotMatch( workflow, /^\s*pull_request_target\s*:/m );
		} );

		it( `${ file } runs only on GitHub-hosted runners`, () => {
			assert.doesNotMatch( workflow, /self-hosted/ );
		} );
	}

	it( 'checks a pull request with read-only access and no secrets', () => {
		const workflow = readFileSync( PULL_REQUEST, 'utf8' );

		assert.match( workflow, /^on:\n {2}pull_request:/m );
		assert.match( workflow, /^permissions:\n {2}contents: read$/m );
		assert.doesNotMatch( workflow, /secrets\./ );
	} );

	it( 'runs lint and the unit tests on a pull request', () => {
		const workflow = readFileSync( PULL_REQUEST, 'utf8' );

		for ( const command of [
			'pnpm lint',
			'pnpm typecheck',
			'pnpm test:ts',
			'vendor/bin/phpcs',
			'vendor/bin/phpunit --testsuite unit',
		] ) {
			assert.ok( workflow.includes( command ), `${ command } is missing` );
		}
	} );

	it( 'builds, checks and smoke-tests the GitHub and the wordpress.org flavors of a release', () => {
		const workflow = readFileSync( RELEASE, 'utf8' );

		assert.match( workflow, /^ {8}flavor: \[github, wporg\]$/m );

		for ( const step of [
			'flavor-cli.ts assemble --flavor "$FLAVOR"',
			'flavor-cli.ts check --flavor "$FLAVOR" "$SMOKE_DIR/$THEME_SLUG"',
			'flavor-cli.ts zip-name --flavor "$FLAVOR"',
			'gh release upload "$TAG" "$ZIP" "$ZIP.sha256"',
		] ) {
			assert.ok( workflow.includes( step ), `${ step } is missing` );
		}
	} );
} );
