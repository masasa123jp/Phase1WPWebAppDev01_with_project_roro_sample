# RoRo Core WP

This plugin provides core functionality for the RoRo project, including:

- **Progressive Web App (PWA)** integration with a service worker that caches
  plugin assets and falls back to the network when necessary.
- **Push Notifications** via Firebase Cloud Messaging (FCM). A REST endpoint
  (`/wp-json/roro/v1/fcm-token`) allows authenticated users to register their
  FCM tokens. The tokens are stored in user meta for later use.
- **Image Helper** functions and a `[roro_image]` shortcode to embed images
  stored in the plugin’s `assets/images` directory.

## Installation

1. Upload the plugin directory to the `/wp-content/plugins/` directory or
   install the provided zip file via the WordPress admin panel.
2. Activate the plugin through the ‘Plugins’ menu in WordPress.
3. (Optional) Copy your image assets into `wp-content/plugins/roro-core-wp/assets/images/`.
4. Use the `[roro_image]` shortcode in posts or pages, for example:
   ```
   [roro_image file="logo.png" alt="サイトのロゴ"]
   ```

## Service Worker

The service worker registered by this plugin caches requests to files under
`/wp-content/plugins/` using a cache-first strategy. Other requests fall back
to the network with a simple network-first strategy. When offline, cached
responses are served. The service worker currently pre-caches only the site
home page but can be extended to include more assets.

## Push Token Endpoint

Authenticated users can register their FCM token by sending a `POST` request to
`/wp-json/roro/v1/fcm-token` with a JSON body containing a `token` property.
The endpoint validates the token and stores it in the user’s meta data.

## Future Improvements

- Implement additional caching strategies using tools like Workbox to improve
  offline behaviour and precache dynamic assets.
- Provide admin settings to manage PWA and push notification options.
- Expand the shortcode library or add Gutenberg blocks for easier content
  insertion.
- Add localisation files (`.pot`) and translation support.

## Development Notes

This plugin follows WordPress coding standards. New modules should be placed
under the `includes/` directory and namespaced under `RoroCore`. Assets such as
images should live under `assets/images` and can be referenced via the
`roro_core_image_url()` helper function.