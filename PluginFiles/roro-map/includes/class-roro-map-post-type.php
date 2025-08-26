<?php
/**
 * Class responsible for registering the custom post type used by RORO Map
 * and its associated metadata.  It also exposes helper methods for
 * enqueuing front‑end assets and rendering/editing event details in the
 * WordPress admin.  Keeping CPT logic in its own class makes the
 * responsibilities clear and allows them to be reused elsewhere if
 * necessary.
 */
class Roro_Map_Post_Type {

    /**
     * Register the 'roro_event' post type and its metadata fields.  Called on
     * the 'init' action from the main plugin file.  Metadata is exposed
     * through the REST API so that the map interface can fetch it.
     */
    public static function register() {
        // Register the custom post type for events.
        register_post_type( 'roro_event', [
            'labels' => [
                'name'               => _x( 'Events', 'post type general name', 'roro-map' ),
                'singular_name'      => _x( 'Event', 'post type singular name', 'roro-map' ),
                'menu_name'          => _x( 'Events', 'admin menu', 'roro-map' ),
                'name_admin_bar'     => _x( 'Event', 'add new on admin bar', 'roro-map' ),
                'add_new'            => _x( 'Add New', 'event', 'roro-map' ),
                'add_new_item'       => __( 'Add New Event', 'roro-map' ),
                'new_item'           => __( 'New Event', 'roro-map' ),
                'edit_item'          => __( 'Edit Event', 'roro-map' ),
                'view_item'          => __( 'View Event', 'roro-map' ),
                'all_items'          => __( 'All Events', 'roro-map' ),
                'search_items'       => __( 'Search Events', 'roro-map' ),
                'parent_item_colon'  => __( 'Parent Events:', 'roro-map' ),
                'not_found'          => __( 'No events found.', 'roro-map' ),
                'not_found_in_trash' => __( 'No events found in Trash.', 'roro-map' ),
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => [ 'slug' => 'events' ],
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
        ] );

        // Register metadata for start/end times, coordinates, category and address.
        register_post_meta( 'roro_event', 'roro_start', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( 'roro_event', 'roro_end', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( 'roro_event', 'roro_lat', [
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'floatval',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( 'roro_event', 'roro_lng', [
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'floatval',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( 'roro_event', 'roro_cat', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( 'roro_event', 'roro_address', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => function() { return current_user_can( 'edit_posts' ); },
        ] );

        // Hook meta box registration and saving.
        add_action( 'add_meta_boxes_roro_event', [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post_roro_event',       [ __CLASS__, 'save_meta_box' ] );
    }

    /**
     * Register our stylesheet and script.  They are not enqueued here but
     * registered so that the main plugin file can enqueue them when
     * appropriate.  By registering handles we avoid duplicate definitions.
     */
    public static function enqueue_assets() {
        // Register style.
        wp_register_style( 'roro-map', RORO_MAP_URL . 'assets/css/roro-map.css', [], RORO_MAP_VERSION );
        // Register script; depends on the Google Maps API and WordPress' built in i18n utilities.
        wp_register_script( 'roro-map', RORO_MAP_URL . 'assets/js/roro-map.js', [ 'google-maps' ], RORO_MAP_VERSION, true );
    }

    /**
     * Add a meta box to the event editing screen.  This UI allows
     * administrators to set the event date/time, location and category.
     *
     * @param WP_Post $post The current post object.
     */
    public static function add_meta_box( $post ) {
        add_meta_box(
            'roro_event_details',
            __( 'Event Details', 'roro-map' ),
            [ __CLASS__, 'render_meta_box' ],
            'roro_event',
            'normal',
            'default'
        );
    }

    /**
     * Render the fields for our meta box.  Nonces ensure that only
     * authorised users can save the data.  Inputs are named according to
     * their meta keys to simplify saving.
     *
     * @param WP_Post $post The post currently being edited.
     */
    public static function render_meta_box( $post ) {
        // Add a nonce for security and authentication.
        wp_nonce_field( 'roro_event_details', 'roro_event_details_nonce' );
        // Retrieve existing values.
        $start   = get_post_meta( $post->ID, 'roro_start',   true );
        $end     = get_post_meta( $post->ID, 'roro_end',     true );
        $lat     = get_post_meta( $post->ID, 'roro_lat',     true );
        $lng     = get_post_meta( $post->ID, 'roro_lng',     true );
        $cat     = get_post_meta( $post->ID, 'roro_cat',     true );
        $address = get_post_meta( $post->ID, 'roro_address', true );
        ?>
        <p>
            <label for="roro_start"><strong><?php esc_html_e( 'Start date/time', 'roro-map' ); ?></strong></label><br/>
            <input type="datetime-local" id="roro_start" name="roro_start" value="<?php echo esc_attr( $start ); ?>" class="widefat" />
        </p>
        <p>
            <label for="roro_end"><strong><?php esc_html_e( 'End date/time', 'roro-map' ); ?></strong></label><br/>
            <input type="datetime-local" id="roro_end" name="roro_end" value="<?php echo esc_attr( $end ); ?>" class="widefat" />
        </p>
        <p>
            <label for="roro_lat"><strong><?php esc_html_e( 'Latitude', 'roro-map' ); ?></strong></label><br/>
            <input type="number" id="roro_lat" name="roro_lat" step="0.000001" value="<?php echo esc_attr( $lat ); ?>" class="widefat" />
        </p>
        <p>
            <label for="roro_lng"><strong><?php esc_html_e( 'Longitude', 'roro-map' ); ?></strong></label><br/>
            <input type="number" id="roro_lng" name="roro_lng" step="0.000001" value="<?php echo esc_attr( $lng ); ?>" class="widefat" />
        </p>
        <p>
            <label for="roro_cat"><strong><?php esc_html_e( 'Category', 'roro-map' ); ?></strong></label><br/>
            <input type="text" id="roro_cat" name="roro_cat" value="<?php echo esc_attr( $cat ); ?>" class="widefat" />
        </p>
        <p>
            <label for="roro_address"><strong><?php esc_html_e( 'Address', 'roro-map' ); ?></strong></label><br/>
            <input type="text" id="roro_address" name="roro_address" value="<?php echo esc_attr( $address ); ?>" class="widefat" />
        </p>
        <?php
    }

    /**
     * Save the metadata entered into our meta box.  Runs on the
     * 'save_post_roro_event' hook and verifies the nonce, autosave and
     * permissions before updating the metadata.  Sanitisation is handled
     * by the register_post_meta callbacks.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public static function save_meta_box( $post_id ) {
        // Verify nonce.
        if ( ! isset( $_POST['roro_event_details_nonce'] ) || ! wp_verify_nonce( $_POST['roro_event_details_nonce'], 'roro_event_details' ) ) {
            return;
        }
        // Skip autosaves.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check user permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Save each meta field.  Data is sanitised by register_post_meta.
        $fields = [ 'roro_start', 'roro_end', 'roro_lat', 'roro_lng', 'roro_cat', 'roro_address' ];
        foreach ( $fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, wp_unslash( $_POST[ $key ] ) );
            }
        }
    }
}