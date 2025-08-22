<?php
/**
 * Admin interface for the RORO Core WP plugin.
 *
 * This class registers a settings page under a custom top‑level menu.  It
 * leverages the WordPress Settings API to store configuration values for
 * external services such as Google Maps, Dify chat, Google OAuth and LINE OAuth.
 * Constants defined in wp-config.php take precedence over stored options and
 * render the associated fields read‑only.
 *
 * @package RORO_Core_WP
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roro_Core_Wp_Admin {
    /**
     * Add the plugin’s menu and submenu to the WordPress admin.
     */
    public static function add_admin_menu() {
        add_menu_page(
            __( 'RORO', 'roro-core-wp' ),
            __( 'RORO', 'roro-core-wp' ),
            'manage_options',
            'roro-core-wp',
            array( __CLASS__, 'settings_page' ),
            'dashicons-pets',
            60
        );
        add_submenu_page(
            'roro-core-wp',
            __( 'RORO Settings', 'roro-core-wp' ),
            __( 'Settings', 'roro-core-wp' ),
            'manage_options',
            'roro-core-wp-settings',
            array( __CLASS__, 'settings_page' )
        );
    }

    /**
     * Register plugin settings, sections and fields.
     */
    public static function register_settings() {
        register_setting( 'roro_core_wp_settings_group', 'roro_core_wp_settings', array( __CLASS__, 'sanitize_settings' ) );

        add_settings_section(
            'roro_core_wp_api_section',
            __( 'API Credentials', 'roro-core-wp' ),
            function () {
                echo '<p>' . esc_html__( 'Configure external service credentials used by RORO features.', 'roro-core-wp' ) . '</p>';
            },
            'roro-core-wp-settings'
        );

        // Define field descriptors: id => [label, constant, type]
        $fields = array(
            'google_maps_api_key'      => array( __( 'Google Maps API Key', 'roro-core-wp' ), 'RORO_GOOGLE_MAPS_API_KEY', 'text' ),
            'dify_chat_url'           => array( __( 'Dify Chat URL', 'roro-core-wp' ), 'RORO_DIFY_CHAT_URL', 'text' ),
            'google_oauth_client_id'   => array( __( 'Google OAuth Client ID', 'roro-core-wp' ), 'RORO_GOOGLE_OAUTH_ID', 'text' ),
            'google_oauth_client_secret' => array( __( 'Google OAuth Client Secret', 'roro-core-wp' ), 'RORO_GOOGLE_OAUTH_SECRET', 'password' ),
            'line_oauth_channel_id'    => array( __( 'LINE OAuth Channel ID', 'roro-core-wp' ), 'RORO_LINE_OAUTH_ID', 'text' ),
            'line_oauth_channel_secret' => array( __( 'LINE OAuth Channel Secret', 'roro-core-wp' ), 'RORO_LINE_OAUTH_SECRET', 'password' ),
        );

        foreach ( $fields as $id => $def ) {
            add_settings_field(
                $id,
                $def[0],
                array( __CLASS__, $def[2] === 'password' ? 'render_password_field' : 'render_text_field' ),
                'roro-core-wp-settings',
                'roro_core_wp_api_section',
                array(
                    'label_for'   => $id,
                    'option_name' => 'roro_core_wp_settings',
                    'constant'    => $def[1],
                )
            );
        }
    }

    /**
     * Sanitize and save settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized values.
     */
    public static function sanitize_settings( $input ) {
        $output = array();
        $valid_keys = array(
            'google_maps_api_key',
            'dify_chat_url',
            'google_oauth_client_id',
            'google_oauth_client_secret',
            'line_oauth_channel_id',
            'line_oauth_channel_secret',
        );
        foreach ( $valid_keys as $key ) {
            if ( isset( $input[ $key ] ) ) {
                $value = trim( $input[ $key ] );
                // Determine constant name from key.  E.g. google_maps_api_key → RORO_GOOGLE_MAPS_API_KEY
                $const = 'RORO_' . strtoupper( str_replace( array( 'google_', 'line_' ), array( 'GOOGLE_', 'LINE_' ), $key ) );
                // If the constant is defined, do not persist the value.
                if ( defined( $const ) ) {
                    continue;
                }
                $output[ $key ] = sanitize_text_field( $value );
            }
        }
        return $output;
    }

    /**
     * Render a text input.
     *
     * @param array $args Field arguments.
     */
    public static function render_text_field( $args ) {
        $option_name = $args['option_name'];
        $id          = $args['label_for'];
        $constant    = isset( $args['constant'] ) ? $args['constant'] : '';
        if ( defined( $constant ) ) {
            $value = constant( $constant );
            printf( '<input type="text" id="%s" class="regular-text" value="%s" readonly disabled />', esc_attr( $id ), esc_attr( $value ) );
            echo '<p class="description">' . sprintf( esc_html__( 'Defined in wp-config.php as %s', 'roro-core-wp' ), esc_html( $constant ) ) . '</p>';
        } else {
            $options = get_option( $option_name, array() );
            $value   = isset( $options[ $id ] ) ? $options[ $id ] : '';
            printf( '<input type="text" name="%s[%s]" id="%s" class="regular-text" value="%s" />', esc_attr( $option_name ), esc_attr( $id ), esc_attr( $id ), esc_attr( $value ) );
        }
    }

    /**
     * Render a masked password input.
     *
     * @param array $args Field arguments.
     */
    public static function render_password_field( $args ) {
        $option_name = $args['option_name'];
        $id          = $args['label_for'];
        $constant    = isset( $args['constant'] ) ? $args['constant'] : '';
        if ( defined( $constant ) ) {
            echo '<input type="password" id="' . esc_attr( $id ) . '" class="regular-text" value="********" readonly disabled />';
            echo '<p class="description">' . sprintf( esc_html__( 'Defined in wp-config.php as %s', 'roro-core-wp' ), esc_html( $constant ) ) . '</p>';
        } else {
            $options = get_option( $option_name, array() );
            $value   = isset( $options[ $id ] ) ? $options[ $id ] : '';
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
        echo '<h1>' . esc_html__( 'RORO Settings', 'roro-core-wp' ) . '</h1>';
        echo '<form action="options.php" method="post">';
        settings_fields( 'roro_core_wp_settings_group' );
        do_settings_sections( 'roro-core-wp-settings' );
        submit_button( __( 'Save Changes', 'roro-core-wp' ) );
        echo '</form>';
        echo '</div>';
    }
}
