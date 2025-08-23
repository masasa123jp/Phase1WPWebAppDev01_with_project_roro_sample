<?php
declare(strict_types=1);

/**
 * アンインストールフックでのみ実行される想定。
 * IDE 偽陽性対策として function_exists() で防御。
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    // 直接叩かれた場合は何もしない（安全のため終了）
    exit;
}

// プラグインのオプションを削除（存在する場合のみ）
if (function_exists('delete_option')) {
    delete_option('roro_core_settings');
}
