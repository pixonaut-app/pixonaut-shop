/**
 * What the fake plugin (`tests/e2e/fake-pixonaut.php`) lists on its product's
 * cart and order lines.
 */

/** The fake product's one side, by its name as the plugin shows it. */
export const FAKE_SIDE = 'Front';

/**
 * The fake side's field rows, label then value: a field without a label is
 * named by its type, and a picture reads "Image".
 */
export const FAKE_FIELD_ROWS = [
	[ 'Name', 'Alex' ],
	[ 'Greeting', 'Happy birthday' ],
	[ 'Colour', '#1e6b52' ],
	[ 'Photo', 'Image' ],
] as const;
