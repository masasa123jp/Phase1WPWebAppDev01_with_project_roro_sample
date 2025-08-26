<?php
/**
 * お気に入り機能の一覧 UI とアクセシビリティ調整。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roro_Favorites_UI {

    public static function init() {
        add_shortcode( 'roro_favorites_list', array( __CLASS__, 'render_favorites_list' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function enqueue_assets() {
        wp_enqueue_script(
            'roro-favorites',
            plugins_url( '../assets/js/favorites.js', __FILE__ ),
            array( 'jquery' ),
            '1.0.0',
            true
        );
        wp_localize_script(
            'roro-favorites',
            'roroFavoritesL10n',
            array(
                'sortLabel'       => __( '並び替え', 'roro-favorites' ),
                'filterLabel'     => __( '絞り込み', 'roro-favorites' ),
                'noFavorites'     => __( 'お気に入りは登録されていません。', 'roro-favorites' ),
                'toastRemoved'    => __( 'お気に入りから削除しました。', 'roro-favorites' ),
            )
        );
    }

    /**
     * お気に入り一覧のショートコード出力。
     *
     * @return string
     */
    public static function render_favorites_list() {
        if ( ! is_user_logged_in() ) {
            return esc_html__( 'お気に入り機能を利用するにはログインが必要です。', 'roro-favorites' );
        }
        ob_start();
        ?>
        <div id="roro-favorites-list" role="region" aria-live="polite" tabindex="-1">
            <div class="favorites-controls">
                <label>
                    <?php esc_html_e( '並び替え', 'roro-favorites' ); ?>
                    <select id="favorites-sort">
                        <option value="date"><?php esc_html_e( '登録順', 'roro-favorites' ); ?></option>
                        <option value="name"><?php esc_html_e( '名前順', 'roro-favorites' ); ?></option>
                    </select>
                </label>
                <label>
                    <?php esc_html_e( '絞り込み', 'roro-favorites' ); ?>
                    <input type="text" id="favorites-filter" placeholder="<?php esc_attr_e( 'キーワード', 'roro-favorites' ); ?>">
                </label>
            </div>
            <ul class="favorites-list">
                <!-- JS で項目を動的に表示 -->
            </ul>
            <div class="pagination" aria-label="<?php esc_attr_e( 'ページネーション', 'roro-favorites' ); ?>">
                <!-- 任意: ページネーションをここに実装 -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

Roro_Favorites_UI::init();
