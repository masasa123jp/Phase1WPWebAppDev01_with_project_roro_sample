<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/auth/auth_service.php
 *
 * Firebase と LINE の認証を提供するサービス。ユーザーを WordPress アカウントおよび RoRo 顧客テーブルに登録し、ログインを行います。
 * Phase 1.6では provider 列の拡張、auth_provider/user_type/consent_status の登録やUIDによるマッピングを強化しました。
 *
 * @package RoroCore\Auth
 */

namespace RoroCore\Auth;

use WP_REST_Controller;
use WP_REST_Request;
use wpdb;
use WP_Error;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;

class Auth_Service extends WP_REST_Controller {
    /** @var wpdb */
    private wpdb $db;
    /** @var FirebaseAuth|null */
    private ?FirebaseAuth $auth = null;

    public function __construct() {
        global $wpdb;
        $this->db        = $wpdb;
        $this->namespace = 'roro/v1';
        $this->rest_base = 'auth';
        // Firebase初期化
        $service_account_path = apply_filters( 'roro_core_service_account_path', RORO_CORE_DIR . 'credentials/service-account.json' );
        if ( file_exists( $service_account_path ) ) {
            try {
                $this->auth = ( new Factory )->withServiceAccount( $service_account_path )->createAuth();
            } catch ( \Throwable $e ) {
                error_log( 'RoRo Core: Firebase initialization failed – ' . $e->getMessage() );
                $this->auth = null;
            }
        }
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /** ルート登録 */
    public function register_routes() : void {
        // Firebase認証
        register_rest_route( $this->namespace, "/{$this->rest_base}/firebase", [
            'methods'             => 'POST',
            'callback'            => [ $this, 'firebase_login' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'idToken' => [
                    'type'        => 'string',
                    'required'    => true,
                    'description' => __( 'クライアントSDKから取得したFirebase IDトークン。', 'roro-core' ),
                ],
            ],
        ] );
        // LINE認証
        register_rest_route( $this->namespace, "/{$this->rest_base}/line", [
            'methods'             => 'POST',
            'callback'            => [ $this, 'line_login' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'accessToken' => [
                    'type'        => 'string',
                    'required'    => true,
                    'description' => __( 'LIFFログインで取得したLINEアクセストークン。', 'roro-core' ),
                ],
            ],
        ] );
    }

    /**
     * Firebase IDトークンを検証してログインする。
     */
    public function firebase_login( WP_REST_Request $req ) {
        if ( empty( $this->auth ) ) {
            return new WP_Error( 'auth_disabled', __( 'Firebase認証が構成されていません。', 'roro-core' ), [ 'status' => 500 ] );
        }
        $token = $req->get_param( 'idToken' );
        try {
            $verified = $this->auth->verifyIdToken( $token );
            $claims   = $verified->claims();
            $uid      = $claims->get( 'sub' );
            $email    = $claims->get( 'email' );
            $provider = $claims->get( 'firebase' )['sign_in_provider'] ?? 'firebase';
            $name     = $claims->get( 'name', '' );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'auth_fail', $e->getMessage(), [ 'status' => 401 ] );
        }
        return $this->finalize( $uid, $provider, $email, $name );
    }

    /**
     * LINEアクセストークンを検証してログインする。
     */
    public function line_login( WP_REST_Request $req ) {
        $token = $req->get_param( 'accessToken' );
        // 1. トークン検証
        $verify_response = wp_remote_get( 'https://api.line.me/oauth2/v2.1/verify?access_token=' . urlencode( $token ) );
        $verify_body     = json_decode( wp_remote_retrieve_body( $verify_response ), true );
        if ( empty( $verify_body['client_id'] ) ) {
            return new WP_Error( 'line_verify_failed', __( 'LINEトークン検証に失敗しました。', 'roro-core' ), [ 'status' => 401 ] );
        }
        // 2. プロフィール取得
        $profile_response = wp_remote_get( 'https://api.line.me/v2/profile', [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
        ] );
        $profile_body = json_decode( wp_remote_retrieve_body( $profile_response ), true );
        if ( empty( $profile_body['userId'] ) ) {
            return new WP_Error( 'line_profile_failed', __( 'LINEユーザープロフィール取得に失敗しました。', 'roro-core' ), [ 'status' => 500 ] );
        }
        $uid   = 'line:' . $profile_body['userId'];
        $name  = $profile_body['displayName'] ?? __( 'LINEユーザー', 'roro-core' );
        $email = $uid . '@line.local';
        return $this->finalize( $uid, 'line', $email, $name );
    }

    /**
     * UIDを元にWordPressユーザーとカスタマーを取得・作成してログインする。
     *
     * provider は認証元（firebase,line,google,facebookなど）を表します。新規カスタマー作成時には
     * roro_customer.auth_provider / user_type / consent_status を設定します。既存の場合は更新しません。
     *
     * @param string $uid 外部サービスからのUID
     * @param string $provider 認証プロバイダー名
     * @param string $email メールアドレス
     * @param string $name 表示名
     * @return array
     */
    private function finalize( string $uid, string $provider, string $email, string $name ) : array {
        $p = $this->db->prefix;
        // 既にUIDが存在するか確認
        $row = $this->db->get_row( $this->db->prepare(
            "SELECT customer_id, wp_user_id FROM {$p}roro_identity WHERE uid = %s",
            $uid
        ), ARRAY_A );
        if ( ! $row ) {
            // WPユーザーを取得または新規作成
            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                $user_id = wp_create_user( $email, wp_generate_password(), $email );
                wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
            } else {
                $user_id = (int) $user->ID;
            }
            // 新規カスタマー登録。Phase 1.6 では auth_provider 等を設定
            $this->db->insert( "{$p}roro_customer", [
                'name'           => $name,
                'email'          => $email,
                'breed_id'       => 1,
                'auth_provider'  => $provider,
                'user_type'      => 'free',
                'consent_status' => 'unknown',
            ], [ '%s','%s','%d','%s','%s','%s' ] );
            $customer_id = (int) $this->db->insert_id;
            // identity テーブルに保存
            $this->db->insert( "{$p}roro_identity", [
                'uid'         => $uid,
                'customer_id' => $customer_id,
                'wp_user_id'  => $user_id,
                'provider'    => $provider,
            ], [ '%s','%d','%d','%s' ] );
        } else {
            $customer_id = (int) $row['customer_id'];
            $user_id     = (int) $row['wp_user_id'];
        }
        // WPログイン
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
        return [
            'ok'          => true,
            'customer_id' => $customer_id,
            'wp_user_id'  => $user_id,
            'provider'    => $provider,
            'idp'         => $provider, // 互換性のため旧名称も返す
        ];
    }
}
