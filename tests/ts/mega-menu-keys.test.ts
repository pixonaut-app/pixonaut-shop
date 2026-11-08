/**
 * Where focus goes in the mega header's panel (`src/header/mega-menu-keys.ts`).
 * Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { groupStep, trapStep } from '../../src/header/mega-menu-keys.ts';

/** A panel with four groups. */
const GROUPS = 4;

describe( 'groupStep', () => {
	it( 'moves down and up the groups, round from the last to the first', () => {
		assert.equal( groupStep( 'ArrowDown', 0, GROUPS ), 1 );
		assert.equal( groupStep( 'ArrowDown', GROUPS - 1, GROUPS ), 0 );
		assert.equal( groupStep( 'ArrowUp', 2, GROUPS ), 1 );
		assert.equal( groupStep( 'ArrowUp', 0, GROUPS ), GROUPS - 1 );
	} );

	it( 'jumps to the first and the last group', () => {
		assert.equal( groupStep( 'Home', 2, GROUPS ), 0 );
		assert.equal( groupStep( 'End', 1, GROUPS ), GROUPS - 1 );
	} );

	it( 'leaves every other key to the browser', () => {
		assert.equal( groupStep( 'ArrowRight', 1, GROUPS ), null );
		assert.equal( groupStep( 'Tab', 1, GROUPS ), null );
		assert.equal( groupStep( 'ArrowDown', 0, 0 ), null );
	} );
} );

describe( 'trapStep', () => {
	it( 'takes Tab from the last element round to the first', () => {
		assert.equal( trapStep( GROUPS - 1, GROUPS, false ), 0 );
	} );

	it( 'takes Shift+Tab from the first element round to the last', () => {
		assert.equal( trapStep( 0, GROUPS, true ), GROUPS - 1 );
	} );

	it( 'brings focus that is outside the panel back into it', () => {
		assert.equal( trapStep( -1, GROUPS, false ), 0 );
		assert.equal( trapStep( -1, GROUPS, true ), GROUPS - 1 );
	} );

	it( 'leaves Tab between the panel’s own elements to the browser', () => {
		assert.equal( trapStep( 1, GROUPS, false ), null );
		assert.equal( trapStep( 1, GROUPS, true ), null );
		assert.equal( trapStep( 0, 0, false ), null );
	} );
} );
