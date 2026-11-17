import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { byDepth } from '../../src/archive/client/drawer.ts';
import type { Filter } from '../../src/archive/index/types.ts';

function filter( key: string, parent?: string ): Filter {
	return {
		key,
		label: key,
		type: 'attribute',
		values: [],
		...( parent ? { parent: { key: parent, values: [] } } : {} ),
	};
}

describe( 'byDepth', () => {
	it( 'puts each parent before its dependents, two levels deep', () => {
		const order = byDepth( [
			filter( 'finish', 'colour' ),
			filter( 'colour', 'framing' ),
			filter( 'framing' ),
		] );

		assert.deepEqual(
			order.map( ( known ) => known.key ),
			[ 'framing', 'colour', 'finish' ],
		);
	} );

	it( 'leaves out attributes that depend on each other in a circle', () => {
		const order = byDepth( [ filter( 'a', 'b' ), filter( 'b', 'a' ), filter( 'size' ) ] );

		assert.deepEqual(
			order.map( ( known ) => known.key ),
			[ 'size' ],
		);
	} );
} );
