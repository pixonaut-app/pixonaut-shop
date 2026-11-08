import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { type CategoryAttributes, CategoryControls, NoCategories } from '../category-controls';
import metadata from './block.json';

interface EditProps {
	attributes: CategoryAttributes;
	setAttributes: ( attributes: Partial< CategoryAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	return (
		<div { ...useBlockProps() }>
			<CategoryControls
				attributes={ attributes }
				setAttributes={ setAttributes }
				noneLabel={ __( 'Top-level categories', 'pixonaut-shop' ) }
				headingHelp={ __( 'Empty, the row has no heading.', 'pixonaut-shop' ) }
			/>
			<ServerSideRender
				block={ metadata.name }
				attributes={ { ...attributes } }
				skipBlockSupportAttributes
				EmptyResponsePlaceholder={ NoCategories }
			/>
		</div>
	);
}
