<?php
/**
 * 犬種一覧エンドポイント。
 *
 * roro_dog_breed テーブルから登録されている犬種をすべて取得し、IDと名称のみ返します。
 * 将来的にはカテゴリやサイズなど追加フィールドを公開することが可能です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class Breed_List_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/breeds';

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
     * 犬種一覧を取得する。存在しない場合は空配列を返す。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'roro_dog_breed';
        $rows  = $wpdb->get_results( "SELECT breed_id AS id, name FROM {$table} ORDER BY name", ARRAY_A );
        return rest_ensure_response( $rows ?: [] );
    }
}
