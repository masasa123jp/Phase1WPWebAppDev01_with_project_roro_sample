<?php
/**
 * Administrative UI for RORO Map.  This class adds a settings page where
 * site administrators can configure the Google Maps API key used by
 * the plugin.  It also integrates the settings link into the plugin
 * listing for convenience.
 */
class Roro_Map_Admin {

    /**
     * Initialise the admin hooks.  Called immediately when the file is
     * included by the main plugin.
     */
    public static function init() {
        if ( is_admin() ) {
            add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
            add_filter( 'plugin_action_links_' . plugin_basename( RORO_MAP_PATH . 'roro-map.php' ), [ __CLASS__, 'plugin_action_links' ] );
        }
    }

    /**
     * Add the RORO Map settings page under the Settings menu.  This page
     * allows administrators to enter their Google Maps API key and save
     * it using the WordPress settings API.
     */
    public static function add_settings_page() {
        add_options_page(
            __( 'RORO Map Settings', 'roro-map' ),
            __( 'RORO Map', 'roro-map' ),
            'manage_options',
            'roro-map-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Render the settings page.  Uses the standard WordPress settings
     * API to save options.  Only users with manage_options capability
     * can access this page.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Save option if the form was submitted and the nonce verifies.
        if ( isset( $_POST['roro_map_settings_nonce'] ) && wp_verify_nonce( $_POST['roro_map_settings_nonce'], 'roro_map_save_settings' ) ) {
            $key = isset( $_POST['roro_map_google_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['roro_map_google_api_key'] ) ) : '';
            update_option( 'roro_map_google_api_key', $key );
            echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'roro-map' ) . '</p></div>';
        }
        $api_key = get_option( 'roro_map_google_api_key', '' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'RORO Map Settings', 'roro-map' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'roro_map_save_settings', 'roro_map_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="roro_map_google_api_key"><?php esc_html_e( 'Google Maps API Key', 'roro-map' ); ?></label></th>
                        <td>
                            <input type="text" id="roro_map_google_api_key" name="roro_map_google_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Enter your Google Maps API key.  This key will be used to load the Google Maps JavaScript API on the front‑end.', 'roro-map' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add a settings link to the plugin entry on the plugins page.
     *
     * @param array $links Existing action links.
     * @return array Modified links with settings action appended.
     */
    public static function plugin_action_links( $links ) {
        $url = admin_url( 'options-general.php?page=roro-map-settings' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'roro-map' ) . '</a>';
        return $links;
    }
}

// Initialise the admin UI.
Roro_Map_Admin::init();