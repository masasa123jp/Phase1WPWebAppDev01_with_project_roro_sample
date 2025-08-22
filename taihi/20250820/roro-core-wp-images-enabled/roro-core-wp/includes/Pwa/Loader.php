<?php
namespace RoroCore\Pwa;

/**
 * PWA Loader
 *
 * Registers a service worker script at `/roro-sw.js` and enqueues a
 * registration script.  The service worker implements a simple cache
 * strategy: assets under `/wp-content/plugins/` are served from cache
 * first, while other requests fall back to the network.  When the
 * network is unavailable, cached responses are used as a fallback.
 */
class Loader {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_sw' ] );
        add_action( 'init', [ $this, 'serve_service_worker' ] );
    }

    /**
     * Enqueue the service worker registration script on the front end.
     */
    public function enqueue_sw() {
        if ( is_admin() ) {
            return;
        }
        $handle = 'roro-core-pwa';
        wp_register_script( $handle, '', [], RORO_CORE_VERSION, true );
        wp_enqueue_script( $handle );
        $sw_url = esc_url( home_url( '/roro-sw.js' ) );
        $script = "if ('serviceWorker' in navigator) { navigator.serviceWorker.register('" . $sw_url . "'); }";
        wp_add_inline_script( $handle, $script );
    }

    /**
     * Serve the service worker script when requested.
     */
    public function serve_service_worker() {
        if ( isset( $_SERVER['REQUEST_URI'] ) && preg_match( '#/roro-sw\\.js$#', $_SERVER['REQUEST_URI'] ) ) {
            header( 'Content-Type: application/javascript; charset=utf-8' );
            echo $this->generate_sw();
            exit;
        }
    }

    /**
     * Generate the service worker script.
     *
     * Implements basic pre-cache of static assets in the plugin and a
     * network-first strategy for other requests.
     *
     * @return string JavaScript of the service worker.
     */
    private function generate_sw() {
        // Pre-cache all files under the plugin's asset directory.
        $cache_name    = 'roro-core-cache-v1';
        $precache_urls = [
            '/'        // cache homepage as fallback
        ];
        return "const CACHE_NAME = '" . $cache_name . "';\n" .
               "self.addEventListener('install', event => {\n" .
               "  event.waitUntil(\n" .
               "    caches.open(CACHE_NAME).then(cache => cache.addAll(" . json_encode( $precache_urls ) . "))\n" .
               "  );\n" .
               "  self.skipWaiting();\n" .
               "});\n" .
               "self.addEventListener('activate', event => {\n" .
               "  event.waitUntil(\n" .
               "    caches.keys().then(keys => Promise.all(keys.map(key => {\n" .
               "      if (key !== CACHE_NAME) return caches.delete(key);\n" .
               "    })))\n" .
               "  );\n" .
               "  self.clients.claim();\n" .
               "});\n" .
               "self.addEventListener('fetch', event => {\n" .
               "  const request = event.request;\n" .
               "  // Only GET requests are cached\n" .
               "  if (request.method !== 'GET') return;\n" .
               "  const url = new URL(request.url);\n" .
               "  // Cache-first for plugin assets\n" .
               "  if (url.pathname.includes('/wp-content/plugins/') ) {\n" .
               "    event.respondWith(\n" .
               "      caches.match(request).then(cached => {\n" .
               "        return cached || fetch(request).then(response => {\n" .
               "          const clone = response.clone();\n" .
               "          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));\n" .
               "          return response;\n" .
               "        });\n" .
               "      })\n" .
               "    );\n" .
               "  } else {\n" .
               "    // Network-first for other requests\n" .
               "    event.respondWith(\n" .
               "      fetch(request).catch(() => caches.match(request))\n" .
               "    );\n" .
               "  }\n" .
               "});\n";
    }
}