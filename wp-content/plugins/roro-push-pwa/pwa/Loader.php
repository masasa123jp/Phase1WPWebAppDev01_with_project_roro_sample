<?php
/**
 * PWA loader.  Handles registration of service worker and manifest for
 * the RoRo platform.  Serves the service worker script at
 * `/roro-sw.js`.  Also enqueues a small client script that registers
 * the service worker on the front end.  In production you should
 * integrate Workbox or Vite’s PWA plugin to generate the service
 * worker automatically during your build process.
 *
 * @package RoroPushPwa\Pwa
 */

namespace RoroPushPwa\Pwa;

class Loader {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'init', [ $this, 'serve_service_worker' ] );
    }

    /**
     * Enqueue the service worker registration script on the front end.
     */
    public function enqueue_scripts() : void {
        // Register a simple inline script to register the service worker.
        $script = "if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/roro-sw.js').catch(function(e){console.error('SW registration failed', e);}); }";
        wp_add_inline_script( 'wp-foot', $script );
    }

    /**
     * Serve the service worker script when requested.  If the current
     * request is for `roro-sw.js` we send the appropriate headers and
     * output a minimal service worker.  WordPress should not try to
     * handle the request further.
     */
    public function serve_service_worker() : void {
        if ( isset( $_SERVER['REQUEST_URI'] ) && preg_match( '#/roro-sw\\.js$#', $_SERVER['REQUEST_URI'] ) ) {
            header( 'Content-Type: application/javascript; charset=utf-8' );
            // Very minimal service worker for offline caching.  You can
            // replace this with Workbox or a more robust implementation.
            echo "self.addEventListener('install', function(event) { self.skipWaiting(); });\n";
            echo "self.addEventListener('activate', function(event) { event.waitUntil(self.clients.claim()); });\n";
            echo "self.addEventListener('fetch', function(event) { event.respondWith(fetch(event.request)); });\n";
            exit;
        }
    }
}
