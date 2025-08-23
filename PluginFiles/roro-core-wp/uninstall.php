<?php
/**
 * RORO Core WP - Uninstall
 * プラグイン削除時のクリーンアップ。
 */

declare(strict_types=1);

// 直アクセス防止（WP のアンインストール経由のみ許可）
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 追加したオプションを削除
delete_option('roro_core_settings');
