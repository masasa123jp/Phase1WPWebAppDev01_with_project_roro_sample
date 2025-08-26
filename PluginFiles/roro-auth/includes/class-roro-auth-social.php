<?php
/**
 * Stub implementation for social account management.
 *
 * In this MECE plugin we include a stub for future social account
 * management (link/unlink accounts).  It registers a shortcode
 * `[roro_social_links]` which displays a placeholder message for
 * logged in users.  A full implementation can replace this class
 * without affecting other parts of the plugin.
 */
class Roro_Auth_Social {
    /**
     * Register the shortcode on plugin load.  Called automatically.
     */
    public static function init(): void {
        add_shortcode('roro_social_links', [__CLASS__, 'render_stub']);
    }

    /**
     * Shortcode callback which returns a placeholder message.
     *
     * @return string
     */
    public static function render_stub(): string {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html(Roro_Auth_I18n::t('signin_required')) . '</p>';
        }
        return '<p>' . esc_html(Roro_Auth_I18n::t('social_not_implemented')) . '</p>';
    }
}

// Initialise immediately to register the shortcode.
Roro_Auth_Social::init();