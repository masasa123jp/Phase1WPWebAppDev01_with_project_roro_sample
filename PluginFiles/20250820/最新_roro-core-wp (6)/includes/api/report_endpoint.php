<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/report_endpoint.php
 *
 * レポート保存エンドポイント。
 * ペットの状態レポートを JSON 形式で受け取り、roro_report テーブルに保存します。
 * 必須フィールド (breed_id, age_month) が欠けていないか検証します。ユーザーは認証済みである必要があります。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Report_Endpoint {
    /** @var string テーブル名 */
    private string $table;

    public function __construct( \wpdb $wpdb ) {
        $this->table = $wpdb->prefix . 'roro_report';
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * ルート登録。
     */
    public function register(): void {
        register_rest_route(
            'roro/v1',
            '/report',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save' ],
                'permission_callback' => [ $this, 'can_submit' ],
                'args'                => [
                    'content' => [ 'type' => 'object', 'required' => true ],
                ],
            ]
        );
    }

    /**
     * 認可：ログイン済みかつnonce検証。
     */
    public function can_submit(): bool {
        return is_user_logged_in() && wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'wp_rest' );
    }

    /**
     * レポートを保存する。
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public function save( WP_REST_Request $req ) {
        global $wpdb;
        $wp_user_id     = get_current_user_id();
        $identity_table = $wpdb->prefix . 'roro_identity';
        $customer_id    = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT customer_id FROM {$identity_table} WHERE wp_user_id = %d", $wp_user_id )
        );
        if ( ! $customer_id ) {
            return new WP_Error( 'no_customer', __( 'カスタマーが見つかりません。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $content = $req->get_param( 'content' );
        if ( ! is_array( $content ) ) {
            return new WP_Error( 'invalid_content', __( 'content はオブジェクト形式である必要があります。', 'roro-core' ), [ 'status' => 400 ] );
        }
        if ( empty( $content['breed_id'] ) || empty( $content['age_month'] ) ) {
            return new WP_Error( 'missing_fields', __( 'breed_id と age_month が必要です。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $json_content = wp_json_encode( $content );
        if ( false === $json_content ) {
            return new WP_Error( 'invalid_json', __( 'JSON への変換に失敗しました。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $wpdb->insert( $this->table, [
            'customer_id' => $customer_id,
            'content'     => $json_content,
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d','%s','%s' ] );
        return rest_ensure_response( [ 'report_id' => (int) $wpdb->insert_id ] );
    }
}
