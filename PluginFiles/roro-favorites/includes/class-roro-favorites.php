<?php

/**
 * Favourites functionality for Roro.
 *
 * Registers AJAX handlers for adding and removing favourites and a shortcode
 * to display the current user’s favourites. This implementation stores
 * favourites in user meta as a proof of concept. You may want to migrate
 * the data to a custom table for better performance and flexibility.
 *
 * @since 1.0.0
 */
class Roro_Favorites_Plugin {
    /**
     * Kick things off by registering hooks and AJAX actions.
     */
    public function run() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_roro_fav_toggle', array( $this, 'toggle_favorite' ) );
        add_action( 'wp_ajax_nopriv_roro_fav_toggle', array( $this, 'auth_required' ) );
        add_shortcode( 'roro_favorites', array( $this, 'render_favorites' ) );
    }

    /**
     * Enqueue JavaScript for handling favourite toggles.
     */
    public function enqueue_scripts() {
        wp_enqueue_script( 'roro-favorites-js', RORO_FAVORITES_URL . 'assets/js/favorites.js', array( 'jquery' ), '1.0.0', true );
        wp_localize_script( 'roro-favorites-js', 'roroFavorites', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'roro_fav_toggle' ),
        ) );
    }

    /**
     * AJAX callback to add or remove a favourite.
     */
    public function toggle_favorite() {
        check_ajax_referer( 'roro_fav_toggle', 'nonce' );
        // Only allow logged‑in users to save favourites.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'roro-favorites' ) ), 401 );
        }
        $user_id   = get_current_user_id();
        $item_id   = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : '';
        // Validate inputs
        if ( ! $item_id || ! in_array( $item_type, array( 'event', 'spot' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid item.', 'roro-favorites' ) ), 400 );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'roro_favorites';
        // Check if favourite exists
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d AND item_id = %d AND item_type = %s", $user_id, $item_id, $item_type ) );
        if ( $exists ) {
            // Remove favourite
            $wpdb->delete( $table, array( 'id' => $exists ), array( '%d' ) );
            $action = 'removed';
        } else {
            // Add favourite
            $wpdb->insert( $table, array(
                'user_id'   => $user_id,
                'item_id'   => $item_id,
                'item_type' => $item_type,
            ), array( '%d', '%d', '%s' ) );
            $action = 'added';
        }
        // Build updated list of favourites for the user
        $fav_rows = $wpdb->get_results( $wpdb->prepare( "SELECT item_id, item_type FROM $table WHERE user_id = %d", $user_id ), ARRAY_A );
        wp_send_json_success( array( 'action' => $action, 'favorites' => $fav_rows ) );
    }

    /**
     * Respond to unauthenticated AJAX requests.
     */
    public function auth_required() {
        wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'roro-favorites' ) ), 401 );
    }

    /**
     * Render the user’s favourites as a list.
     *
     * @return string HTML output.
     */
    public function render_favorites() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to view your favourites.', 'roro-favorites' ) . '</p>';
        }
        $user_id = get_current_user_id();
        global $wpdb;
        $table_fav  = $wpdb->prefix . 'roro_favorites';
        $table_ev   = $wpdb->prefix . 'roro_events';
        $table_sp   = $wpdb->prefix . 'roro_spots';
        // Join favourites with events and spots to get names
        $query = "SELECT f.item_id, f.item_type, e.title AS event_title, s.name AS spot_name\n"
               . "FROM $table_fav f\n"
               . "LEFT JOIN $table_ev e ON (f.item_type = 'event' AND e.id = f.item_id)\n"
               . "LEFT JOIN $table_sp s ON (f.item_type = 'spot'  AND s.id = f.item_id)\n"
               . "WHERE f.user_id = %d";
        $results = $wpdb->get_results( $wpdb->prepare( $query, $user_id ), ARRAY_A );
        if ( empty( $results ) ) {
            return '<p>' . esc_html__( 'You have no favourites.', 'roro-favorites' ) . '</p>';
        }
        $output = '<ul class="roro-favorites-list">';
        foreach ( $results as $row ) {
            $name = ( 'event' === $row['item_type'] ) ? $row['event_title'] : $row['spot_name'];
            if ( ! $name ) {
                $name = '#' . $row['item_id'];
            }
            $output .= '<li>' . esc_html( $name ) . '</li>';
        }
        $output .= '</ul>';
        return $output;
    }
}