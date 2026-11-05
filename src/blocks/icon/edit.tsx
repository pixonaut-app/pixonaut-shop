import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __, _x } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

interface Attributes {
	icon: string;
	circle: boolean;
}

interface EditProps {
	attributes: Attributes;
	setAttributes: ( attributes: Partial< Attributes > ) => void;
}

/** Each icon's name in the editor, by the name block.json declares and inc/Blocks/Icons.php draws. */
const LABELS: Record< string, string > = {
	account: _x( 'Account', 'icon', 'pixonaut-shop' ),
	bag: _x( 'Bag', 'icon', 'pixonaut-shop' ),
	search: _x( 'Search', 'icon', 'pixonaut-shop' ),
	menu: _x( 'Menu', 'icon', 'pixonaut-shop' ),
	close: _x( 'Close', 'icon', 'pixonaut-shop' ),
	chevron: _x( 'Chevron', 'icon', 'pixonaut-shop' ),
	info: _x( 'Info', 'icon', 'pixonaut-shop' ),
	check: _x( 'Check', 'icon', 'pixonaut-shop' ),
	truck: _x( 'Truck', 'icon', 'pixonaut-shop' ),
	gift: _x( 'Gift', 'icon', 'pixonaut-shop' ),
	heart: _x( 'Heart', 'icon', 'pixonaut-shop' ),
	star: _x( 'Star', 'icon', 'pixonaut-shop' ),
	leaf: _x( 'Leaf', 'icon', 'pixonaut-shop' ),
	upload: _x( 'Upload', 'icon', 'pixonaut-shop' ),
	clock: _x( 'Clock', 'icon', 'pixonaut-shop' ),
	lock: _x( 'Lock', 'icon', 'pixonaut-shop' ),
	eye: _x( 'Eye', 'icon', 'pixonaut-shop' ),
};

const OPTIONS = metadata.attributes.icon.enum.map( ( name ) => ( {
	value: name,
	label: LABELS[ name ] ?? name,
} ) );

export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<div { ...useBlockProps( { className: attributes.circle ? 'has-circle' : undefined } ) }>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Icon', 'pixonaut-shop' ) }
						value={ attributes.icon }
						options={ OPTIONS }
						onChange={ ( icon: string ) => setAttributes( { icon } ) }
					/>
					<ToggleControl
						label={ __( 'In a circle', 'pixonaut-shop' ) }
						checked={ attributes.circle }
						onChange={ ( circle: boolean ) => setAttributes( { circle } ) }
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
