/**
 * The chips the Use swatches style draws for a variation menu
 * (`src/product/swatch-choices.ts`). Run with Node's own test runner:
 * `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { swatchChoices } from '../../src/product/swatch-choices.ts';

/** WooCommerce's first option, which offers no value. */
const PLACEHOLDER = { value: '', label: 'Choose an option', disabled: false };

describe( 'swatchChoices', () => {
	it( 'draws a chip for each option but the placeholder, in the menu’s order', () => {
		assert.deepEqual(
			swatchChoices(
				[
					PLACEHOLDER,
					{ value: 'S', label: 'Small', disabled: false },
					{ value: 'M', label: 'Medium', disabled: false },
				],
				'',
			),
			[
				{ value: 'S', label: 'Small', disabled: false, checked: false },
				{ value: 'M', label: 'Medium', disabled: false, checked: false },
			],
		);
	} );

	it( 'checks the chip of the chosen option', () => {
		const choices = swatchChoices(
			[
				PLACEHOLDER,
				{ value: 'S', label: 'S', disabled: false },
				{ value: 'M', label: 'M', disabled: false },
			],
			'M',
		);

		assert.deepEqual(
			choices.map( ( choice ) => choice.checked ),
			[ false, true ],
		);
	} );

	it( 'keeps an option WooCommerce cannot sell with the other choices, unavailable', () => {
		const [ choice ] = swatchChoices( [ { value: 'L', label: 'L', disabled: true } ], '' );

		assert.equal( choice?.disabled, true );
	} );

	it( 'draws no chips for a menu with only the placeholder', () => {
		assert.deepEqual( swatchChoices( [ PLACEHOLDER ], '' ), [] );
	} );
} );
