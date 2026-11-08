import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * The editor's controls the group tiles and the category carousel share: which category's
 * children show, picked by slug so a block keeps its choice when a page moves between stores,
 * and the heading above them.
 */

export interface CategoryAttributes {
	/** The slug of the category whose children show; empty for none. */
	parent: string;
	heading: string;
}

interface Category {
	id: number;
	name: string;
	slug: string;
	parent: number;
}

/** The part of core-data's store this file reads. */
interface CoreSelectors {
	getEntityRecords: (
		kind: string,
		name: string,
		query: Record< string, unknown >,
	) => Category[] | null;
}

interface Option {
	value: string;
	label: string;
}

/** WooCommerce's product category taxonomy, which its REST API shows to the editor. */
const CATEGORY_TAXONOMY = 'product_cat';

/** The `parent` of a top-level category. */
const TOP_LEVEL = 0;

/** Asks the REST API for every category at once rather than a page of them. */
const ALL = -1;

/** Before a subcategory's name, once per level below the top. */
const LEVEL_MARK = '— ';

/** Every product category, each under its parent and marked by its depth, in name order. */
export function useCategoryOptions(): Option[] {
	const categories = useSelect(
		( select ) =>
			( select( 'core' ) as unknown as CoreSelectors ).getEntityRecords(
				'taxonomy',
				CATEGORY_TAXONOMY,
				{ per_page: ALL, _fields: 'id,name,slug,parent', orderby: 'name' },
			),
		[],
	);

	const options: Option[] = [];
	const add = ( parent: number, depth: number ) => {
		for ( const category of categories ?? [] ) {
			if ( category.parent === parent ) {
				options.push( {
					value: category.slug,
					label: LEVEL_MARK.repeat( depth ) + category.name,
				} );
				add( category.id, depth + 1 );
			}
		}
	};
	add( TOP_LEVEL, 0 );

	return options;
}

interface CategoryControlsProps {
	attributes: CategoryAttributes;
	setAttributes: ( attributes: Partial< CategoryAttributes > ) => void;
	/** The choice for no category, and what the block shows then. */
	noneLabel: string;
	/** Says what an empty heading shows. */
	headingHelp: string;
}

/** The category picker on its own, for the sidebar and a block's placeholder. */
export function CategoryPicker( {
	attributes,
	setAttributes,
	noneLabel,
}: Omit< CategoryControlsProps, 'headingHelp' > ) {
	const options = useCategoryOptions();

	return (
		<SelectControl
			__next40pxDefaultSize
			label={ __( 'Category', 'pixonaut-shop' ) }
			help={ __( 'Its subcategories with products show.', 'pixonaut-shop' ) }
			value={ attributes.parent }
			options={ [ { value: '', label: noneLabel }, ...options ] }
			onChange={ ( parent: string ) => setAttributes( { parent } ) }
		/>
	);
}

export function CategoryControls( props: CategoryControlsProps ) {
	const { attributes, setAttributes, headingHelp } = props;

	return (
		<InspectorControls>
			<PanelBody>
				<CategoryPicker { ...props } />
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Heading', 'pixonaut-shop' ) }
					help={ headingHelp }
					value={ attributes.heading }
					onChange={ ( heading: string ) => setAttributes( { heading } ) }
				/>
			</PanelBody>
		</InspectorControls>
	);
}

/** What the editor shows where the server renders nothing. */
export function NoCategories() {
	return <p>{ __( 'No subcategory here has products yet.', 'pixonaut-shop' ) }</p>;
}
