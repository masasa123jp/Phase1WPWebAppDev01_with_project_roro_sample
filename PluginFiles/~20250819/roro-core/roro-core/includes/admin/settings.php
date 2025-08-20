<?php
/**
 * Admin settings wrapper.  Delegates settings registration and rendering
 * to the General_Settings class located under the Settings namespace.
 *
 * @package RoroCore\Admin
 */

namespace RoroCore\Admin;

use RoroCore\Settings\General_Settings;

class Settings {

    public function __construct() {
        // Initialise general settings when the settings page is constructed.
        General_Settings::init();
    }

    /**
     * Render the settings page.  Called from the admin menu.
     */
    public function render_page() : void {
        General_Settings::render_page();
    }
}
