import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import './editor.css';

registerBlockType( 'roro/advice-list', {
	edit() {
		return <p>{ __( 'Advice List – preview in front‑end.', 'roro-core' ) }</p>;
	},
	save() {
		return null; // dynamic
	},
} );
