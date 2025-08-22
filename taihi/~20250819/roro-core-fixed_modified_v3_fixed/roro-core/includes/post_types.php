<?php

/**
 * モジュールパス: wp-content/plugins/roro-core/includes/post_types.php
 *
 * RoRoプラットフォームで使用するカスタム投稿タイプを登録します。
 * このモジュールは、以前分割されていた roro_photo と roro_advice の
 * 定義を 1 つのクラスに統合したものです。両投稿タイプの登録を一箇所にまとめることで、
 * メンテナンスが容易になり、適切なタイミングで WordPress にフックされます。
 * 将来カスタム投稿タイプを追加する場合は、register() 内に登録メソッドを追加してください。
 *
 * @package RoroCore
 */

namespace RoroCore;

defined( 'ABSPATH' ) || exit;

/**
 * RoRo用のカスタム投稿タイプを登録するクラス。
 * 両方の登録コールバックは WordPress の `init` アクションにフックされ、
 * リライトルールなどのインフラが利用可能になります。
 */
class Post_Types {

    /**
     * カスタム投稿タイプの登録を WordPress にフックします。
     * プラグイン初期化ルーチン（例: roro_core_init()）内から呼び出し、
     * 投稿タイプを利用可能にします。
     *
     * @return void
     */
    public static function register() : void {
        add_action( 'init', [ self::class, 'register_photo_cpt' ] );
        add_action( 'init', [ self::class, 'register_advice_cpt' ] );
    }

    /**
     * roro_photo カスタム投稿タイプを登録します。
     * この投稿タイプは Photo Upload エンドポイント経由でユーザーが
     * アップロードしたペット写真を保存します。
     * デフォルトでは非公開（公開クエリ不可）ですが、
     * 管理画面 UI と REST API には表示され、
     * 管理者がアップロードを管理できます。
     *
     * @return void
     */
    public static function register_photo_cpt() : void {
        register_post_type( 'roro_photo', [
            'labels'       => [
                'name'          => __( 'Pet Photos', 'roro-core' ),
                'singular_name' => __( 'Pet Photo', 'roro-core' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-camera',
            'supports'     => [ 'title', 'thumbnail', 'custom-fields' ],
        ] );
    }

    /**
     * roro_advice カスタム投稿タイプを登録します。
     * この投稿タイプは犬のケアに関するキュレーションされたアドバイス記事を表します。
     * 公開設定で REST API にも公開され、フロントエンドアプリで表示可能です。
     * 抜粋やサムネイルなどのサポートでリッチな表示が可能になります。
     *
     * @return void
     */
    public static function register_advice_cpt() : void {
        register_post_type( 'roro_advice', [
            'labels'       => [
                'name'          => __( 'Dog Advice', 'roro-core' ),
                'singular_name' => __( 'Advice', 'roro-core' ),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'menu_icon'    => 'dashicons-carrot',
        ] );
    }
}
