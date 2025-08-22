<?php
/**
 * Module path: wp-content/plugins/roro-core/includes/settings/general_settings.php
 *
 * RoRo Core の一般設定クラス。Google Maps、OpenAI、FCM、LINE LIFF のAPIキーを保存し、
 * サニタイズしてwp_optionsに格納します。
 *
 * @package RoroCore\Settings
 */

namespace RoroCore\Settings;

class General_Settings {
    public const OPTION_KEY = 'roro_core_options';

    public static function init() : void {
        add_action( 'admin_init', [ self::class, 'register_settings' ] );
        add_action( 'admin_menu', [ self::class, 'add_options_page' ] );
    }

    /**
     * 設定フィールド登録。
     */
    public static function register_settings() : void {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, [ 'sanitize_callback' => [ self::class, 'sanitize' ] ] );
        add_settings_section( 'api_keys', __( 'API Keys', 'roro-core' ), '__return_false', self::OPTION_KEY );
        add_settings_field( 'gmaps_key',  __( 'Google Maps JS API Key', 'roro-core' ), [ self::class, 'text_field_cb' ], self::OPTION_KEY, 'api_keys', [ 'label_for' => 'gmaps_key' ] );
        add_settings_field( 'openai_key', __( 'OpenAI API Key',          'roro-core' ), [ self::class, 'text_field_cb' ], self::OPTION_KEY, 'api_keys', [ 'label_for' => 'openai_key' ] );
        add_settings_field( 'fcm_key',    __( 'FCM Server Key',          'roro-core' ), [ self::class, 'text_field_cb' ], self::OPTION_KEY, 'api_keys', [ 'label_for' => 'fcm_key' ] );
        add_settings_field( 'liff_id',    __( 'LINE LIFF ID',            'roro-core' ), [ self::class, 'text_field_cb' ], self::OPTION_KEY, 'api_keys', [ 'label_for' => 'liff_id' ] );
    }

    /**
     * サニタイズ処理。空欄は空文字列として保存。
     *
     * @param array $input
     * @return array
     */
    public static function sanitize( array $input ) : array {
        return [
            'gmaps_key'  => sanitize_text_field( $input['gmaps_key']  ?? '' ),
            'openai_key' => sanitize_text_field( $input['openai_key'] ?? '' ),
            'fcm_key'    => sanitize_text_field( $input['fcm_key']    ?? '' ),
            'liff_id'    => sanitize_text_field( $input['liff_id']    ?? '' ),
        ];
    }

    /**
     * テキスト入力用コールバック。
     *
     * @param array $args
     */
    public static function text_field_cb( array $args ) : void {
        $options = get_option( self::OPTION_KEY );
        $key     = $args['label_for'];
        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" />',
            esc_attr( $key ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $options[ $key ] ?? '' )
        );
    }

    /**
     * 設定画面を「設定」メニューに追加。
     */
    public static function add_options_page() : void {
        add_options_page(
            __( 'RoRo Core Settings', 'roro-core' ),
            __( 'RoRo Core',          'roro-core' ),
            'manage_options',
            self::OPTION_KEY,
            [ self::class, 'render_page' ]
        );
    }

    /**
     * 設定画面を表示。
     */
    public static function render_page() : void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'RoRo Core Settings', 'roro-core' ); ?></h1>
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
