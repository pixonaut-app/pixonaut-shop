/**
 * An archive's cards as Pixonaut for WooCommerce publishes them, for the
 * archive to filter, sort and count in the browser. The page names the
 * index it belongs to (`ArchiveIndex` on the server); the shape is the
 * plugin's, plus the fields this theme adds to each card and variation
 * while the plugin builds it (`inc/Archive/ArchiveIndexFields.php`).
 */

/** The page's pointer to its index. */
export interface IndexPointer {
	url: string;
	version: string;
	count: number;
}

export interface Currency {
	code: string;
	symbol: string;
	/** WooCommerce's currency position: `left`, `right`, `left_space` or `right_space`. */
	position: string;
	decimals: number;
	decimalSeparator: string;
	thousandSeparator: string;
}

export interface FilterValue {
	/** The value's slug, as the address names it. */
	id: string;
	label: string;
	order: number;
}

export interface Filter {
	/** The name the address gives the filter. */
	key: string;
	label: string;
	type: 'attribute' | 'price';
	values?: FilterValue[];
	/** For an attribute that applies only under some values of another: those values. */
	parent?: { key: string; values: string[] };
}

export interface Sort {
	/** WooCommerce's `orderby` value. */
	key: string;
	label: string;
}

export interface Picture {
	src: string;
	srcset: string;
	width: number;
	height: number;
}

export interface Variation {
	/**
	 * The values of each attribute the variation can be bought in, by the
	 * filter's key: several for an attribute it leaves open, none for one
	 * that does not apply to it. A key that is missing is an attribute the
	 * product does not have.
	 */
	values: Record< string, string[] >;
	/** The price the shop displays. */
	price: number;
	/** The regular price the shop displays; above `price` while it is on sale. */
	regularPrice: number;
	/** The highest price, for a product whose one offer spans a range. */
	priceMax?: number;
	/** The lowest price in the 30 days before a reduction, where the shop shows one. */
	lowestPrice?: number;
	/** The picture of the filtered combination, an index into `images`. */
	image?: number;
	/** The picture a card opening on this variation shows otherwise while a filter is on, an index into `images`. */
	openImage?: number;
}

export interface Card {
	id: string;
	productId: number;
	title: string;
	url: string;
	/** The start of the product's short description, as plain text. */
	summary: string;
	image: Picture & { alt: string };
	/** The picture shown while the card is hovered, if it has one. */
	secondImage?: Picture;
	/** The card's place in each sort, lowest first. */
	sort: Record< string, number >;
	/** The card's own values, for a card of a split product; none for a product shown as itself. */
	holds: Record< string, string[] >;
	/** The attributes any card of this card's product holds. */
	listedBy: string[];
	/** The attributes the card holds that list it only while a filter is on them. */
	onlyFiltered: string[];
	/** The attributes the product's page lets a shopper choose: its variation attributes. */
	choices: string[];
	/** What the card sells, in the order the server prefers to open it on. */
	variations: Variation[];
}

export interface ArchiveIndex {
	version: string;
	currency: Currency;
	filters: Filter[];
	sorts: Sort[];
	cards: Card[];
	images: Picture[];
}
