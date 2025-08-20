# RORO Core WP

This plugin provides core functionality for the RORO application as a WordPress plugin.  It creates a suite of custom tables and settings to manage customers, pets, location favourites, authentication, recommendations, events and AI chat.  Additionally, it offers a simple Google Maps integration and exposes shortcodes for embedding maps, chat and recommendations into pages and posts.

## Features

- **Database Setup** – On activation the plugin creates all required tables by executing the SQL contained in `sql/schema.sql`.  You can customise the schema by editing this file.
- **Data Seed** – The activation routine imports data from `sql/events_table_corrected3.sql` by default.  You can also load `initial_data_with_latlng.sql` by uncommenting the call in `class-roro-activator.php`.
- **API Credential Management** – A settings page is added to the WordPress admin menu where you can configure the Google Maps API key, Dify chat URL and Google/LINE OAuth credentials.  Values defined as constants in `wp-config.php` take precedence and are shown as read‑only.
- **Google Maps Shortcode** – Use `[roro_map]` to render a simple map with a search box.  The map API key is loaded dynamically from your configuration.
- **AI Chat Shortcode** – Use `[roro_chat]` to embed an AI chat iframe pointing at the configured Dify URL.
- **Recommendation Shortcode** – Use `[roro_recommendation]` to display personalised recommendations.  The current implementation outputs a placeholder; integrate your own algorithm in `class-roro-recommender.php`.

## Installation

1. Extract the `roro-core-wp.zip` archive into your WordPress `wp-content/plugins` directory.  You should end up with a folder called `roro-core-wp` containing the plugin files.
2. Activate the **RORO Core WP** plugin from the WordPress **Plugins** screen.  Activation will create the database tables and import the initial event data.
3. Navigate to **RORO → Settings** in the admin menu.  Enter your Google Maps API key, Dify chat URL and any OAuth credentials.  Click **Save Changes**.
4. If you prefer to store keys in code rather than the database, define the following constants in your `wp-config.php` file:

   ```php
   define( 'RORO_GOOGLE_MAPS_API_KEY', 'your-maps-key' );
   define( 'RORO_DIFY_CHAT_URL', 'https://udify.app/chat/your-chat-id' );
   define( 'RORO_GOOGLE_OAUTH_ID', 'your-google-client-id' );
   define( 'RORO_GOOGLE_OAUTH_SECRET', 'your-google-client-secret' );
   define( 'RORO_LINE_OAUTH_ID', 'your-line-channel-id' );
   define( 'RORO_LINE_OAUTH_SECRET', 'your-line-channel-secret' );
   ```

5. Use the shortcodes in your posts or pages:
   - `[roro_map]` – Display a map with search box
   - `[roro_chat]` – Embed the AI chat widget
   - `[roro_recommendation]` – Show recommendations (placeholder for now)

## Uninstallation

When you delete the plugin from WordPress, `uninstall.php` will run and remove all plugin tables and settings.  If you wish to preserve data on uninstall, modify the `uninstall()` method in `includes/class-roro-activator.php` accordingly.

## Development Notes

The plugin is structured into subdirectories for clarity:

- `includes/` – PHP classes for database operations, activation/uninstallation, admin settings and recommendations.
- `public/` – JavaScript, CSS and images for front‑end functionality.
- `sql/` – SQL files used during activation to create tables and seed data.
- `languages/` – Translation template (POT) file.

Feel free to extend the provided stubs to build the full RORO experience on WordPress.