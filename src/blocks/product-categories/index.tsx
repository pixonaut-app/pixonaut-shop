import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.css';

// render.php draws the block on the server, so nothing is saved in the post. The server
// registers the rest of block.json, translated, so the client adds only the editor.
registerBlockType( metadata.name, { edit: Edit } as never );
