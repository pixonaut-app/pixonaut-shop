import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { archivePath } from '../../src/archive/archive-path.ts';

describe( 'archivePath', () => {
	it( 'is the path of an archive on its first page', () => {
		assert.equal( archivePath( '/product-category/prints/' ), '/product-category/prints/' );
	} );

	it( 'is the same path from a later page, which a new filter leaves for the first', () => {
		assert.equal( archivePath( '/product-category/prints/page/2/' ), '/product-category/prints/' );
		assert.equal( archivePath( '/shop/page/12' ), '/shop/' );
	} );

	it( 'keeps a category that is itself named page', () => {
		assert.equal( archivePath( '/product-category/page/' ), '/product-category/page/' );
	} );
} );
