<?php
/* ================================ 既存設定 ... ================================ */

/* ----------------------- RoRo プロジェクト追加定数 ----------------------- */

/* 1) JWT Authentication for WP REST API
 * セキュリティ確保のため、長い乱数を環境変数 JWT_AUTH_SECRET_KEY から取得する方式に変更しました。
 */
define( 'JWT_AUTH_SECRET_KEY', getenv( 'JWT_AUTH_SECRET_KEY' ) ?: 'CHANGE_ME_TO_LONG_RANDOM_STRING' );
define( 'JWT_AUTH_CORS_ENABLE', true );

/* 2) Firebase Cloud Messaging – サーバキー
 * キーは環境変数 RORO_FCM_SERVER_KEY から取得します。デフォルトでは空文字列を設定します。
 */
define( 'RORO_FCM_SERVER_KEY', getenv( 'RORO_FCM_SERVER_KEY' ) ?: '' );

/* 3) OpenAI Key – AI Advice エンドポイント用
 * キーを直接ハードコーディングせず、環境変数 RORO_OPENAI_KEY から取得します。
 */
define( 'RORO_OPENAI_KEY', getenv( 'RORO_OPENAI_KEY' ) ?: '' );

/* 4) Sentry DSN – PHP 用
 * DSN をハードコーディングしないよう環境変数から取得します。
 */
define( 'RORO_SENTRY_DSN', getenv( 'RORO_SENTRY_DSN' ) ?: '' );

/* 5) キャッシュ・最適化（任意） */
define( 'WP_CACHE', true );
define( 'AUTOSAVE_INTERVAL', 120 );
define( 'WP_POST_REVISIONS', 5 );

/* ----------------------- 必須: 末尾に wp-settings.php ----------------------- */
if ( ! defined( 'ABSPATH' ) ) {
  define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
