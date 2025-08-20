<?php
/**
 * お問い合わせエンドポイント。
 *
 * お問い合わせフォームから送信されたデータを管理者にメールで届け、同時にデータベースに保存します。
 * メール送信に失敗した場合はエラーレスポンスを返します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Contact_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/contact';

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
                'permission_callback' => '__return_true',
                'args'                => [
                    'name'    => [ 'type' => 'string', 'required' => true ],
                    'email'   => [ 'type' => 'string', 'required' => true ],
                    'message' => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );
    }

    /**
     * お問い合わせを処理し、メール送信後データを保存する。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        global $wpdb;
        $name    = sanitize_text_field( $request->get_param( 'name' ) );
        $email   = sanitize_email( $request->get_param( 'email' ) );
        $message = wp_kses_post( $request->get_param( 'message' ) );
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'メールアドレスが不正です。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $subject = sprintf( __( 'お問い合わせ: %s 様', 'roro-core' ), $name );
        $body    = "お名前: {$name}\nメールアドレス: {$email}\n\n{$message}";
        // メール送信
        $sent = wp_mail( get_option( 'admin_email' ), $subject, $body );
        if ( ! $sent ) {
            return new WP_Error( 'mail_failed', __( 'メール送信に失敗しました。', 'roro-core' ), [ 'status' => 500 ] );
        }
        // roro_contact に保存（ログインユーザーの場合は customer_id を紐づけ）
        $customer_id = null;
        $user_id     = get_current_user_id();
        if ( $user_id ) {
            $id_table = $wpdb->prefix . 'roro_identity';
            $customer_id = $wpdb->get_var( $wpdb->prepare( "SELECT customer_id FROM {$id_table} WHERE wp_user_id = %d", $user_id ) );
        }
        $table = $wpdb->prefix . 'roro_contact';
        $wpdb->insert( $table, [
            'customer_id' => $customer_id,
            'name'        => $name,
            'email'       => $email,
            'subject'     => '',
            'message'     => $message,
            'status'      => 'new',
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );
        return rest_ensure_response( [ 'success' => true ] );
    }
}
