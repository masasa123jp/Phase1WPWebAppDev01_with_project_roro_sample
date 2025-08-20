<?php
/**
 * スポンサー詳細エンドポイント。
 *
 * GET ではスポンサーの詳細を取得し、POST（編集）ではスポンサー情報を更新します。
 * 更新操作は管理者権限（manage_options）が必要です。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Sponsor_Detail_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/sponsors/(?P<id>\d+)';

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
                'callback'            => [ self::class, 'get_sponsor' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ self::class, 'update_sponsor' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'id'    => [ 'type' => 'integer', 'required' => true ],
                    'name'  => [ 'type' => 'string',  'required' => false ],
                    'image' => [ 'type' => 'string',  'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * スポンサー情報の取得。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function get_sponsor( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        global $wpdb;
        $id    = (int) $request->get_param( 'id' );
        $table = $wpdb->prefix . 'roro_sponsor';
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT sponsor_id AS id, name, logo_url AS image, website_url, status
                   FROM {$table} WHERE sponsor_id = %d",
                $id
            ),
            ARRAY_A
        );
        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'スポンサーが見つかりません。', 'roro-core' ), [ 'status' => 404 ] );
        }
        return rest_ensure_response( $row );
    }

    /**
     * スポンサー情報を更新する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function update_sponsor( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        global $wpdb;
        $id      = (int) $request->get_param( 'id' );
        $name    = $request->get_param( 'name' );
        $image   = $request->get_param( 'image' );
        $website = $request->get_param( 'website_url' );
        $table   = $wpdb->prefix . 'roro_sponsor';
        // 存在確認
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE sponsor_id = %d", $id ) );
        if ( ! $exists ) {
            return new WP_Error( 'not_found', __( 'スポンサーが見つかりません。', 'roro-core' ), [ 'status' => 404 ] );
        }
        $data  = [];
        $types = [];
        if ( null !== $name ) {
            $data['name'] = sanitize_text_field( $name );
            $types[]      = '%s';
        }
        if ( null !== $image ) {
            $data['logo_url'] = esc_url_raw( $image );
            $types[]          = '%s';
        }
        if ( null !== $website ) {
            $data['website_url'] = esc_url_raw( $website );
            $types[]             = '%s';
        }
        if ( $data ) {
            $wpdb->update( $table, $data, [ 'sponsor_id' => $id ], $types, [ '%d' ] );
        }
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT sponsor_id AS id, name, logo_url AS image, website_url, status FROM {$table} WHERE sponsor_id = %d", $id ),
            ARRAY_A
        );
        return rest_ensure_response( $row );
    }
}
