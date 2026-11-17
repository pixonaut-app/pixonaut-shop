/**
 * Whether an answer is an archive index the browser can filter with: every
 * field the archive reads has its type, on every card and variation. One
 * that is not leaves the archive as the server rendered it.
 */

import type { ArchiveIndex } from './types.ts';

type Shape = Record< string, unknown >;

function isObject( value: unknown ): value is Shape {
	return typeof value === 'object' && value !== null && ! Array.isArray( value );
}

function isStrings( value: unknown ): value is string[] {
	return Array.isArray( value ) && value.every( ( item ) => typeof item === 'string' );
}

function isNumber( value: unknown ): value is number {
	return typeof value === 'number' && Number.isFinite( value );
}

function isOptionalNumber( value: unknown ): boolean {
	return value === undefined || isNumber( value );
}

function isValuesByKey( value: unknown ): boolean {
	return isObject( value ) && Object.values( value ).every( isStrings );
}

function isPicture( value: unknown ): boolean {
	return (
		isObject( value ) &&
		typeof value.src === 'string' &&
		isNumber( value.width ) &&
		isNumber( value.height )
	);
}

function isVariation( value: unknown ): boolean {
	return (
		isObject( value ) &&
		isValuesByKey( value.values ) &&
		isNumber( value.price ) &&
		isNumber( value.regularPrice ) &&
		isOptionalNumber( value.priceMax ) &&
		isOptionalNumber( value.lowestPrice ) &&
		isOptionalNumber( value.image ) &&
		isOptionalNumber( value.openImage )
	);
}

function isCard( value: unknown ): boolean {
	return (
		isObject( value ) &&
		typeof value.id === 'string' &&
		isNumber( value.productId ) &&
		typeof value.title === 'string' &&
		typeof value.url === 'string' &&
		isPicture( value.image ) &&
		isObject( value.sort ) &&
		isValuesByKey( value.holds ) &&
		isStrings( value.listedBy ) &&
		isStrings( value.onlyFiltered ) &&
		isStrings( value.choices ) &&
		Array.isArray( value.variations ) &&
		value.variations.every( isVariation )
	);
}

function isFilter( value: unknown ): boolean {
	return (
		isObject( value ) &&
		typeof value.key === 'string' &&
		typeof value.label === 'string' &&
		( value.type === 'attribute' || value.type === 'price' ) &&
		( value.values === undefined ||
			( Array.isArray( value.values ) &&
				value.values.every(
					( item ) =>
						isObject( item ) && typeof item.id === 'string' && typeof item.label === 'string',
				) ) ) &&
		( value.parent === undefined ||
			( isObject( value.parent ) &&
				typeof value.parent.key === 'string' &&
				isStrings( value.parent.values ) ) )
	);
}

export function isArchiveIndex( data: unknown ): data is ArchiveIndex {
	return (
		isObject( data ) &&
		isObject( data.currency ) &&
		isNumber( data.currency.decimals ) &&
		typeof data.currency.decimalSeparator === 'string' &&
		typeof data.currency.thousandSeparator === 'string' &&
		Array.isArray( data.filters ) &&
		data.filters.every( isFilter ) &&
		Array.isArray( data.sorts ) &&
		Array.isArray( data.images ) &&
		data.images.every( isPicture ) &&
		Array.isArray( data.cards ) &&
		data.cards.every( isCard )
	);
}
