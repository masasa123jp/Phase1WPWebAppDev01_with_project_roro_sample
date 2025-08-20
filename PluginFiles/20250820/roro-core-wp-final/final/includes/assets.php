<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/assets.php
 *
 * WordPress ブロックエディタおよびフロントエンドで使用するスクリプト／スタイルを
 * 登録・読み込むユーティリティクラス。主な責務は以下のとおり。
 *
 * 1. ブロックエディタ用の React/JSX バンドル（例: ガチャホイール編集ブロック）の登録
 * 2. フロントエンド用の React バンドル読み込みと REST データ (nonce 等) の受け渡し
 * 3. wp_set_script_translations() を介した JS 翻訳ファイルの読込
 * 4. Chart.js CDN・Canvas アニメーションライブラリなど汎用スクリプトの登録
 *
 * ※ フォント／スタイルを追加したい場合は enqueue_style() を同様の要領で行う。
 *
 * @package RoroCore
 */

declare( strict_types = 1 );

namespace RoroCore;

class Assets {
    /**
     * 初期化。HOOK に処理を登録する。
     *
     * @return void
     */
    public static function init(): void {
        // ブロックエディタ向け
        add_action( 'enqueue_block_editor_assets', [ self::class, 'editor' ] );
        // フロントエンド向け
        add_action( 'wp_enqueue_scripts', [ self::class, 'frontend' ] );
    }

    /**
     * ブロックエディタ用スクリプト登録。
     *
     * Editor バンドルには Block API v2 を想定しており、WordPress Core が提供する
     * wp-i18n（翻訳関数）、wp-element（React）、wp-blocks などに依存する。
     * ビルド時に externals 設定で除外しておくと最適。
     *
     * @return void
     */
    public static function editor(): void {
        $base = plugins_url( '../blocks/', __FILE__ );

        // 例: ガチャホイール編集ブロック
        wp_enqueue_script(
            'roro-gacha-wheel-editor',
            $base . 'gacha-wheel/index.js',
            [ 'wp-blocks', 'wp-i18n', 'wp-element' ],
            '1.1.0',
            false // エディタでは footer=false
        );

        // JS 用翻訳ファイルの読込
        wp_set_script_translations( 'roro-gacha-wheel-editor', 'roro-core' );
    }

    /**
     * フロントエンド用スクリプト登録。
     *
     * REST API のエンドポイントと nonce を wp_localize_script() で JS 側に渡す。
     * また Chart.js（CDN）を読み込み、グラフ表示ブロックがロードされても
     * 404 にならないようにする。既に同ハンドルが登録されている場合は上書きしない。
     *
     * @return void
     */
    public static function frontend(): void {
        $base = plugins_url( '../blocks/', __FILE__ );

        // ガチャホイール（フロント側）
        wp_enqueue_script(
            'roro-gacha-wheel',
            $base . 'gacha-wheel/frontend.js',
            [],
            '1.1.0',
            true // フッターで読み込む
        );

        // REST 情報を JS へ渡す
        wp_localize_script( 'roro-gacha-wheel', 'wpRoro', [
            'rest_url' => esc_url_raw( rest_url( 'roro/v1/' ) ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
        ] );

        // JS 用翻訳ファイル
        wp_set_script_translations( 'roro-gacha-wheel', 'roro-core' );

        /**
         * Chart.js (UMD) - CDN から読み込み
         * すでに他プラグインで登録済みの場合は二重登録を避ける。
         */
        if ( ! wp_script_is( 'chart-js', 'registered' ) ) {
            wp_register_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.3.2/dist/chart.umd.min.js',
                [],
                '4.3.2',
                true
            );
        }
        wp_enqueue_script( 'chart-js' );

        /**
         * Canvas アニメーション (例: pixi.js) が必要であればここで登録。
         * 今回は未使用のためコメントアウト例のみ示す。
         *
         * // if ( ! wp_script_is( 'pixi', 'registered' ) ) {
         * //     wp_register_script(
         * //         'pixi',
         * //         'https://cdn.jsdelivr.net/npm/pixi.js@8.0.0/dist/browser/pixi.min.js',
         * //         [],
         * //         '8.0.0',
         * //         true
         * //     );
         * // }
         * // wp_enqueue_script( 'pixi' );
         */
    }
}

// グローバルで初期化
Assets::init();
