<?php
// 設定値を削除（プラグイン削除時のみ）
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'roro_chatbot_welcome' );
