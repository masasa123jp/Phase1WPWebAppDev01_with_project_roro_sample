import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import './editor.css';

registerBlockType( 'roro/gacha-wheel', {
	edit: () => <button className="roro-spin">{ __( 'Spin!', 'roro-core' ) }</button>,
	save: ()  => <button className="roro-spin">{ __( 'Spin!', 'roro-core' ) }</button>,
} );
