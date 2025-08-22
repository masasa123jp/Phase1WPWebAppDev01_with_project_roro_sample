<?php
/**
 * 課題一覧エンドポイント。
 *
 * 登録されている課題マスタを返します。優先度 (priority) の降順、および名称の昇順でソートします。
 * 課題が一つも存在しない場合は空の配列を返します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Issues_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/issues';

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
     * 課題一覧を返します。
     *
     * @param WP_REST_Request $req リクエスト
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $req ) : WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'roro_issue';
        $rows  = $wpdb->get_results( "SELECT issue_id AS id, name, description, priority FROM {$table} ORDER BY priority DESC, name", ARRAY_A );
        return rest_ensure_response( $rows ?: [] );
    }
}
