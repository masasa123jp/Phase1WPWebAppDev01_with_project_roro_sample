<?php
/**
 * 施設データベースエンドポイント。
 *
 * 管理者向けに施設の一覧取得と新規登録機能を提供します。カテゴリの検証を行い、
 * roro_facility テーブルに登録します。今後、施設タイプごとに拡張可能です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Facility_DB_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/facilities/db';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'list_facilities' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ self::class, 'create_facility' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
                'args' => [
                    'name'     => [ 'type' => 'string', 'required' => true ],
                    'category' => [ 'type' => 'string', 'required' => true ],
                    'address'  => [ 'type' => 'string', 'required' => true ],
                    'lat'      => [ 'type' => 'number', 'required' => false ],
                    'lng'      => [ 'type' => 'number', 'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * 施設一覧を返す。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function list_facilities( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $fac_table = $wpdb->prefix . 'roro_facility';
        $rows = $wpdb->get_results(
            "SELECT facility_id AS id, name, category, address, phone, lat, lng, created_at
               FROM {$fac_table}
             ORDER BY facility_id",
            ARRAY_A
        );
        return rest_ensure_response( $rows ?: [] );
    }

    /**
     * 施設を新規登録する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function create_facility( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $name     = sanitize_text_field( $request->get_param( 'name' ) );
        $category = sanitize_text_field( $request->get_param( 'category' ) );
        $address  = sanitize_text_field( $request->get_param( 'address' ) );
        $lat      = $request->get_param( 'lat' ) !== null ? (float) $request->get_param( 'lat' ) : null;
        $lng      = $request->get_param( 'lng' ) !== null ? (float) $request->get_param( 'lng' ) : null;
        // 許可されたカテゴリ一覧
        $allowed = [ 'cafe','hospital','salon','park','hotel','school','store' ];
        if ( ! in_array( $category, $allowed, true ) ) {
            return new WP_Error( 'invalid_category', __( '無効な施設カテゴリです。', 'roro-core' ), [ 'status' => 400 ] );
        }
        // 挿入
        $fac_table = $wpdb->prefix . 'roro_facility';
        $wpdb->insert( $fac_table, [
            'name'       => $name,
            'category'   => $category,
            'address'    => $address,
            'lat'        => $lat,
            'lng'        => $lng,
            'phone'      => '',
            'created_at' => current_time( 'mysql' ),
        ], [ '%s','%s','%s','%f','%f','%s','%s' ] );
        return rest_ensure_response( [
            'id'       => (int) $wpdb->insert_id,
            'name'     => $name,
            'category' => $category,
            'address'  => $address,
            'lat'      => $lat,
            'lng'      => $lng,
        ] );
    }
}
