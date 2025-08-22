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
     * Register the shortcode.
     */
    public function run() {
        add_shortcode( 'roro_advice', array( $this, 'render_advice' ) );
    }

    /**
     * Fetch a random advice string.
     *
     * @return string
     */
    protected function get_random_advice() {
        // Attempt to fetch a random advice row from our database.  If no custom advice
        // exists, fall back to the hard-coded defaults.
        // If the core DB class is available, attempt to fetch advice from the database.
        if ( class_exists( 'Roro_Core_Wp_DB' ) ) {
            $language = apply_filters( 'roro_advice_language', get_locale() );
            $row      = Roro_Core_Wp_DB::get_random_advice( $language );
            if ( $row && ! empty( $row['advice_text'] ) ) {
                return $row['advice_text'];
            }
        }
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