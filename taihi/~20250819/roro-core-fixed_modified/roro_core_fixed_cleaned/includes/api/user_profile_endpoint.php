<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/user_profile_endpoint.php
 *
 * ログインユーザーのプロフィールを返すエンドポイント。
 * WPユーザーID・表示名・メール・ロールに加え、roro_identity から customer_id と provider、
 * roro_customer から user_type と consent_status を取得して返します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;

class User_Profile_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/me';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /** ルート登録 */
    public static function register() : void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'handle' ],
                'permission_callback' => [ self::class, 'permission_callback' ],
            ],
        ] );
    }

    /**
     * 認可チェック：ログインユーザーのみ。
     *
     * 親クラスと同じシグネチャに合わせるため、WP_REST_Request を受け取り
     * ログイン状態を返すようにします。
     *
     * @param WP_REST_Request $request
     * @return bool True if the current user is logged in.
     */
    public static function permission_callback( \WP_REST_Request $request ) {
    return parent::permission_callback( $request );
}


    /**
     * ユーザープロフィールを取得して返す。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        $profile = [
            'wp_user_id' => (int) $user->ID,
            'name'       => $user->display_name,
            'email'      => $user->user_email,
            'roles'      => $user->roles,
        ];
        // roro_identity から customer_id と provider を取得
        global $wpdb;
        $id_table = $wpdb->prefix . 'roro_identity';
        $row      = $wpdb->get_row( $wpdb->prepare(
            "SELECT customer_id, provider FROM {$id_table} WHERE wp_user_id = %d",
            $user->ID
        ), ARRAY_A );
        if ( $row ) {
            $profile['customer_id']   = (int) $row['customer_id'];
            $profile['auth_provider'] = $row['provider'];
            // roro_customer から user_type / consent_status を取得
            $cust_table = $wpdb->prefix . 'roro_customer';
            $cust       = $wpdb->get_row( $wpdb->prepare(
                "SELECT user_type, consent_status FROM {$cust_table} WHERE customer_id = %d",
                $row['customer_id']
            ), ARRAY_A );
            if ( $cust ) {
                $profile['user_type']      = $cust['user_type'];
                $profile['consent_status'] = $cust['consent_status'];
            }
        }
        return rest_ensure_response( $profile );
    }
}
