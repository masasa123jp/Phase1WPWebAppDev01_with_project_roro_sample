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


・使い方（概要）

適用/ロールバック（管理画面）
WordPress管理画面 → ツール > RORO DB Manager

一覧でチェック → 「選択を適用」または「選択をロールバック」

DRY RUN チェックで実行前にログ確認（SQLは実行しない）

アップロード で .sql / .php / .zip を投入すると /uploads/roro-sql/ へ展開され、一覧に出ます

REST（管理者限定 / manage_options）

GET /wp-json/roro/v1/db/migrations

POST /wp-json/roro/v1/db/apply {"ids":["20250824001_init_core"],"dry_run":false}

POST /wp-json/roro/v1/db/rollback {"ids":["20250824003_seed_advice_up"],"dry_run":false}

WP-CLI

wp roro-sql list

wp roro-sql apply --ids=20250824001_init_core,20250824003_seed_advice_up

wp roro-sql rollback --ids=20250824003_seed_advice_up

・運用・安全上の注意（実装反映済み）

本番適用前に必ずバックアップを取得 してください（DBスナップショット等）。

DELIMITER は簡易対応 です。ストアド/イベント等の高度な構文は 事前検証 を推奨します。

ロールバック（down）はマイグレーション定義側の責務 です。downが無いIDは スキップ されます。

REST操作は manage_options 権限のみ許可（管理者限定）にしています。

管理画面に DRY RUN を用意。実行前にログで内容を確認できます。

アップロード（.zip/.sql/.php） に対応：/uploads/roro-sql/ に展開 → 一覧から適用可能。

適用時のメタ（時刻・実行者・ハッシュ）を保存し、追跡性を向上させています。

・既存SQL（添付）を運用するには

管理画面 → ツール > RORO DB Manager

「SQL/マイグレーションのアップロード」で sql.zip もしくは *.sql を選択 → アップロード

一覧に出てきたIDにチェック → DRY RUN でログ確認 → 問題なければ 選択を適用

もし添付SQL中に DELIMITER を使ったプロシージャ等が含まれている場合でも、上記の分割器で実行可能です。複雑なケースはステージングでリハーサルしてから本番へ適用してください。