<?php
/**
 * 決済エンドポイント。
 *
 * スポンサーへの請求データを管理します。管理者が決済履歴の一覧取得および新規登録を行います。
 * スポンサーが存在しない場合はエラーとなります。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Payment_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/payments';

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
                'callback'            => [ self::class, 'get_payments' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ self::class, 'record_payment' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'sponsor_id' => [ 'type' => 'integer', 'required' => true ],
                    'amount'     => [ 'type' => 'number',  'required' => true ],
                ],
            ],
        ] );
    }

    /**
     * 決済履歴を取得する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_payments( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'roro_payment';
        $rows  = $wpdb->get_results(
            "SELECT payment_id AS id, sponsor_id, customer_id, amount, method, status, created_at
               FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        );
        return rest_ensure_response( $rows ?: [] );
    }

    /**
     * 新規決済を記録する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function record_payment( WP_REST_Request $request ) : WP_REST_Response {
        global $wpdb;
        $sponsor_id = (int) $request->get_param( 'sponsor_id' );
        $amount     = (float) $request->get_param( 'amount' );
        // スポンサー存在チェック
        $sponsor_table = $wpdb->prefix . 'roro_sponsor';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sponsor_table} WHERE sponsor_id = %d", $sponsor_id ) );
        if ( ! $exists ) {
            return new WP_Error( 'invalid_sponsor', __( '指定されたスポンサーが存在しません。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $table = $wpdb->prefix . 'roro_payment';
        $wpdb->insert( $table, [
            'customer_id'    => null,
            'sponsor_id'     => $sponsor_id,
            'method'         => 'credit',
            'amount'         => $amount,
            'status'         => 'succeeded',
            'transaction_id' => null,
            'created_at'     => current_time( 'mysql' ),
        ], [ '%d', '%d', '%s', '%f', '%s', '%s', '%s' ] );
        $id  = $wpdb->insert_id;
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT payment_id AS id, sponsor_id, customer_id, amount, method, status, created_at FROM {$table} WHERE payment_id = %d", $id ),
            ARRAY_A
        );
        return rest_ensure_response( $row );
    }
}
