<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/review_endpoint.php
 *
 * 施設レビュー投稿用エンドポイント。
 * 施設ID、評価（1〜5）、コメントを受け取り、roro_facility_review にレコードを挿入します。
 * 評価は1〜5の範囲で検証し、WordPressユーザーIDから customer_id を取得して保存します。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Review_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/reviews';

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
                'permission_callback' => [ self::class, 'permission_callback' ],
                'args'                => [
                    'facility_id' => [ 'type' => 'integer', 'required' => true ],
                    'rating'      => [ 'type' => 'number',  'required' => true ],
                    'comment'     => [ 'type' => 'string',  'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * 認可チェック：ログインユーザーのみ許可。
     *
     * 親クラスのメソッドと同じシグネチャに揃えます。WP_REST_Request を受け取り
     * ログインユーザーであるかどうかを返します。
     *
     * @param WP_REST_Request $request
     * @return bool True if the user is logged in.
     */
    public static function permission_callback( \WP_REST_Request $request ) {
    return parent::permission_callback( $request );
}


    /**
     * レビュー投稿処理。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) {
        global $wpdb;
        $facility_id = (int) $request->get_param( 'facility_id' );
        $rating      = (float) $request->get_param( 'rating' );
        $comment     = $request->get_param( 'comment' );
        if ( $rating < 1 || $rating > 5 ) {
            return new WP_Error( 'invalid_rating', __( '評価は1〜5の範囲で入力してください。', 'roro-core' ), [ 'status' => 400 ] );
        }
        // WPユーザーID → customer_id を取得
        $wp_user_id     = get_current_user_id();
        $identity_table = $wpdb->prefix . 'roro_identity';
        $customer_id    = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT customer_id FROM {$identity_table} WHERE wp_user_id = %d",
            $wp_user_id
        ) );
        if ( ! $customer_id ) {
            return new WP_Error( 'no_customer', __( 'ユーザーに対応する顧客情報が見つかりません。', 'roro-core' ), [ 'status' => 400 ] );
        }
        // 保存
        $review_table = $wpdb->prefix . 'roro_facility_review';
        $wpdb->insert( $review_table, [
            'facility_id' => $facility_id,
            'customer_id' => $customer_id,
            'rating'      => $rating,
            'comment'     => ( $comment !== null ) ? wp_kses_post( $comment ) : '',
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d', '%d', '%f', '%s', '%s' ] );
        return rest_ensure_response( [ 'id' => (int) $wpdb->insert_id ] );
    }
}
