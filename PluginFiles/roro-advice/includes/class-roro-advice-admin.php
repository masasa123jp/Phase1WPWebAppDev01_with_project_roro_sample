<?php
/**
 * アドバイスコンテンツのタグ／カテゴリ管理およびシードデータ投入支援。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roro_Advice_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_post_roro_advice_seed', array( __CLASS__, 'handle_seed_submit' ) );
    }

    public static function add_menu() {
        add_menu_page(
            __( 'Advice 管理', 'roro-advice' ),
            __( 'Advice 管理', 'roro-advice' ),
            'manage_options',
            'roro-advice',
            array( __CLASS__, 'render_admin_page' ),
            'dashicons-lightbulb'
        );
    }

    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Advice 管理', 'roro-advice' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'roro-advice-seed' ); ?>
                <input type="hidden" name="action" value="roro_advice_seed">
                <p>
                    <?php esc_html_e( 'サンプルのタグ／カテゴリデータを投入します。', 'roro-advice' ); ?>
                </p>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'シード投入', 'roro-advice' ); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * シードデータ投入処理。
     */
    public static function handle_seed_submit() {
        check_admin_referer( 'roro-advice-seed' );
        // サンプルカテゴリーとタグを登録する例
        $cats = array( '健康', 'しつけ', '食事' );
        foreach ( $cats as $cat ) {
            if ( ! term_exists( $cat, 'roro_advice_category' ) ) {
                wp_insert_term( $cat, 'roro_advice_category' );
            }
        }
        $tags = array( '初めての飼育', '高齢犬', '雨の日' );
        foreach ( $tags as $tag ) {
            if ( ! term_exists( $tag, 'roro_advice_tag' ) ) {
                wp_insert_term( $tag, 'roro_advice_tag' );
            }
        }

        wp_redirect( add_query_arg( 'message', 'seed_done', admin_url( 'admin.php?page=roro-advice' ) ) );
        exit;
    }
}

// Note: the admin init hook is invoked from the main plugin file when
// running in the admin dashboard.  Do not call init here to avoid
// registering duplicate hooks.
