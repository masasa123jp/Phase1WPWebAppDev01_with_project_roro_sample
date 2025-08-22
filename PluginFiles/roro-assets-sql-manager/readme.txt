=== RoRo Assets and SQL Manager ===
Contributors: roro-dev-team
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin provides two key features for the RoRo WordPress project:

1. **Image Shortcode** — Easily embed images stored in the theme’s `project‑roro/assets/images` directory using a shortcode. Example: `[roro_image file="chiamame_character.png" alt="Chiamame" class="img-fluid" width="300"]`.

2. **SQL Import Tools** — Run SQL scripts located in a top‑level `DB_sql` directory (e.g. `/var/www/html/DB_sql`) to create or update database tables. An admin page is added under Tools → RoRo SQL Import for on‑demand execution.

== Installation ==

1. Upload the `roro-assets-sql-manager` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Place your image files inside your theme’s `project-roro/assets/images` folder.
4. Place your `.sql` files into a `DB_sql` directory located at the WordPress root (a sibling of `wp-content`).
5. Navigate to **Tools → RoRo SQL Import** to run your SQL scripts when needed.

== Frequently Asked Questions ==

**Where should I put my images?**

All images should reside in your active theme’s `project-roro/assets/images` directory. The plugin will build the correct URL based on your current theme. If that folder doesn’t exist, the plugin will fall back to your theme’s root `assets/images` directory.

**Where should I put my SQL files?**

Create a folder named `DB_sql` at the root of your WordPress installation (i.e. next to `wp-content`). Any `.sql` files in this directory will be executed when you run the import from the admin page or by calling the `roro_assets_sql_manager_import_sql_files()` function.

**Does the plugin automatically run SQL on activation?**

No. The plugin only checks that the `DB_sql` directory exists when activated. To execute the SQL files, either visit Tools → RoRo SQL Import and click **Run Import**, or call the function `roro_assets_sql_manager_import_sql_files()` in your code.

== Changelog ==

v1.0.0 — Initial release. Provides image shortcode and SQL import functionality.