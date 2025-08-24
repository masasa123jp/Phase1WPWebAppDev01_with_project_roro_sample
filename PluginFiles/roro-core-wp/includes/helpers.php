<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * 深い配列まで text フィールド的にサニタイズ
 */
function roro_core_wp_sanitize_deep($value) {
    if (is_array($value)) {
        return array_map('roro_core_wp_sanitize_deep', $value);
    }
    if (is_scalar($value)) {
        return sanitize_text_field((string)$value);
    }
    return $value;
}

/**
 * テーブル存在チェック（DB 抽象）
 */
function roro_core_wp_has_table(string $raw_table): bool {
    global $wpdb;
    $table = $raw_table;
    // フル指定でなければプレフィックス付与（大文字スキーマも想定されるため LIKE でチェック）
    if (stripos($raw_table, $wpdb->prefix) !== 0) {
        $table = $wpdb->prefix . $raw_table;
    }
    $like = $wpdb->esc_like($table);
    $sql  = $wpdb->prepare("SHOW TABLES LIKE %s", $like);
    return (bool) $wpdb->get_var($sql);
}

/**
 * 現在ユーザーの最小限の情報
 */
function roro_core_wp_current_user_payload(): array {
    if (!is_user_logged_in()) {
        return ['id' => 0];
    }
    $u = wp_get_current_user();
    return [
        'id'           => (int)$u->ID,
        'display_name' => (string)$u->display_name,
        'email'        => (string)$u->user_email,
    ];
}

/**
 * ユーザーメタに配列を安全に保存（JSON 化）
 */
function roro_core_wp_update_user_array_meta(int $user_id, string $key, array $arr): bool {
    return (bool) update_user_meta($user_id, $key, wp_json_encode(array_values($arr), JSON_UNESCAPED_UNICODE));
}

/**
 * ユーザーメタから配列を取得（JSON→配列）
 */
function roro_core_wp_get_user_array_meta(int $user_id, string $key): array {
    $json = (string) get_user_meta($user_id, $key, true);
    if ($json === '') return [];
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}
