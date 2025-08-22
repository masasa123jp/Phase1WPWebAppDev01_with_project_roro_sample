<?php
/**
 * レポートメール送信用エンドポイント。
 *
 * POSTで report (JSON 文字列) と email を受け取り、その内容を管理者宛および指定メールアドレスに送信します。
 * メール送信後にtrue/falseを返します。メールが送れない場合はエラーを返します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Report_Email_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/report/email';

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
                    'report' => [ 'type' => 'string', 'required' => true ],
                    'email'  => [ 'type' => 'string', 'required' => true ],
                ],
            ],
        ] );
    }

    /**
     * メール送信処理。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
        $report_json = $request->get_param( 'report' );
        $email       = sanitize_email( $request->get_param( 'email' ) );
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'メールアドレスの形式が不正です。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $subject = __( 'RoRo レポート', 'roro-core' );
        $message = $report_json;
        // 管理者にもCCする
        $to      = [ $email, get_option( 'admin_email' ) ];
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        $sent    = wp_mail( $to, $subject, $message, $headers );
        if ( ! $sent ) {
            return new WP_Error( 'mail_failed', __( 'メール送信に失敗しました。', 'roro-core' ), [ 'status' => 500 ] );
        }
        return rest_ensure_response( [ 'sent' => true ] );
    }
}
