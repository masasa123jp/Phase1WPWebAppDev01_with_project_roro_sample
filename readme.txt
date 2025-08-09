=== RoRo Core ===
Contributors: masasa123jp
Tags: pets, liff, react, gacha, gutenberg
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-enhanced pet-care toolkit: random advice, nearby facility search,
unified Firebase/LINE authentication with user management, AI advice
caching, Gutenberg blocks, and React dashboard.

== Description ==
= English =
RoRo Core turns your WordPress site into a lightweight pet-care platform.  
* Random “Gacha” advice & facility suggestions  
* Nearby facility search via GIS / Haversine  
* React + LIFF front-end (optional)  
* Gutenberg blocks: Gacha Wheel, Advice List  
* Unified Firebase/LINE login & user management  
* AI advice endpoint with caching  
* KPI dashboard widget & settings page  

= 日本語 =
RoRo Core は、WordPress サイトをペットケアプラットフォームへ拡張するプラグインです。  
* ガチャ形式の犬向けアドバイスと施設提案  
* GIS/Haversine 距離検索による近隣施設表示  
* React + LIFF のフロントエンド (任意)  
* Gutenberg ブロック（ガチャホイール・アドバイス一覧）  
* 統合された Firebase/LINE ログインとユーザー管理  
* OpenAI を使用した AI アドバイスとキャッシュ  
* KPI ダッシュボードウィジェットと設定画面  

= 中文 =
RoRo Core 让您的 WordPress 变身宠物护理平台。  
* 抽奖式建议 & 附近宠物友好设施  
* 基于 GIS/Haversine 的范围检索  
* React + LIFF 前端（可选）  
* Gutenberg 区块：抽奖按钮、建议列表  
* 统一的 Firebase/LINE 登录与用户管理  
* 基于 OpenAI 的 AI 建议缓存  
* 仪表盘 KPI 小部件与设置页  

== Installation ==
1. Upload `roro-core.zip` via **Plugins → Add New → Upload Plugin** and activate.  
2. Navigate to **Settings → RoRo Settings** and fill LIFF ID, API Token.  
3. (Recommended) Add an HTTP-Cron in your hosting panel hitting `/wp-cron.php?doing_wp_cron=1` every 10 minutes.

== Frequently Asked Questions ==
= Does the plugin work without the React front-end? =  
Yes. Gutenberg blocks and REST API work standalone.

= Which PHP / MySQL versions are required? =  
PHP 7.4+ / MySQL 5.7 or MariaDB 10.5 (XServer default).

== Screenshots ==
1. KPI dashboard widget  
2. Gacha Wheel block in front end  
3. Advice List block in editor  

== Changelog ==
= 1.0.0 =
* Unified Firebase/LINE authentication via a new `Auth_Service` class; removed the legacy auth controller.
* Consolidated custom post types into a single `Post_Types` module and removed duplicate class files.
* Added caching to the AI advice endpoint using WordPress transients with customisable TTL via filters.
* Added lowercase fallback to the PSR‑4 autoloader and removed the unused loader class.
* Cleaned up redundant modules and improved internationalisation strings.

= 0.6.0 =
* Added Facility List React route & CLI exporter  
* Introduced PHPCS, ESLint, Prettier, Vitest workflow  
* Dockerfile now includes XDebug & wp-cli  

= 0.5.0 =
* Breed-stats, Rate-Limiter, Transient cache  
* Settings page internationalised  

= 0.1.0 =
* Initial release: Gacha API, Facility search, Gutenberg blocks

== Upgrade Notice ==
= 1.0.0 =
This release removes deprecated modules and introduces unified authentication and caching. No manual migration steps are required. If you are developing locally with Docker, run `composer install` to ensure dependencies are up to date.

= 0.6.0 =
After update, run `composer install` if you use local Docker stack.

== License ==
RoRo Core is free software: you can redistribute it and/or modify it under the terms of the GPL-2.0+.
