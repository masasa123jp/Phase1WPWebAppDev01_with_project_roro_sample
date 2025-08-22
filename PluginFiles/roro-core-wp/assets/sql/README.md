# assets/sql – DDL / seed 運用ガイド

本ディレクトリは、**roro-core-wp** が実行する **DB スキーマ**および**初期データ（seed）** の保管場所です。  
WordPress の管理画面「**Roro DB Setup**」または有効化フックから、**`schema/` → `seed/` の順**で適用されます。

---

## 構成

```
assets/sql/
├─ schema/
│  └─ DDL_20250822.sql     # 決定版DDL（CREATE DATABASEなし、utf8mb4）
├─ seed/
│  ├─ initial_data_with_latlng_fixed_BASIC.sql
│  ├─ initial_data_with_latlng_fixed_EVENT_MASTER.sql
│  ├─ initial_data_with_latlng_fixed_GMAP.sql
│  ├─ initial_data_with_latlng_fixed_OPAM.sql
│  └─ initial_data_with_latlng_fixed_TSM.sql
└─ README.md               # 本ファイル
```

---

## 実行順序と冪等性

1. **schema/**  
   - `DDL_20250822.sql` を**最初に**実行します。  
   - 方針:  
     - 正準テーブルは **`RORO_*`** のみ生成  
     - 旧互換の **`wp_roro_*`** は **ビュー**で提供  
     - `CREATE TABLE IF NOT EXISTS` により**再実行安全**  
     - 文字コードは **utf8mb4 / utf8mb4_unicode_520_ci**

2. **seed/**  
   - スキーマ適用後、**seed を順次実行**します。  
   - 可能な限り `INSERT IGNORE` または `ON DUPLICATE KEY UPDATE` を用いて**冪等**にしています。

> **CREATE DATABASE** は含めません（WordPress 既存 DB に対して実行）。

---

## 命名規約

- スキーマファイル：`DDL_YYYYMMDD.sql`（最新のみを運用）  
- 初期データ：`initial_data_*.sql`（テーブル別／ソース別）  
- 互換ビュー名：`wp_roro_*`（アプリ側の旧参照を吸収）

---

## 追加・拡張の手順

1. 新しいテーブル/列を追加する場合  
   - `schema/DDL_YYYYMMDD.sql` を更新（**IF NOT EXISTS** / **ADD COLUMN IF NOT EXISTS** に準拠）  
   - 既存列のリネームは**ビューの互換性**を確認の上で実施

2. seed を増量する場合  
   - `seed/` にファイルを追加  
   - 既存データと衝突しないよう、`INSERT IGNORE` など冪等化

3. 実行方法  
   - 管理画面: **Roro DB Setup**（`includes/admin-page.php`）  
   - プラグイン有効化時: `register_activation_hook` → `includes/schema.php` 経由で自動実行（再実行可）

---

## 既存→新スキーマ移行の注意

- 旧実体テーブル `wp_roro_*` にデータがある場合、**DDL実行前**に `RORO_*` へ移行してください。  
- DDL 実行後は `wp_roro_*` は **ビュー** となります（**重複テーブルは作成しません**）。

---

## トラブルシュート

- **権限エラー**: DB ユーザーに `CREATE VIEW` / `TRIGGER` 権限が必要  
- **文字化け**: 接続の `SET NAMES utf8mb4` を確認  
- **重複**: 既存の `wp_roro_*` **テーブル**（ビューではなく実体）が残っている場合は事前に退避・削除
