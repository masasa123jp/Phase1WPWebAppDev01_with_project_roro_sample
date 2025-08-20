<?php
/**
 * スポンサー一覧エンドポイント。
 *
 * roro_sponsor テーブルからアクティブなスポンサーを取得し、ID・名前・ロゴURLを返します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Sponsor_List_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/sponsors';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'handle' ],
                'permission_callback' => '__return_true',
            ],
        ] );
    }

    /**
     * スポンサー一覧を取得して返します。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'roro_sponsor';
        $rows  = $wpdb->get_results(
            "SELECT sponsor_id AS id, name, COALESCE(logo_url, '') AS image
               FROM {$table} WHERE status = 'active'
             ORDER BY created_at DESC",
            ARRAY_A
        );
        return rest_ensure_response( $rows ?: [] );
    }
}
