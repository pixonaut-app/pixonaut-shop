/**
 * The chips a variation menu is drawn as under the Use swatches style: one
 * per option WooCommerce's menu holds, in its order, but its first, which
 * offers no value ("Choose an option"). The menu stays the one source of the
 * choice, so the chips are worked out from it again whenever it changes.
 */

/** One `<option>` of a variation menu, as the page holds it. */
export interface MenuOption {
	readonly value: string;
	readonly label: string;
	/** WooCommerce cannot sell it with the other attributes chosen. */
	readonly disabled: boolean;
}

/** One chip: the option it picks, and whether it is the menu's choice. */
export interface SwatchChoice extends MenuOption {
	readonly checked: boolean;
}

export function swatchChoices( options: readonly MenuOption[], selected: string ): SwatchChoice[] {
	return options
		.filter( ( option ) => option.value !== '' )
		.map( ( option ) => ( { ...option, checked: option.value === selected } ) );
}
