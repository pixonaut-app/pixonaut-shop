/**
 * A variable product's attributes as chips, in an add-to-cart form block
 * with the Use swatches style (`inc/Product/VariationSwatches.php`).
 *
 * Each of WooCommerce's variation menus keeps the choice: it is hidden, and a
 * radio group of chips drawn after it in its place. Picking a chip picks the
 * menu's option and sends the menu `change`, as choosing it there would;
 * whenever the menu changes, by a pick, WooCommerce narrowing its options or
 * its Clear link, the chips are drawn again from it. WooCommerce clears the
 * menus through jQuery, whose `change` reaches only listeners bound through
 * jQuery, so that is how the menu is followed where jQuery is loaded. After
 * Clear, WooCommerce hands focus to the form's first menu, hidden here, so the
 * first menu's chips take it instead.
 */
import { type MenuOption, swatchChoices } from './swatch-choices.ts';

/** WooCommerce's variation menus, in a form with the style. */
const MENUS = '.is-style-swatches form.variations_form table.variations select';

/** A variation form's menus, of which WooCommerce focuses the first after Clear. */
const FORM_MENUS = 'table.variations select';

/** The event WooCommerce sends the form, through jQuery, when Clear wants the first menu focused. */
const RESET_FOCUS_EVENT = 'reset_focus';

const GROUP_CLASS = 'pixonaut-shop-swatches';
const CHIP_CLASS = 'pixonaut-shop-swatch';

/** What the radios are named after the menu's own name, so they never reach the cart as an attribute. */
const RADIO_NAME_PREFIX = 'pixonaut_shop_swatch_';

type JQueryLike = ( element: Element ) => { on( event: string, handler: () => void ): unknown };

declare global {
	interface Window {
		jQuery?: JQueryLike;
	}
}

function menuOptions( menu: HTMLSelectElement ): MenuOption[] {
	return Array.from( menu.options, ( option ) => ( {
		value: option.value,
		label: option.text,
		disabled: option.disabled,
	} ) );
}

function pick( menu: HTMLSelectElement, value: string ): void {
	menu.value = value;
	menu.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

/** Focuses the chosen chip, or with none chosen the first that can be chosen. */
function focusChip( group: HTMLElement ): void {
	(
		group.querySelector< HTMLInputElement >( 'input:checked' ) ??
		group.querySelector< HTMLInputElement >( 'input:not(:disabled)' )
	)?.focus();
}

function draw( menu: HTMLSelectElement, group: HTMLElement ): void {
	const hadFocus = group.contains( document.activeElement );
	const name = RADIO_NAME_PREFIX + menu.name;

	group.replaceChildren(
		...swatchChoices( menuOptions( menu ), menu.value ).map( ( choice ) => {
			const chip = document.createElement( 'label' );
			const radio = document.createElement( 'input' );
			const text = document.createElement( 'span' );

			chip.className = CHIP_CLASS;
			radio.type = 'radio';
			radio.className = `${ CHIP_CLASS }__input`;
			radio.name = name;
			radio.value = choice.value;
			radio.checked = choice.checked;
			radio.disabled = choice.disabled;
			radio.addEventListener( 'change', () => pick( menu, choice.value ) );
			text.className = `${ CHIP_CLASS }__label`;
			text.textContent = choice.label;
			chip.append( radio, text );

			return chip;
		} ),
	);

	if ( hadFocus ) {
		focusChip( group );
	}
}

function enhance( menu: HTMLSelectElement ): void {
	const group = document.createElement( 'div' );
	const label = menu.id
		? document.querySelector( `label[for="${ CSS.escape( menu.id ) }"]` )
		: null;

	group.className = GROUP_CLASS;
	group.setAttribute( 'role', 'radiogroup' );

	if ( label ) {
		label.id ||= `${ menu.id }-label`;
		group.setAttribute( 'aria-labelledby', label.id );
	}

	menu.hidden = true;
	menu.after( group );

	const redraw = () => draw( menu, group );

	if ( window.jQuery ) {
		window.jQuery( menu ).on( 'change', redraw );

		const form = menu.form;

		if ( form?.querySelector( FORM_MENUS ) === menu ) {
			window.jQuery( form ).on( RESET_FOCUS_EVENT, () => focusChip( group ) );
		}
	} else {
		menu.addEventListener( 'change', redraw );
	}

	new MutationObserver( redraw ).observe( menu, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: [ 'disabled' ],
	} );
	redraw();
}

document.querySelectorAll< HTMLSelectElement >( MENUS ).forEach( enhance );
