# RORO Plugins v1.6.0 – Release Notes
Date: 2025-08-22

本リリースは **Phase 3 リリース候補（P3 RC）** を含み、**DB スキーマの決定版（DDL_20250822.sql）**、配布 Zip、署名一覧（`checksums.txt`）を同梱します。

---

## パッケージ一覧

- `roro-core-wp-1.6.0.zip`
- `roro-auth-1.6.0.zip`
- `roro-map-1.6.0.zip`
- `roro-chatbot-1.6.0-rc3.zip`
- `roro_plugins_final_bundle-20250822.zip`（auth/map/chatbot の統合バンドル）
- `checksums.txt`（上記 Zip の SHA256）

---

## 主要変更点

### roro-core-wp 1.6.0
- **DDL_20250822.sql（決定版）** を同梱（`assets/sql/schema/`）  
- 正準テーブルを **`RORO_*`** に統一、**`wp_roro_*` は互換ビュー**化（重複テーブルを生成しない）  
- 管理画面「**Roro DB Setup**」から **DDL→seed** の再実行が可能  
- 文字コードを **utf8mb4 / utf8mb4_unicode_520_ci** に統一

### roro-auth 1.6.0
- **ソーシャル連携の解除 UI** を強化（Google/LINE/Apple 等）  
- **メール検証必須化**（未検証ユーザーの機能制限）  
- **通知テンプレート最適化**（件名/本文の多言語化フック）  
- ペット情報 CRUD／プロフィール画像アップロードの安定化

### roro-map 1.6.0
- **自宅位置の GUI 保存**（既存 REST 保存に加え）  
- **マーカー・クラスタリング**／**距離ソート**  
- お気に入り（`roro-favorites`）との連携性向上

### roro-chatbot 1.6.0-rc3
- **Dify ストリーミング描画**を最適化（フロント分割描画）  
- **近傍スポットカードの JSON スキーマ**に準拠し、**roro-map** 連携で回答内にカードを埋め込み  
- 自前モード／Dify 切替、UI のチップ／カード CSS/JS を強化

---

## 互換性と移行

- 既存サイトに **`wp_roro_*` 実体テーブル**がある場合、DDL適用前に **`RORO_*` へ移行**してください。  
- DDL 適用後は `wp_roro_*` は **ビュー** となり、旧参照は互換吸収されます。  
- MySQL は 8.0+ 推奨。`CREATE VIEW` / `TRIGGER` 権限が必要。

---

## インストール順序

1. `roro-core-wp-1.6.0.zip` → 有効化 → 管理画面「Roro DB Setup」で **DDL→seed** 実行  
2. `roro-auth-1.6.0.zip` → 有効化  
3. `roro-map-1.6.0.zip` → 有効化  
4. `roro-chatbot-1.6.0-rc3.zip` → 有効化  
5. 必要に応じて統合バンドル `roro_plugins_final_bundle-20250822.zip` も利用可（auth/map/chatbot 同梱）

---

## 署名（checksums.txt）

- `checksums.txt` に同梱 Zip の **SHA256** を記載しています。  
- 生成例:
  ```bash
  sha256sum roro-core-wp-1.6.0.zip             roro-auth-1.6.0.zip             roro-map-1.6.0.zip             roro-chatbot-1.6.0-rc3.zip             roro_plugins_final_bundle-20250822.zip > checksums.txt
  ```

---

## 既知の課題（抜粋）

- 一部の seed はレコード増量時に投入時間が長くなる場合があります（段階投入を推奨）。  
- Dify 接続は API キー／エンドポイントの設定が必須（環境変数または WP 管理画面で設定）。

---

## 変更履歴（サマリ）

- Core: スキーマ整理／互換ビュー化／管理画面再実行  
- Auth: 解除 UI・メール検証・通知テンプレ最適化  
- Map: 自宅位置 GUI・クラスタ・距離ソート  
- Chatbot: ストリーミング最適化・近傍カード・UI 強化
