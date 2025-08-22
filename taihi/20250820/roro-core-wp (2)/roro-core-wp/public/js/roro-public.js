/*
 * RORO Core WP public script.
 *
 * This file contains general JavaScript used on the front end of the site for
 * features that are not specific to Google Maps.  At present it logs a
 * message to the console indicating that the plugin’s public script has
 * loaded.  Additional client‑side functionality (e.g. AJAX handlers for
 * recommendations or chat interactions) can be added here.
 */
( function () {
    document.addEventListener( 'DOMContentLoaded', function () {
        if ( window.console && console.log ) {
            console.log( 'RORO Core WP public script loaded' );
        }
    } );
} )();