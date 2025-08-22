=== RoRo Core WP ===
Contributors: yourname
Tags: pwa, push-notifications, pet-care, api, wordpress
Requires at least: 5.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

RoRo Core WP provides the foundation for the RoRo pet‑care platform.  It
includes REST API endpoints, authentication, localisation, notifications,
custom post types, and a Progressive Web App (PWA) service worker.  This
package has been trimmed for distribution and no longer bundles heavy
development artefacts such as database design diagrams or event CSVs.  See
the project repository for full development assets if required.

== Description ==

RoRo Core WP extends WordPress with a suite of features to power the RoRo
pet‑care platform:

* **REST API** – A set of endpoints for gacha, breed lists, facility search,
  analytics and more.
* **Authentication** – Handles user login and profile management.
* **Notifications** – Registers Firebase Cloud Messaging (FCM) tokens and
  supports multiple devices per user, token removal and topic
  subscription management.
* **Progressive Web App** – Includes a Workbox‑powered service worker that
  caches plugin assets, pages and API responses using sensible strategies.
* **Shortcodes** – Provides `[roro_image]` for embedding images placed in
  `assets/images` without hard‑coding URLs.
* **Internationalisation (i18n)** – Ships with a `.pot` file and `.po`
  translations for several languages.  The text domain is `roro-core`.

== Installation ==

1. Upload the `roro-core-wp` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the *Plugins* menu in WordPress.
3. (Optional) Copy your image assets into `wp-content/plugins/roro-core-wp/assets/images`.
4. Use `[roro_image file="your-image.png"]` in posts or call
   `roro_core_image_url( 'your-image.png' )` in templates to output images.

== Changelog ==

= 1.0.0 =
* Initial public release.  Based on the RoRo Core plugin with added PWA
  capabilities, push notification management, image helpers and improved
  internationalisation.
