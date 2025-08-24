<?php
/**
 * アンインストール時の処理
 * - ここでは本プラグインが保持するオプションを削除する
 * - SQLテーブルなどは作成していないため削除対象はオプションのみ
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit; // 直アクセス防止
}

// 管理者権限チェックは WordPress 側で済んでいる前提
// 本プラグインのオプションを削除
delete_option('roro_auth_force_all'); // 「全ユーザーに2FAを強制」設定
