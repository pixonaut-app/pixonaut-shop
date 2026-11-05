/**
 * `pnpm plugin:use <fake|main|/path/to/checkout|v1.2.3|latest>`: which build
 * of Pixonaut for WooCommerce the development site runs.
 */

import { useFromCommandLine } from './cli.ts';
import { PIXONAUT_WC } from './source.ts';

useFromCommandLine( PIXONAUT_WC );
