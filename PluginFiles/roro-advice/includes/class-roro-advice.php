<?php

/**
 * One‑point advice functionality for the Roro project.
 *
 * This class registers a shortcode that outputs a random piece of advice. If
 * you have a custom table defined to store advice, you can modify
 * get_random_advice() to retrieve from the database instead of the hardcoded
 * defaults.
 *
 * @since 1.0.0
 */
class Roro_Advice_Plugin {
    /**
     * Service instance used to retrieve advice.
     *
     * @var RORO_Advice_Service
     */
    protected $service;

    public function __construct() {
        // Lazily instantiate the service when the plugin is constructed.
        if ( class_exists( 'RORO_Advice_Service' ) ) {
            $this->service = new RORO_Advice_Service();
        }
    }

    /**
     * Register the shortcode.
     */
    public function run() {
        add_shortcode( 'roro_advice', array( $this, 'render_advice' ) );
    }

    /**
     * Fetch a random advice string.
     *
     * This implementation delegates to the shared RORO_Advice_Service. If the
     * service is unavailable for any reason it falls back to a small set of
     * hard‑coded English messages to preserve backward compatibility.
     *
     * @return string
     */
    protected function get_random_advice() {
        if ( $this->service instanceof RORO_Advice_Service ) {
            $locale = substr( get_locale(), 0, 2 );
            return $this->service->get_random_advice( 'general', $locale );
        }
        // Fallback: use simple English messages if service is not available
        $default = array(
            __( 'Remember to spend quality time with your pet every day.', 'roro-advice' ),
            __( 'Regular exercise keeps your pet healthy and happy.', 'roro-advice' ),
            __( 'Provide fresh water at all times.', 'roro-advice' ),
            __( 'Don’t forget annual health checkups.', 'roro-advice' ),
        );
        return $default[ array_rand( $default ) ];
    }

    /**
     * Render the advice string.
     *
     * @return string
     */
    public function render_advice() {
        $advice = $this->get_random_advice();
        return '<p class="roro-advice">' . esc_html( $advice ) . '</p>';
    }
}