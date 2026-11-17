import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { openedAlone } from '../../src/archive/opened-alone.ts';

describe( 'openedAlone', () => {
	it( 'is every dependent a value opens while no other ticked value opens it', () => {
		assert.deepEqual( openedAlone( [ 'pa_frame-colour' ], [ [] ] ), [ 'pa_frame-colour' ] );
	} );

	it( 'leaves a dependent another ticked value still opens', () => {
		assert.deepEqual( openedAlone( [ 'pa_frame-colour', 'pa_mount' ], [ [ 'pa_frame-colour' ] ] ), [
			'pa_mount',
		] );
	} );

	it( 'is nothing for a value that opens nothing', () => {
		assert.deepEqual( openedAlone( [], [ [ 'pa_frame-colour' ] ] ), [] );
	} );
} );
