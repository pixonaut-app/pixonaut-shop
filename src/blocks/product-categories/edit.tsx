import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

/** The values block.json's `layout` enum allows. */
type Layout = 'tiles' | 'buttons';

/** The values block.json's `orderBy` enum allows. */
type OrderBy = 'count' | 'order';

interface Attributes {
	layout: Layout;
	/** Unset shows the layout's own number, which inc/Blocks/ProductCategories.php holds. */
	number?: number;
	orderBy: OrderBy;
}

interface EditProps {
	attributes: Attributes;
	setAttributes: ( attributes: Partial< Attributes > ) => void;
}

/** The most categories a store picks: three rows of tiles at wide size. */
const MAX_NUMBER = 12;

export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Layout', 'pixonaut-shop' ) }
						value={ attributes.layout }
						options={ [
							{ value: 'tiles', label: __( 'Tiles', 'pixonaut-shop' ) },
							{ value: 'buttons', label: __( 'Buttons', 'pixonaut-shop' ) },
						] }
						onChange={ ( layout: Layout ) => setAttributes( { layout } ) }
					/>
					<RangeControl
						__next40pxDefaultSize
						label={ __( 'Number of categories', 'pixonaut-shop' ) }
						value={ attributes.number }
						min={ 1 }
						max={ MAX_NUMBER }
						allowReset
						onChange={ ( number?: number ) => setAttributes( { number } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Order by', 'pixonaut-shop' ) }
						value={ attributes.orderBy }
						options={ [
							{ value: 'count', label: __( 'Product count', 'pixonaut-shop' ) },
							{ value: 'order', label: __( 'Category order', 'pixonaut-shop' ) },
						] }
						onChange={ ( orderBy: OrderBy ) => setAttributes( { orderBy } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ metadata.name }
				attributes={ { ...attributes } }
				skipBlockSupportAttributes
			/>
		</div>
	);
}
