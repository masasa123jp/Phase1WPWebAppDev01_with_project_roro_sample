<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package RoroCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/* 独自テーブル削除 */
$tables = [ 'roro_photo', 'roro_gacha_log', 'roro_revenue' ];
foreach ( $tables as $t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$t}" );
}

/* オプション削除 */
$options = [ 'roro_maps_key', 'roro_adsense_id', 'roro_gacha_table' ];
foreach ( $options as $opt ) {
	delete_option( $opt );
}

/* ユーザーメタ削除 */
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'roro_%'" );
