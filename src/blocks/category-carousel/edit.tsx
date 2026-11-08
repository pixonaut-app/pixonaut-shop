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
	const noneLabel = __( 'Top-level categories', 'pixonaut-shop' );
	const unknown = useUnknownCategory( attributes.parent );
	const empty =
		'' === attributes.parent
			? __( 'No category has products yet.', 'pixonaut-shop' )
			: __( 'No subcategory here has products yet.', 'pixonaut-shop' );

	return (
		<div { ...useBlockProps() }>
			<CategoryControls
				attributes={ attributes }
				setAttributes={ setAttributes }
				noneLabel={ noneLabel }
				headingHelp={ __( 'Empty, the row has no heading.', 'pixonaut-shop' ) }
			/>
			{ unknown ? (
				<CategoryPlaceholder
					label={ __( 'Category carousel', 'pixonaut-shop' ) }
					instructions={ __(
						'Choose the category whose subcategories show in the row, or the top-level categories.',
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
					EmptyResponsePlaceholder={ emptyMessage( empty ) }
				/>
			) }
		</div>
	);
}
