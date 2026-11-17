/**
 * `pnpm fonts:metrics`: writes the font metrics table `inc/Fonts/` reads.
 * Rerun it after upgrading @capsizecss/metrics.
 */

import { writeFileSync } from 'node:fs';
import { entireMetricsCollection } from '@capsizecss/metrics/entireMetricsCollection';

import { type FaceMetrics, metricsPhp, metricsTable } from './metrics.ts';

const OUTPUT = new URL( '../../inc/Fonts/font-metrics.php', import.meta.url );

writeFileSync(
	OUTPUT,
	metricsPhp( metricsTable( entireMetricsCollection as unknown as Record< string, FaceMetrics > ) ),
);
