RoRo Core WP Plugin - Full Asset Package
=======================================

This package contains the RoRo Core WordPress plugin along with full database
and design assets used by the RoRo Core platform.

Included:
- The original plugin files from `roro-core` (refactored) with the main file
  renamed to `roro-core-wp.php` and the text domain updated.
- Database SQL dumps with fixed latitude/longitude and event data under
  `db/sql` and `db/event` (including subdirectories such as csv, mock).
- CSV event datasets and python scripts used for event enrichment.
- Database design diagrams (SVG, MD, Excel) under `db/design` including
  historic and current design documents.

To install:
1. Unzip this package into your WordPress `wp-content/plugins` directory.
2. Activate the "RoRo Core WP (Full Asset)" plugin through the WordPress
   Admin Plugins screen.
3. Import the SQL files into your MySQL database as required using phpMyAdmin
   or command-line tools.

This plugin serves as the core functionality for the RoRo pet-care platform
and provides REST API endpoints, authentication, localization, notifications,
custom post types, and more.
