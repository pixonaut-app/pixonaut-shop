/**
 * What Pixonaut for WooCommerce prints and loads on the product page that the
 * fake plugin (`tests/e2e/fake-pixonaut.php`) leaves out: its storefront
 * stylesheet's rules for the preview, its strip of views, the side tabs and
 * the cart row, what it sets on a side's frame, the side tabs it prints for a
 * product with more than one side, the strip of views it prints under a
 * preview, the room it keeps for the SDK's mark, and the box its SDK draws the
 * design in. Copied from the plugin's
 * `assets/src/storefront/storefront.css` and `templates/storefront/form.php`
 * and `preview.php`; when the plugin changes them, change this in the same
 * change.
 */

/** The widest viewport the plugin lays its preview out for a phone at. */
export const PLUGIN_PHONE_MAX_WIDTH = 767;

/** The presets the plugin's script lists in `data-pixonaut-look` on a theme that defines them all. */
export const PLUGIN_THEME_LOOK = 'accent border bodyFont radius';

/** The most of a phone's screen the plugin lets the preview and the mark under it take, in `vh`. */
export const PLUGIN_PHONE_PREVIEW_VH = 80;

/** The room the plugin keeps under the preview for the SDK's "Powered by Pixonaut" mark. */
export const PLUGIN_MARK_HEIGHT_PX = 26;

/** The attribute on a frame whose workspace shows the mark, and the target the SDK draws it into. */
export const PLUGIN_FRAME_MARK = 'data-pixonaut-mark';
export const PLUGIN_MARK_TARGET = '<div class="pixonaut-preview__mark"></div>';

/** What the plugin's script sets on a preview short enough to leave the form room. */
export const PLUGIN_PINNED = 'data-pixonaut-pinned';

export const PLUGIN_STOREFRONT_CSS = `
.pixonaut-preview {
	box-sizing: border-box;
	min-width: 0;
}

.pixonaut-preview__frame {
	width: 100%;
}

.pixonaut-preview__frame[${ PLUGIN_FRAME_MARK }] {
	--pixonaut-preview-mark: ${ PLUGIN_MARK_HEIGHT_PX }px;
}

.pixonaut-preview__mark {
	display: flow-root;
	min-height: var(--pixonaut-preview-mark, 0px);
}

.pixonaut-preview__stage {
	position: relative;
}

.pixonaut-preview__side {
	width: 100%;
}

.pixonaut-preview__frame .pixonaut-preview__side {
	height: var(--pixonaut-preview-height, auto);
	aspect-ratio: var(--pixonaut-preview-ratio, 1);
}

.pixonaut-preview__frame.is-drawn .pixonaut-preview__side {
	aspect-ratio: auto;
}

.pixonaut-views__strip {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 12px;
	padding-top: var(--pixonaut-views-gap, 12px);
}

.pixonaut-form.is-closed + .pixonaut-cart-row .pixonaut-approval {
	display: none;
}

.pixonaut-cart-row {
	display: contents;
}

.pixonaut-form .pixonaut-cart-row {
	display: flow-root;
}

.pixonaut-tabs {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5em;
	margin: 0 0 1em;
}

.pixonaut-tabs__tab[aria-selected="true"] {
	font-weight: 700;
	text-decoration: underline;
	text-decoration-color: var(--wp--preset--color--accent, currentcolor);
	text-underline-offset: 0.25em;
}

.pixonaut-form[data-pixonaut-look~="border"] .pixonaut-tabs__tab {
	min-height: 2.75rem;
	padding: 0.375rem 0.875rem;
	border: 1px solid var(--wp--preset--color--border-strong);
	background: var(--wp--preset--color--base, transparent);
	color: var(--wp--preset--color--contrast, inherit);
	font-family: inherit;
	font-size: inherit;
	cursor: pointer;
}

.pixonaut-form[data-pixonaut-look~="radius"] .pixonaut-tabs__tab {
	border-radius: var(--wp--custom--radius--small);
}

.pixonaut-form[data-pixonaut-look~="accent"]
	.pixonaut-tabs__tab[aria-selected="true"] {
	text-decoration: none;
	border-color: var(--wp--preset--color--accent);
	box-shadow: inset 0 0 0 1px var(--wp--preset--color--accent);
	color: var(--wp--preset--color--accent);
}

@media (max-width: ${ PLUGIN_PHONE_MAX_WIDTH }px) {
	.pixonaut-preview {
		width: 100%;
		float: none;
		margin-right: 0;
	}

	.pixonaut-preview[${ PLUGIN_PINNED }] {
		position: sticky;
		top: 0;
		z-index: 1;
		background: inherit;
	}

	.pixonaut-preview__frame {
		max-width: calc(
			(${ PLUGIN_PHONE_PREVIEW_VH }vh - var(--pixonaut-preview-mark, 0px)) *
			var(--pixonaut-preview-drawn-aspect, var(--pixonaut-preview-aspect, 1))
		);
		margin-inline: auto;
	}
}
`;

