<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

/**
 * 実テーブルを DROP するかは運用方針次第。
 * ここではオプション/ビューのみ削除、実テーブルは保持（誤削除防止）。
 */
function roro_core_do_uninstall() {
    global $wpdb;
    // 設定削除
    delete_option('roro_core_settings');

    // 互換ビュー削除（存在すれば）
    $views = array(
        'wp_roro_ai_conversation', 'wp_roro_ai_message', 'wp_roro_customer',
        'wp_roro_event_master', 'wp_roro_map_favorite',
        'wp_roro_one_point_advice_master', 'wp_roro_pet', 'wp_roro_travel_spot_master'
    );
    foreach ($views as $v) {
        $wpdb->query("DROP VIEW IF EXISTS `{$v}`");
    }
}
