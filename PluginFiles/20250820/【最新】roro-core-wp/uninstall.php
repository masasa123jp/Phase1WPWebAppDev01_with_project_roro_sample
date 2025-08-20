<?php
/**
 * Uninstall handler for the RORO Core WP plugin.
 *
 * When the plugin is removed from WordPress, this file is executed to
 * perform clean‑up.  It invokes the uninstall routine defined in
 * Roro_Core_Wp_Activator to drop database tables and remove options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Include the activator class if it hasn’t been loaded.
if ( ! class_exists( 'Roro_Core_Wp_Activator' ) ) {
    require_once dirname( __FILE__ ) . '/includes/class-roro-activator.php';
}

// Perform uninstall cleanup.
Roro_Core_Wp_Activator::uninstall();