/** The side tabs the plugin prints at the top of its form for a product with two sides, the first selected. */
export const PLUGIN_SIDE_TABS = `
<div class="pixonaut-tabs" role="tablist" aria-label="Sides">
	<button type="button" role="tab" id="pixonaut-tab-front" class="pixonaut-tabs__tab" data-pixonaut-side="front" aria-controls="pixonaut-form-front" aria-selected="true" tabindex="0">Front</button>
	<button type="button" role="tab" id="pixonaut-tab-back" class="pixonaut-tabs__tab" data-pixonaut-side="back" aria-controls="pixonaut-form-back" aria-selected="false" tabindex="-1">Back print</button>
</div>
`;

/** The strip of views the plugin prints under a side's preview until the SDK draws its own, with the one view of the fake's side. */
export const PLUGIN_VIEWS_STRIP = `
<span class="pixonaut-views__strip">
	<span class="pixonaut-views__view" aria-hidden="true">
		<span class="pixonaut-views__photo"></span>
		<span class="pixonaut-views__label">Front</span>
	</span>
</span>
`;

/**
 * What the plugin sets on a side's frame for the photo it opens on, a
 * landscape one here (`--pixonaut-preview-ratio` and
 * `--pixonaut-preview-aspect`, from the server's reads).
 */
export const PLUGIN_FRAME_STYLE =
	'--pixonaut-preview-ratio: 3 / 2; --pixonaut-preview-aspect: 1.5;';

/** The class the plugin's script puts on a side's frame once the SDK has drawn into it. */
export const PLUGIN_FRAME_DRAWN = 'is-drawn';

/** The photo shapes a side's views are drawn at: a portrait photo, and a landscape one. */
export const SDK_PHOTO_RATIOS = [ 3 / 4, 3 / 2 ] as const;

/**
 * What the SDK draws into a side's preview element of a set height (SDK
 * 0.8.0): a box at the photo's shape, scaled to fit the element and
 * centred in it, with the design centred on it. The SDK sizes the box in
 * pixels from the element's size; this does the same.
 */
export function drawSdkPreview( side: HTMLElement, ratio: number ): void {
	const width = Math.min( side.clientWidth, side.clientHeight * ratio );
	const height = width / ratio;

	side.innerHTML = `
<div data-testid="pixonaut-preview-part" style="position:relative;width:100%;height:100%;">
	<div data-testid="sdk-stage" style="position:absolute;left:50%;top:50%;width:${ width }px;height:${ height }px;transform:translate(-50%,-50%);background:#ececec;">
		<div data-testid="sdk-plate" style="position:absolute;left:12%;right:12%;top:50%;aspect-ratio:1;max-height:76%;transform:translateY(-50%);background:#888;"></div>
	</div>
</div>
`;
}

/**
 * What the plugin's script and the SDK do with the add-to-cart form's own
 * parts in the steps layout, on a product with one side (SDK 0.8.0's
 * `optionsStep` and `lastStepContent`). The script gathers the approval box,
 * the chosen variation's box and every part after the designer's form, but
 * the variation fields, hidden inputs, scripts and templates, into a
 * `.pixonaut-cart-row` right after the form; the SDK moves the variation
 * fields into its first step, and the cart row into its last, under Back.
 * Self-contained, so it can run in the page.
 */
export function drawSdkSteps( form: HTMLElement ): void {
	const variationFields =
		'table.variations, .wp-block-woocommerce-add-to-cart-with-options-variation-selector';
	const kept = `${ variationFields }, input[type="hidden"], script, template`;
	const addToCart = form.closest( 'form' );
	const variations = addToCart?.querySelector( variationFields );
	const row = document.createElement( 'div' );
	const after: Element[] = [];

	for ( let part = form.nextElementSibling; part !== null; part = part.nextElementSibling ) {
		if ( ! part.matches( kept ) && part.querySelector( variationFields ) === null ) {
			after.push( part );
		}
	}

	row.className = 'pixonaut-cart-row';
	for ( const part of [
		addToCart?.querySelector( '.pixonaut-approval' ),
		addToCart?.querySelector( '.single_variation' ),
		...after,
	] ) {
		if ( part ) {
			row.append( part );
		}
	}
	form.after( row );

	const optionsStep = document.createElement( 'div' );
	const lastStep = document.createElement( 'div' );
	const back = document.createElement( 'button' );

	optionsStep.dataset.testid = 'sdk-options-step';
	lastStep.dataset.testid = 'sdk-last-step';
	back.type = 'button';
	back.textContent = 'Back';
	if ( variations ) {
		optionsStep.append( variations );
	}
	lastStep.append( back, row );
	form.prepend( optionsStep );
	form.append( lastStep );
}
