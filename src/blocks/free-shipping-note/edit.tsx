import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

/**
 * The editor has no customer's cart, so the server renders nothing there; the block's title
 * stands in for the note, so a store sees where it sits in the drawer.
 */
function Placeholder() {
	return <p>{ __( 'Free shipping note', 'pixonaut-shop' ) }</p>;
}

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender
				block={ metadata.name }
				attributes={ {} }
				EmptyResponsePlaceholder={ Placeholder }
			/>
		</div>
	);
}
