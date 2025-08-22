<?php
/**
 * RORO Core Plugin
 *
 * Provides an admin settings page for configuring API keys and other
 * options.  Utilises the WordPress Settings API to register and
 * persist option values.  Environment constants defined in
 * wp-config.php will take precedence and disable editing of those
 * values.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Roro_Plugin_Admin {

    /**
     * Add admin menu pages.
     */
    public static function add_admin_menu() {
        // Top-level menu
        add_menu_page(
            __( 'RORO', 'roro-plugin' ),
            __( 'RORO', 'roro-plugin' ),
            'manage_options',
            'roro',
            array( __CLASS__, 'settings_page' ),
            'dashicons-pets',
            60
        );
        // Settings submenu
        add_submenu_page(
            'roro',
            __( 'RORO Settings', 'roro-plugin' ),
            __( 'Settings', 'roro-plugin' ),
            'manage_options',
            'roro-settings',
            array( __CLASS__, 'settings_page' )
        );
    }

    /**
     * Register settings, sections, and fields.
     */
    public static function register_settings() {
        // Register a single option to store settings as an array.
        register_setting( 'roro_settings', 'roro_settings', array( __CLASS__, 'sanitize_settings' ) );

        // Add a section for API keys.
        add_settings_section(
            'roro_api_section',
            __( 'API Credentials', 'roro-plugin' ),
            function() {
                echo '<p>' . esc_html__( 'Configure external service credentials used by RORO features.', 'roro-plugin' ) . '</p>';
            },
            'roro-settings'
        );

        // Google Maps API Key
        add_settings_field(
            'google_maps_api_key',
            __( 'Google Maps API Key', 'roro-plugin' ),
            array( __CLASS__, 'render_text_field' ),
            'roro-settings',
            'roro_api_section',
            array(
                'label_for' => 'google_maps_api_key',
                'option_name' => 'roro_settings',
                'constant' => 'RORO_GOOGLE_MAPS_API_KEY'
            )
        );

        // Dify URL
        add_settings_field(
            'dify_url',
            __( 'Dify Chat URL', 'roro-plugin' ),
            array( __CLASS__, 'render_text_field' ),
            'roro-settings',
            'roro_api_section',
            array(
                'label_for' => 'dify_url',
                'option_name' => 'roro_settings',
                'constant' => 'RORO_DIFY_URL'
            )
        );

        // Google OAuth
        add_settings_field(
            'google_oauth_id',
            __( 'Google OAuth Client ID', 'roro-plugin' ),
            array( __CLASS__, 'render_text_field' ),
            'roro-settings',
            'roro_api_section',
            array(
                'label_for' => 'google_oauth_id',
                'option_name' => 'roro_settings',
                'constant' => 'RORO_GOOGLE_OAUTH_ID'
            )
        );
        add_settings_field(
            'google_oauth_secret',
            __( 'Google OAuth Client Secret', 'roro-plugin' ),
            array( __CLASS__, 'render_password_field' ),
            'roro-settings',
            'roro_api_section',
            array(
                'label_for' => 'google_oauth_secret',
                'option_name' => 'roro_settings',
                'constant' => 'RORO_GOOGLE_OAUTH_SECRET'
            )
        );

        // LINE OAuth
        add_settings_field(
            'line_oauth_id',
            __( 'LINE OAuth Channel ID', 'roro-plugin' ),
            array( __CLASS__, 'render_text_field' ),
            'roro-settings',
            'roro_api_section',
            array(
                'label_for' => 'line_oauth_id',
                'option_name' => 'roro_settings',
                'constant' => 'RORO_LINE_OAUTH_ID'
            )
        );
        add_settings_field(
            'line_oauth_secret',
            __( 'LINE OAuth Channel Secret', 'roro-plugin' ),
            array( __CLASS__, 'render_password_field' ),
            'roro-settings',
            'roro_api_section',
            array(
                'label_for' => 'line_oauth_secret',
                'option_name' => 'roro_settings',
                'constant' => 'RORO_LINE_OAUTH_SECRET'
            )
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public static function sanitize_settings( $input ) {
        $output = array();
        // Keys we expect
        $keys = array(
            'google_maps_api_key',
            'dify_url',
            'google_oauth_id',
            'google_oauth_secret',
            'line_oauth_id',
            'line_oauth_secret'
        );
        foreach ( $keys as $key ) {
            if ( isset( $input[$key] ) ) {
                $value = trim( $input[$key] );
                // Do not save if defined via constant
                $const_name = 'RORO_' . strtoupper( str_replace( 'google_', 'google_', str_replace( 'line_', 'line_', $key ) ) );
                if ( defined( $const_name ) ) {
                    continue;
                }
                $output[$key] = sanitize_text_field( $value );
            }
        }
        return $output;
    }

    /**
     * Render a text input field.
     *
     * @param array $args Field arguments.
     */
    public static function render_text_field( $args ) {
        $option_name = $args['option_name'];
        $id = $args['label_for'];
        $constant = isset( $args['constant'] ) ? $args['constant'] : '';
        $value = '';
        if ( defined( $constant ) ) {
            $value = constant( $constant );
            printf( '<input type="text" id="%s" class="regular-text" value="%s" readonly disabled />', esc_attr( $id ), esc_attr( $value ) );
            echo '<p class="description">' . sprintf( esc_html__( 'Defined in wp-config.php as %s', 'roro-plugin' ), esc_html( $constant ) ) . '</p>';
        } else {
            $options = get_option( $option_name, array() );
            $value = isset( $options[$id] ) ? $options[$id] : '';
            printf( '<input type="text" name="%s[%s]" id="%s" class="regular-text" value="%s" />', esc_attr( $option_name ), esc_attr( $id ), esc_attr( $id ), esc_attr( $value ) );
        }
    }

    /**
     * Render a password input field (masked).
     *
     * @param array $args Field arguments.
     */
    public static function render_password_field( $args ) {
        $option_name = $args['option_name'];
        $id = $args['label_for'];
        $constant = isset( $args['constant'] ) ? $args['constant'] : '';
        if ( defined( $constant ) ) {
            // Constant defined – do not allow editing
            echo '<input type="password" id="' . esc_attr( $id ) . '" class="regular-text" value="********" readonly disabled />';
            echo '<p class="description">' . sprintf( esc_html__( 'Defined in wp-config.php as %s', 'roro-plugin' ), esc_html( $constant ) ) . '</p>';
        } else {
            $options = get_option( $option_name, array() );
            $value = isset( $options[$id] ) ? $options[$id] : '';
            printf( '<input type="password" name="%s[%s]" id="%s" class="regular-text" value="%s" autocomplete="new-password" />', esc_attr( $option_name ), esc_attr( $id ), esc_attr( $id ), esc_attr( $value ) );
        }
    }

    /**
     * Render the settings page.
     */
    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'RORO Settings', 'roro-plugin' ) . '</h1>';
        echo '<form action="options.php" method="post">';
        settings_fields( 'roro_settings' );
        do_settings_sections( 'roro-settings' );
        submit_button( __( 'Save Changes', 'roro-plugin' ) );
        echo '</form>';
        echo '</div>';
    }
}