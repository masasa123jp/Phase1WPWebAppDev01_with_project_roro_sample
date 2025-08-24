<?php
/**
 * RORO Core WP - uninstall handler
 */
declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit; // 直接実行防止
}

// オプションと一部メタを削除（必要に応じて範囲調整）
delete_option('roro_core_settings');

// ユーザーメタ（お気に入り）の全削除
global $wpdb;
/** @var wpdb $wpdb */
$meta_key = 'roro_favorites';
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
        $meta_key
    )
);

// CPT の投稿は残す（運用で不要ならコメントアウト解除）
// $post_ids = get_posts([
//     'post_type'      => 'roro_event',
//     'posts_per_page' => -1,
//     'fields'         => 'ids',
//     'post_status'    => 'any',
// ]);
// foreach ($post_ids as $pid) {
//     wp_delete_post((int)$pid, true);
// }
