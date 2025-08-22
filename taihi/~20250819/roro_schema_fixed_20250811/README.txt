RoRo Schema (Fixed) - 2025-08-11

Files
-----
- schema_20250811.php : Single-file installer with guards for dbDelta/WP_CLI.
- roro_schema_20250811.sql : Standalone SQL (for phpMyAdmin/MySQL client).

How to use
----------
[Plugin activation]
  require_once __DIR__ . '/schema_20250811.php';
  register_activation_hook(__FILE__, ['Roro_Schema_20250811', 'install']);

[WP-CLI]
  wp roro-schema install
  wp roro-schema install --mode=raw

Notes
-----
- If your WordPress table prefix is NOT 'wp_', edit roro_schema_20250811.sql and replace 'wp_users' with '<prefix>users' before running it directly.
- The PHP installer uses $wpdb->prefix so it works with any prefix.
- The script avoids "unknown function dbDelta" editor warnings by:
   * requiring upgrade.php when needed,
   * calling dbDelta via call_user_func only after function_exists('dbDelta').
- WP_CLI calls are only registered when class_exists('WP_CLI') is true.
