<?php
/**
 * 広告承認エンドポイント。
 *
 * 管理者が広告IDとステータスを指定して広告を承認または拒否します。ステータス approved は
 * 'active' に、rejected は 'draft' に変換して更新します。結果として更新後の行を返します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Ad_Approval_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/ads/approval';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'handle' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'ad_id'  => [ 'type' => 'integer', 'required' => true ],
                    'status' => [ 'type' => 'string',  'required' => true, 'enum' => [ 'approved', 'rejected' ] ],
                ],
            ],
        ] );
    }

    /**
     * 広告の承認／拒否処理を実行する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        global $wpdb;
        $ad_id  = (int) $request->get_param( 'ad_id' );
        $status = $request->get_param( 'status' );
        $table  = $wpdb->prefix . 'roro_ad';
        // 存在確認
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ad_id = %d", $ad_id ) );
        if ( ! $exists ) {
            return new WP_Error( 'not_found', __( '広告が見つかりません。', 'roro-core' ), [ 'status' => 404 ] );
        }
        $new_status = ( 'approved' === $status ) ? 'active' : 'draft';
        $wpdb->update( $table, [ 'status' => $new_status ], [ 'ad_id' => $ad_id ], [ '%s' ], [ '%d' ] );
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT ad_id AS id, sponsor_id, title, status FROM {$table} WHERE ad_id = %d", $ad_id ),
            ARRAY_A
        );
        return rest_ensure_response( $row );
    }
}
