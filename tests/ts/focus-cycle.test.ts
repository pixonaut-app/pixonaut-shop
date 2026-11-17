import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { nextInCycle } from '../../src/archive/focus-cycle.ts';

const CONTROLS = [ 'close', 'checkbox', 'clear', 'show' ];

describe( 'nextInCycle', () => {
	it( 'leaves Tab alone between the first and the last control', () => {
		assert.equal( nextInCycle( CONTROLS, 'checkbox', false ), null );
		assert.equal( nextInCycle( CONTROLS, 'clear', true ), null );
	} );

	it( 'goes round from the last control to the first, and back', () => {
		assert.equal( nextInCycle( CONTROLS, 'show', false ), 'close' );
		assert.equal( nextInCycle( CONTROLS, 'close', true ), 'show' );
	} );

	it( 'brings the focus in from outside: to the first, or going back to the last', () => {
		assert.equal( nextInCycle( CONTROLS, 'elsewhere', false ), 'close' );
		assert.equal( nextInCycle( CONTROLS, null, true ), 'show' );
	} );

	it( 'does nothing without a control', () => {
		assert.equal( nextInCycle( [], 'close', false ), null );
	} );
} );
