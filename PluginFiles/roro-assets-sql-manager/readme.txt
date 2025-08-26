=== RORO Assets SQL Manager ===
Contributors: roro-dev-team
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin combines two complementary pieces of functionality for the RORO
WordPress project:

1. **SQL Migration Manager** — Discover and run SQL or PHP migrations located
   in the plugin’s `migrations/` folder or provided via the
   `roro_sql_register_migrations` filter. Migrations can declare
   dependencies, support up/down callbacks for apply/rollback, and are
   executed inside a transaction. An administrative UI under Tools → RORO DB
   Manager allows you to apply or roll back migrations, perform dry runs to
   inspect the log and download a CSV of recent log entries. REST endpoints
   (`/wp-json/roro/v1/db/...`) provide programmatic access to list,
   apply or roll back migrations.

2. **Multilingual SQL Log Viewer** — When the SQL manager plugin is logging
   queries it exposes a log table with column headers such as “Query” and
   “Execution Time (ms)”. This plugin internationalises those headers using
   translation files located in `languages/`. English, Japanese, Korean and
   Simplified Chinese translations are included. Additional languages can be
   added by creating `.po` files in the same folder.

== Installation ==

1. Upload the `roro-assets-sql-manager` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the ‘Plugins’ menu in WordPress.
3. Place your migration files (SQL or PHP) inside the plugin’s `migrations/` directory, or register them via the `roro_sql_register_migrations` filter. You can also upload SQL/ZIP/PHAR files from the admin page to `/uploads/roro-sql/` for one‑off runs.
4. Navigate to **Tools → RORO DB Manager** to run migrations, inspect the log or download a CSV. Use the “DRY RUN” checkbox to simulate an apply or rollback without executing any SQL.
5. Use the REST API endpoints under `/wp-json/roro/v1/db/` if you need to automate migration tasks from external systems. All endpoints require administrator privileges.

== Frequently Asked Questions ==

**Where should I put my migration files?**

By default the plugin looks in its own `migrations/` directory for `.sql` and
`.php` files and orders them lexicographically by ID. You can also use the
`roro_sql_register_migrations` filter to register migrations from other
plugins or themes. Uploaded `.sql`/`.php`/`.zip` files are extracted into
`wp-content/uploads/roro-sql/` and presented alongside bundled migrations in
the admin UI.

**Does the plugin modify my database automatically on activation?**

No. When activated the plugin merely initialises its internal option
structures. Migrations are only run when you explicitly apply them from the
admin page, via the REST API or via WP‑CLI (see below).

**How do I add support for another language?**

Create a `.po` file in the `languages/` directory following the naming
convention `roro-assets-sql-manager-<locale>.po` and add translations for
`Query`, `Execution Time (ms)` and `Executed At`. Be sure to load the
translation file using `load_plugin_textdomain()` in your environment.

**Is WP‑CLI supported?**

Yes. After activating the plugin you can run the following commands:

```
wp roro-sql list
wp roro-sql apply --ids=20250824001_init_core,20250824003_seed_advice_up
wp roro-sql rollback --ids=20250824003_seed_advice_up
```

These commands mirror the functionality of the admin UI and REST API.

== Changelog ==

= 1.3.0 =
* Refactored the plugin into modular components (common utilities, migration
  engine, admin UI, REST API and SQL log translation) while preserving
  backwards compatibility.
* Added support for multilingual column headings in the SQL log viewer.

= 1.0.0 =
* Initial release combining the SQL migration manager and multilingual log
  viewer.