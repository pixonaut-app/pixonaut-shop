/**
 * Heads a headed menu with its menu's name in the Site Editor, as
 * `inc/Blocks/MenuHeading.php` does on the front end: only a navigation block
 * carrying the headed-menu class that names a published menu with a name.
 * The class names come from that PHP class, through `pixonautShopMenuHeading`.
 */
import { createHigherOrderComponent } from '@wordpress/compose';
import { useEntityRecord } from '@wordpress/core-data';
import { addFilter } from '@wordpress/hooks';

const NAVIGATION_BLOCK = 'core/navigation';

/** The post type a navigation block's `ref` names. */
const MENU_POST_TYPE = 'wp_navigation';

const PUBLISHED = 'publish';

const FILTER_NAMESPACE = 'pixonaut-shop/menu-heading';

const config = window.pixonautShopMenuHeading;

/** Whether a block's `className` attribute holds the headed-menu class. */
function isHeaded( className ) {
	return typeof className === 'string' && className.split( /\s+/ ).includes( config.className );
}

/** The menu's title as the store wrote it, whichever form the record holds it in. */
function menuName( record ) {
	const title = record?.title;
	const name = typeof title === 'string' ? title : ( title?.raw ?? title?.rendered ?? '' );

	return name.trim();
}

function HeadedMenu( { BlockEdit, props } ) {
	const { record } = useEntityRecord( 'postType', MENU_POST_TYPE, props.attributes.ref );
	const name = record?.status === PUBLISHED ? menuName( record ) : '';

	if ( name === '' ) {
		return <BlockEdit { ...props } />;
	}

	return (
		<div className={ config.previewClassName }>
			<h2 className={ config.headingClassName }>{ name }</h2>
			<BlockEdit { ...props } />
		</div>
	);
}

const withMenuHeading = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if (
			props.name !== NAVIGATION_BLOCK ||
			! props.attributes.ref ||
			! isHeaded( props.attributes.className )
		) {
			return <BlockEdit { ...props } />;
		}

		return <HeadedMenu BlockEdit={ BlockEdit } props={ props } />;
	},
	'withMenuHeading',
);

if ( config ) {
	addFilter( 'editor.BlockEdit', FILTER_NAMESPACE, withMenuHeading );
}
