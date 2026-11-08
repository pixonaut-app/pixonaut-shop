import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import {
	type CategoryAttributes,
	CategoryControls,
	CategoryPlaceholder,
	emptyMessage,
	useUnknownCategory,
} from '../category-controls';
import metadata from './block.json';

interface EditProps {
	attributes: CategoryAttributes;
	setAttributes: ( attributes: Partial< CategoryAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const noneLabel = __( 'Choose a category', 'pixonaut-shop' );
	const unknown = useUnknownCategory( attributes.parent );

	return (
		<div { ...useBlockProps() }>
			<CategoryControls
				attributes={ attributes }
				setAttributes={ setAttributes }
				noneLabel={ noneLabel }
				headingHelp={ __( "Empty, the heading is the category's name.", 'pixonaut-shop' ) }
			/>
			{ '' === attributes.parent || unknown ? (
				<CategoryPlaceholder
					label={ __( 'Group tiles', 'pixonaut-shop' ) }
					instructions={ __(
						'Choose the category whose subcategories show as tiles, such as an occasion.',
						'pixonaut-shop',
					) }
					attributes={ attributes }
					setAttributes={ setAttributes }
					noneLabel={ noneLabel }
				/>
			) : (
				<ServerSideRender
					block={ metadata.name }
					attributes={ { ...attributes } }
					skipBlockSupportAttributes
					EmptyResponsePlaceholder={ emptyMessage(
						__( 'No subcategory here has products yet.', 'pixonaut-shop' ),
					) }
				/>
			) }
		</div>
	);
}
