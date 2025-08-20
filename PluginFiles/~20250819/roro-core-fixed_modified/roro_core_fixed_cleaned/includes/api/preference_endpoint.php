<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/preference_endpoint.php
 *
 * 通知設定エンドポイント。
 * 現在の通知設定を取得および更新する。更新時にはカテゴリ別メール通知、プッシュ通知、およびトークン有効期限も保存する。
 * すべての操作にはログインとnonce検証が必要です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class Preference_Endpoint {
    private const META_KEY = 'roro_notification_pref';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * ルート登録。
     */
    public function register_routes(): void {
        register_rest_route(
            'roro/v1',
            '/preference',
            [
                'methods'             => [ WP_REST_Server::READABLE, WP_REST_Server::EDITABLE ],
                'callback'            => [ $this, 'handle' ],
                'permission_callback' => function () {
                    return is_user_logged_in() && wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'wp_rest' );
                },
            ]
        );
    }

    /**
     * 通知設定取得・更新処理。
     * GETは現在の設定を返し、POSTは更新を行う。
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public function handle( WP_REST_Request $req ): WP_REST_Response {
        $user_id = get_current_user_id();
        if ( 'GET' === $req->get_method() ) {
            return rest_ensure_response( get_user_meta( $user_id, self::META_KEY, true ) ?: [] );
        }
        // 更新処理
        $body     = $req->get_json_params();
        $defaults = [
            'line'             => false,
            'email'            => false,
            'fcm'              => false,
            'category_email_on'=> true,
            'category_push_on' => true,
            'token_expires_at' => null,
        ];
        $value = wp_parse_args( $body, $defaults );
        update_user_meta( $user_id, self::META_KEY, $value );
        return rest_ensure_response( [ 'success' => true ] );
    }
}
