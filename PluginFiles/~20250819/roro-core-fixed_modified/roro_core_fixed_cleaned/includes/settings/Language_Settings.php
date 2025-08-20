<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/settings/language_settings.php
 *
 * デフォルト言語設定を提供するクラス。管理者は日本語・英語・中国語・韓国語から選択し、
 * 設定は roro_core_language オプションに保存されます。
 *
 * @package RoroCore\Settings
 */

namespace RoroCore\Settings;

class Language_Settings {
    public const OPTION_KEY = 'roro_core_language';

    public static function init(): void {
        add_action( 'admin_init', [ self::class, 'register_settings' ] );
        add_action( 'admin_menu', [ self::class, 'add_options_page' ] );
    }

    /**
     * 設定登録。
     */
    public static function register_settings(): void {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, [ 'sanitize_callback' => [ self::class, 'sanitize' ] ] );
        add_settings_section( 'languages', __( 'Language Settings', 'roro-core' ), '__return_false', self::OPTION_KEY );
        add_settings_field(
            'default_language',
            __( 'Default Language', 'roro-core' ),
            [ self::class, 'select_field_cb' ],
            self::OPTION_KEY,
            'languages',
            [ 'label_for' => 'default_language' ]
        );
    }

    /**
     * 入力値をサニタイズ。対応していない言語コードは日本語へフォールバック。
     *
     * @param array $input
     * @return array
     */
    public static function sanitize( array $input ) : array {
        $supported = [ 'ja', 'en_US', 'zh_CN', 'ko' ];
        $lang      = $input['default_language'] ?? 'ja';
        if ( ! in_array( $lang, $supported, true ) ) {
            $lang = 'ja';
        }
        return [ 'default_language' => sanitize_text_field( $lang ) ];
    }

    /**
     * 言語選択ドロップダウンを表示。
     *
     * @param array $args
     */
    public static function select_field_cb( array $args ) : void {
        $options = get_option( self::OPTION_KEY );
        $current = $options['default_language'] ?? 'ja';
        $langs   = [
            'ja'    => __( 'Japanese', 'roro-core' ),
            'en_US' => __( 'English',  'roro-core' ),
            'zh_CN' => __( 'Chinese',  'roro-core' ),
            'ko'    => __( 'Korean',   'roro-core' ),
        ];
        echo '<select id="default_language" name="' . esc_attr( self::OPTION_KEY ) . '[default_language]">';
        foreach ( $langs as $code => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $code ),
                selected( $current, $code, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    /**
     * 設定ページを管理画面に追加。
     */
    public static function add_options_page() : void {
        add_options_page(
            __( 'Language Settings', 'roro-core' ),
            __( 'Language',          'roro-core' ),
            'manage_options',
            self::OPTION_KEY,
            [ self::class, 'render_page' ]
        );
    }

    /**
     * 設定ページをレンダリング。
     */
    public static function render_page() : void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Language Settings', 'roro-core' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_KEY );
                do_settings_sections( self::OPTION_KEY );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
