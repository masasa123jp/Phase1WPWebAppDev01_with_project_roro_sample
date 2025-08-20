<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/api/photo_upload_endpoint.php
 *
 * 写真アップロード用エンドポイント。
 * 画像ファイルを受け取りWordPressのメディアライブラリに保存した後、roro_photo テーブルにも登録します。
 * facility_id が指定されている場合は、写真と施設を紐づけます。最大2MBのJPEG/PNG/GIF/WEBPのみ受け付けます。
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Photo_Upload_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/photo';

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
                    'facility_id' => [ 'type' => 'integer', 'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * 認可チェック：ログインユーザーのみ許可。
     *
     * 親クラスのシグネチャと一致させるため、WP_REST_Request を受け取り
     * ログイン状態を判定して返します。
     *
     * @param WP_REST_Request $request
     * @return bool True if the current user is logged in.
     */
    public static function permission_callback( WP_REST_Request $request ) {
        return is_user_logged_in();
    }

    /**
     * 写真アップロード処理。
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle( WP_REST_Request $request ) {
        if ( empty( $_FILES['file'] ) ) {
            return new WP_Error( 'no_file', __( 'アップロードされたファイルがありません。', 'roro-core' ), [ 'status' => 400 ] );
        }
        $file         = $_FILES['file'];
        $max_size     = (int) apply_filters( 'roro_photo_max_size', 2 * 1024 * 1024 );
        $allowed_mimes= [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
        if ( $file['size'] > $max_size ) {
            return new WP_Error( 'file_too_large', __( 'ファイルサイズが大きすぎます。', 'roro-core' ), [ 'status' => 400 ] );
        }
        if ( ! in_array( $file['type'], $allowed_mimes, true ) ) {
            return new WP_Error( 'invalid_type', __( 'サポートされていないファイル形式です。', 'roro-core' ), [ 'status' => 400 ] );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $uploaded = wp_handle_upload( $file, [ 'test_form' => false ] );
        if ( isset( $uploaded['error'] ) ) {
            return new WP_Error( 'upload_error', $uploaded['error'], [ 'status' => 500 ] );
        }
        // メディア登録
        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name( $uploaded['file'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $uploaded['file'] );
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
        wp_update_attachment_metadata( $attachment_id, $attach_data );
        // roro_photo に保存
        global $wpdb;
        $customer_id = null;
        $breed_id    = null;
        $user_id     = get_current_user_id();
        if ( $user_id ) {
            $identity_table = $wpdb->prefix . 'roro_identity';
            $customer_id    = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT customer_id FROM {$identity_table} WHERE wp_user_id = %d",
                $user_id
            ) );
            if ( $customer_id ) {
                $customer_table = $wpdb->prefix . 'roro_customer';
                $breed_id       = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT breed_id FROM {$customer_table} WHERE customer_id = %d",
                    $customer_id
                ) );
            }
        }
        $facility_id = $request->get_param( 'facility_id' ) ? (int) $request->get_param( 'facility_id' ) : null;
        $photo_table = $wpdb->prefix . 'roro_photo';
        $wpdb->insert( $photo_table, [
            'customer_id'   => $customer_id ?: null,
            'breed_id'      => $breed_id ?: null,
            'facility_id'   => $facility_id,
            'attachment_id' => $attachment_id,
            'zipcode'       => '',
            'lat'           => null,
            'lng'           => null,
            'created_at'    => current_time( 'mysql' ),
        ], [ '%d','%d','%d','%d','%s','%f','%f','%s' ] );
        return new WP_REST_Response( [ 'attachment_id' => (int) $attachment_id ], 201 );
    }
}
