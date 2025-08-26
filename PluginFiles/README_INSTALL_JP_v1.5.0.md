# RORO プラグインインストール手順（v1.5.0）

このドキュメントは **導入～運用** の全体像に加え、**v1.5.0 の変更点サマリと開発背景** を併記します。
（詳細な変更点の網羅は *RELEASE_NOTES_v1.5.0.md* を参照）

---

## 変更点サマリ（v1.5.0）
- 新規プラグイン: **Magazine 1.0.0**, **Recommend 1.0.0** を追加。
- Core: `RORO_*` 正準テーブル化／`wp_roro_*` は互換ビュー。DB セットアップを UI から再実行可能に。
- Map: 自宅位置 GUI 保存／クラスタ／距離ソート。
- Chatbot: Dify ストリーム最適化、近傍スポットカード対応。
- Favorites: DB 永続化・多言語化。Advice: 取得失敗時のフォールバック追加。

## 開発背景（設計の狙い）
- **置くだけ運用**：`assets/sql/{schema,seed}` を所定位置に置き、管理 UI から一括実行できるように設計。
- **多言語＆疎結合**：各プラグインはショートコード／REST でゆるく結合し、翻訳を同梱。
- **セキュリティ基準**：Nonce/Capability/XSS 対策を標準化。

---

## 同梱物一覧（Zip）
|ファイル名|概要|
|---|---|
|`roro-core-wp-0.4.0.zip`|DB 作成と共通処理のコア（管理画面に「RORO 設定」）。|
|`roro-auth-1.0.0.zip`|ログイン/ユーザー登録（WP ユーザー API 利用）。|
|`roro-map-2.0.0.zip`|Google Maps 表示（ショートコード `[roro_map]`）。|
|`roro-chatbot-2.0.0.zip`|チャット UI（外部 AI 連携可）。|
|`roro-advice-2.0.0.zip`|ワンポイントアドバイス（`[roro_advice]`）。|
|`roro-favorites-1.1.0.zip`|お気に入りの登録/一覧表示。|
|`roro-magazine-1.0.0.zip`|月刊雑誌（一覧/号ビュー/記事）。|
|`roro-recommend-1.0.0.zip`|日替わりおすすめ＋アドバイス（`[roro_recommend]`）。|
|`roro-assets-sql-manager-1.3.0.zip`|SQL スクリプト実行（開発者向け）。|
|`roro_plugins_final_bundle-20250826.zip`|上記をまとめた統合バンドル。|
|`checksums.txt`|各 Zip の SHA256（整合性確認用）。|

## 前提条件
- WordPress 6.4 以上（推奨）。
- PHP 8.3.21 以上、MySQL 8.0 以上。
- サーバ権限: `CREATE VIEW` / `TRIGGER` が必要（DB 互換ビュー利用のため）。

## 事前準備
1. すべての Zip と `checksums.txt` を取得。
2. 端末で整合性を確認：  
   ```bash
   sha256sum -c checksums.txt
   ```
3. バックアップ（DB/ファイル）を取得。

## インストール手順（推奨順）
1. **Core**：`roro-core-wp-0.4.0.zip` をアップロードして有効化。  
   - 管理画面「RORO 設定」→ **Roro DB Setup** で **DDL → seed** を順に実行。
2. **Auth**：`roro-auth-1.0.0.zip` を有効化。  
3. **Map**：`roro-map-2.0.0.zip` を有効化。  
4. **Advice**：`roro-advice-2.0.0.zip` を有効化。  
5. **Favorites**：`roro-favorites-1.1.0.zip` を有効化。  
6. **Chatbot**：`roro-chatbot-2.0.0.zip` を有効化。  
7. **Magazine**：`roro-magazine-1.0.0.zip` を有効化。  
8. **Recommend**：`roro-recommend-1.0.0.zip` を有効化。  
9. **（任意）Assets SQL Manager**：`roro-assets-sql-manager-1.3.0.zip` を有効化。  

> まとめて導入する場合は `roro_plugins_final_bundle-20250826.zip` 内の各 Zip を順にアップロードしてください。

## 設定（必要に応じて）
- **Google Maps API キー**（`wp-config.php`）  
  ```php
  define('RORO_MAP_API_KEY', 'あなたの API キー');
  ```
- **チャットボット API**（`wp-config.php`）  
  ```php
  define('RORO_CHATBOT_API_URL', 'https://api.example.com/chat');
  define('RORO_CHATBOT_API_KEY', 'your-api-key');
  ```

## 動作確認（抜粋）
- 固定ページに以下のショートコードを追加して表示を確認：
  - 認証: `[roro_login]` / `[roro_signup]`
  - 地図: ``[roro_map center_lat="35.6895" center_lng="139.6917" zoom="12" markers='[{"lat":35.6895,"lng":139.6917,"title":"Tokyo"}]']``
  - チャット: `[roro_chatbot]`
  - アドバイス: `[roro_advice]`
  - お気に入り: `[roro_favorites]`
  - 雑誌: `[roro_magazine]`, `[roro_mag_issue issue="latest"]`, `[roro_mag_article id="42"]`
  - おすすめ: `[roro_recommend]`（`show_advice="0"` で非表示）

## トラブルシュート（要点）
- **DB セットアップが失敗**：DB 権限（`CREATE VIEW`/`TRIGGER`）を確認し、再実行。ログは WP デバッグまたはサーバログを参照。
- **Map が表示されない**：API キー・ドメイン制限・請求設定を確認。
- **メール検証できない**：送信元/SMTP、SPF/DKIM を確認。迷惑メールも確認。
- **翻訳が反映されない**：サイト言語とプラグインの言語ファイル配置を確認。

## アップグレードガイド（既存サイト）
1. DB/ファイルのバックアップを取得。  
2. 各 Zip を **上書きアップロード → 有効化**。  
3. Core の **DDL → seed** を再実行（必要に応じて）。  
4. API キー等の機密設定を見直し。

## 運用のヒント
- 公式更新前に **ステージング** で検証→本番適用。
- `checksums.txt` を CI に組み込み、配布物の改ざん検知を自動化。

---
*本手順書は、導入・設定・確認・運用に限定し、変更点の記載を意図的に省いています（変更点は *リリースノート v1.5.0* を参照）。*
