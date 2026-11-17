/**
 * The font metrics table `inc/Fonts/` measures fallback faces from.
 * Run with Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';

import { type FaceMetrics, metricsPhp, metricsTable } from '../../scripts/fonts/metrics.ts';

const NUNITO: FaceMetrics = {
	familyName: 'Nunito',
	category: 'sans-serif',
	ascent: 1011,
	descent: -353,
	lineGap: 0,
	unitsPerEm: 1000,
	xWidthAvg: 452,
	subsets: { latin: { xWidthAvg: 452 } },
	variants: {
		'800': {
			familyName: 'Nunito',
			ascent: 1011,
			descent: -353,
			lineGap: 0,
			unitsPerEm: 1000,
			xWidthAvg: 481,
			subsets: { latin: { xWidthAvg: 481 } },
		},
		'800italic': {
			familyName: 'Nunito',
			ascent: 1011,
			descent: -353,
			lineGap: 0,
			unitsPerEm: 1000,
			xWidthAvg: 470,
		},
	},
};

describe( 'metricsTable', () => {
	it( 'keys a family by its name in lower case, with its Latin width at each upright weight', () => {
		assert.deepEqual( metricsTable( { nunito: NUNITO } ), {
			nunito: {
				serif: false,
				unitsPerEm: 1000,
				ascent: 1011,
				descent: 353,
				lineGap: 0,
				widths: { 400: 452, 800: 481 },
			},
		} );
	} );

	it( 'marks a serif family', () => {
		const lora = { ...NUNITO, familyName: 'Lora', category: 'serif', variants: {} };

		assert.equal( metricsTable( { lora } ).lora.serif, true );
	} );
} );

describe( 'metricsPhp', () => {
	it( 'writes one family a line', () => {
		assert.match(
			metricsPhp( metricsTable( { nunito: NUNITO } ) ),
			/^ {4}'nunito' => \[false, 1000, 1011, 353, 0, \[400 => 452, 800 => 481\]\],$/m,
		);
	} );

	it( "matches the theme's generated table", () => {
		const table = readFileSync(
			new URL( '../../inc/Fonts/font-metrics.php', import.meta.url ),
			'utf8',
		);

		assert.match( table, /^ {4}'nunito' => \[false, 1000, 1011, 353, 0, \[/m );
		assert.match(
			table,
			/^ {4}'arial' => \[false, 2048, 1854, 434, 67, \[400 => 913, 700 => 983\]\],$/m,
		);
	} );
} );
