# RoRo Core — Phase 1.6 Pet Platform
> **EN:** AI‑driven WordPress plugin & React front‑end for pet‑care.  
> **JA:** ペットケア向け AI 活用 WordPress プラグイン + React フロントエンド。  
> **ZH‑CN:** 面向宠物护理的 AI 驱动 WordPress 插件与 React 前端。  
> **KO:** 반려동물 케어를 위한 AI 기반 WordPress 플러그인과 React 프런트엔드.
---

## 1. プロジェクト概要 / Overview

- **目的**: ペットケア領域の地図・イベント・AIアドバイス・認証を WordPress 上で統合提供  
- **コア**: `roro-core-wp`（DB/seed/共通アセット・管理UI・互換ビュー）  
- **機能プラグイン**: 認証(`roro-auth`)、地図/スポット(`roro-map`)、チャットボット(`roro-chatbot`)、お気に入り(`roro-favorites`)、アドバイス(`roro-advice`)

### 多言語サマリ（EN/JA/ZH‑CN/KO 抜粋）
AI‑driven WordPress plugin & React front‑end for pet‑care.  
ペットケア向け AI 活用 WordPress プラグイン + React フロントエンド。  
面向宠物护理的 AI 驱动 WordPress 插件与 React 前端。  
반려동물 케어를 위한 AI 기반 WordPress 플러그인과 React 프런트엔드.

---

## 2. ディレクトリ構成（抜粋）

```
Phase1WPWebAppDev01_with_project_roro_sample/
├─ PluginFiles/
│  ├─ roro-core-wp/
│  │  ├─ roro-core-wp.php
│  │  ├─ includes/
│  │  │  ├─ schema.php
│  │  │  └─ admin-page.php
│  │  └─ assets/
│  │     ├─ sql/
│  │     │  ├─ schema/
│  │     │  │  └─ DDL_20250822.sql
│  │     │  ├─ seed/
│  │     │  │  ├─ initial_data_with_latlng_fixed_BASIC.sql
│  │     │  │  ├─ initial_data_with_latlng_fixed_EVENT_MASTER.sql
│  │     │  │  ├─ initial_data_with_latlng_fixed_GMAP.sql
│  │     │  │  ├─ initial_data_with_latlng_fixed_OPAM.sql
│  │     │  │  └─ initial_data_with_latlng_fixed_TSM.sql
│  │     │  └─ README.md
│  │     └─ images/
│  ├─ roro-auth/
│  ├─ roro-map/
│  ├─ roro-chatbot/
│  ├─ roro-favorites/
│  └─ roro-advice/
│
├─ dist/
│  ├─ 2025-08-22/
│  │  ├─ roro-core-wp-1.6.0.zip
│  │  ├─ roro-auth-1.6.0.zip
│  │  ├─ roro-map-1.6.0.zip
│  │  ├─ roro-chatbot-1.6.0-rc3.zip
│  │  ├─ roro_plugins_final_bundle-20250822.zip
│  │  ├─ checksums.txt
│  │  └─ RELEASE_NOTES_v1.6.0.md
│  └─ LATEST -> 2025-08-22/
```
（`.gitattributes` / `.gitignore` は適宜）

---

## 3. サポート環境
- WordPress 6.x
- PHP 8.1+
- MySQL 8.0+（utf8mb4 / utf8mb4_unicode_520_ci）
- Apache/Nginx（Rewrite 有効）

---

## 4. 導入順序（推奨）

1. **roro-core-wp** をプラグインとして配置→有効化  
   - 管理画面「Roro DB Setup」（`includes/admin-page.php`）から **DDL_20250822.sql** と **seed** を適用
2. **roro-auth** を配置・有効化（WPユーザー連携／ソーシャル解除 UI／メール検証）
3. **roro-map** を配置・有効化（自宅位置 GUI 保存／クラスタリング／距離ソート）
4. **roro-chatbot** を配置・有効化（Dify/ローカル切替、ストリーミング描画、近傍スポットカード）
5. 必要に応じて **roro-favorites** / **roro-advice** を配置・有効化

> 既存サイトへ適用する場合は実行前に DB バックアップを取得してください。

---

## 5. クイックスタート（ローカル / 本番）

### Local (Docker)
```bash
git clone https://github.com/masasa123jp/Phase1WPWebAppDev01
cd Phase1WPWebAppDev01/docker
docker compose up -d
```

### Production (XServer)
1. `wp-content/plugins/roro-core/` を **roro-core.zip** に圧縮  
2. **プラグイン → 新規追加 → プラグインのアップロード** でアップロード  
3. 10 分間隔で `https://<domain>/wp-cron.php?doing_wp_cron=1` を HTTP‑Cron に登録

---

## 6. 開発フロー

| ステップ | コマンド | 概要 |
|---|---|---|
| 依存導入 | `composer install && npm ci` | 依存関係をインストール |
| Lint | `make lint` | コード品質チェック（PHP/JS） |
| 単体試験 | `make test` | PHPUnit / Vitest |
| E2E | `make e2e` | Playwright など |
| 翻訳 | `bash scripts/make-pot.sh` | POT 生成 |

---

## 7. DB・命名ポリシー（重複テーブル対策）

- 正準テーブルは **`RORO_*`** のみ。  
- 旧コード互換の **`wp_roro_*`** は **ビュー**として提供（**実体テーブルは作成しない**）。  
- `DDL_20250822.sql` は `CREATE DATABASE` を含まず、**`CREATE TABLE IF NOT EXISTS`** 等で**再実行安全**。

---

## 8. 機能モジュール（ハイライト）

- **Gacha API**: ランダムにアドバイス/施設を提案  
- **Facility Search**: GIS/Haversine による距離検索  
- **Admin KPI**: ダッシュボードウィジェット  
- **Blocks**: ガチャホイール／アドバイス一覧  
- **React SPA**: LIFF 認証・施設/詳細ページ  
- **Auth & Caching**: Firebase/LINE ログイン統合と AI キャッシュ

---

## 9. 配布物（dist）と署名

- `/dist/<日付>/` 配下に Zip と `checksums.txt` を保全。  
- 生成例:
```bash
cd dist/2025-08-22
sha256sum *.zip > checksums.txt
```
- リリースノート: `/dist/2025-08-22/RELEASE_NOTES_v1.6.0.md`

---
> 既存サイトへ適用する場合は実行前に DB バックアップを取得してください。
## 🌟 Features / 機能 / 功能 / 기능

| Module               | English                                                  | 日本語                                                         | 中文                                                       | 한국어                                                    |
|----------------------|----------------------------------------------------------|----------------------------------------------------------------|------------------------------------------------------------|-----------------------------------------------------------|
| **Gacha API**        | Random advice & facility suggestions                     | ランダムにアドバイス/施設を提案                                | 随机抽取建议和设施                                         | 랜덤 조언·시설 추천                                       |
| **Facility Search**  | Radius search via GIS/Haversine                          | GIS/Haversine による距離検索                                   | GIS/Haversine 范围检索                                      | GIS/Haversine 반경 검색                                    |
| **Admin KPI**        | Dashboard widget                                         | ダッシュボード KPI                                           | 仪表盘 KPI                                                 | 대시보드 KPI                                              |
| **Blocks**           | Gacha Wheel / Advice List                                | ガチャホイール / アドバイス一覧                               | 抽奖按钮 / 建议列表                                        | 가차 휠 / 조언 리스트                                      |
| **React SPA**        | LIFF auth, facility/advice pages                         | LIFF 認証と施設/詳細ページ                                    | LIFF 认证及页面                                           | LIFF 인증 및 페이지                                       |
| **Auth & Caching**   | Unified Firebase/LINE login & AI advice caching          | 統合された Firebase/LINE ログインと AI アドバイスのキャッシュ   | 统一 Firebase/LINE 登录与 AI 建议缓存                      | 통합된 Firebase/LINE 인증 및 AI 조언 캐시                   |

---

## 🚀 Quick Start / クイックスタート / 快速开始 / 빠른 시작

### Local (Docker)
**EN:**  
```bash
git clone https://github.com/masasa123jp/Phase1WPWebAppDev01
cd Phase1WPWebAppDev01/docker
docker compose up -d
```
**JA:**  
上記コマンドを順に実行してローカル開発環境を起動します。  
**ZH‑CN:**  
按以上顺序执行命令，在本地启动开发环境。  
**KO:**  
위 명령어를 순서대로 실행하여 로컬 개발 환경을 시작합니다.  

### Production (XServer)
**EN:**  
1. Zip `wp-content/plugins/roro-core/` as **roro-core.zip**  
2. Upload via **Plugins → Add New → Upload Plugin**  
3. Add HTTP-Cron: `https://<domain>/wp-cron.php?doing_wp_cron=1` every 10 min  
**JA:**  
1. `wp-content/plugins/roro-core/` を **roro-core.zip** に圧縮  
2. **プラグイン → 新規追加 → プラグインのアップロード** でアップロード  
3. 10 分間隔で `https://<domain>/wp-cron.php?doing_wp_cron=1` を HTTP‑Cron に登録  
**ZH‑CN:**  
1. 将 `wp-content/plugins/roro-core/` 压缩为 **roro-core.zip**  
2. 在后台 **插件 → 安装插件 → 上传插件** 上传  
3. 每 10 分钟调用 `https://<domain>/wp-cron.php?doing_wp_cron=1` 作为 HTTP‑Cron  
**KO:**  
1. `wp-content/plugins/roro-core/` 를 **roro-core.zip** 로 압축  
2. **플러그인 → 새로 추가 → 플러그인 업로드** 에서 업로드  
3. 10분 간격으로 `https://<domain>/wp-cron.php?doing_wp_cron=1` 를 HTTP‑크론으로 등록  

---

## 🛠 Development Workflow / 開発フロー / 开发流程 / 개발 흐름

| Step / ステップ / 步骤 / 단계 | Command | 説明 (JA) | 说明 (ZH‑CN) | 설명 (KO) |
|------------------------------|---------|-----------|--------------|-----------|
| Install deps                | `composer install && npm ci` | 依存関係をインストール | 安装依赖 | 의존성 설치 |
| Lint PHP/JS                 | `make lint`                  | コード品質チェック | 代码质量检查 | 코드 품질 검사 |
| Unit tests                  | `make test`                 | 単体テスト実行 | 单元测试 | 단위 테스트 |
| E2E tests                   | `make e2e`                  | E2E テスト実行 | 端到端测试 | E2E 테스트 |
| Make POT                    | `bash scripts/make-pot.sh`   | 翻訳テンプレート生成 | 生成翻译模板 | 번역 템플릿 생성 |

---

## 🗂 Structure / 構成 / 结构 / 구조
**EN/JA/ZH‑CN/KO:** 各ディレクトリの役割は以下の通りです。  
```text
plugins/roro-core/   ← WordPress プラグイン / 插件 / 플러그인
frontend/            ← React + Vite SPA
docker/              ← ローカルスタック / 本地栈 / 로컬 스택
tests/               ← PHPUnit・Vitest・Playwright テストコード
```

---

## 🆕 What's New in 1.0.0 / 新機能 / 更新内容 / 새로운 점

* **EN:** Unified authentication — consolidates Firebase & LINE login.  
  **JA:** 認証方式を統合し Firebase と LINE ログインを一元化。  
  **ZH‑CN:** 统一身份验证，整合 Firebase 与 LINE 登录。  
  **KO:** 통합 인증 — Firebase 및 LINE 로그인 통합.  

* **EN:** Consolidated custom post types — registers *Pet Photo* & *Dog Advice* together.  
  **JA:** カスタム投稿タイプを統合し *Pet Photo* と *Dog Advice* を一括登録。  
  **ZH‑CN:** 合并自定义文章类型，统一注册 *Pet Photo* 与 *Dog Advice*。  
  **KO:** 커스텀 포스트 타입 통합 — *Pet Photo*, *Dog Advice* 를 함께 등록.  

* **EN:** AI advice caching — caches responses via WordPress transients.  
  **JA:** AI アドバイスを WordPress トランジェントにキャッシュ。  
  **ZH‑CN:** AI 建议使用 WordPress transient 进行缓存。  
  **KO:** AI 조언을 WordPress transient 로 캐싱.  

* **EN:** Cleaner codebase — removes redundant modules & adds lowercase PSR‑4 fallback.  
  **JA:** 冗長モジュールを削除し小文字フォールバック付き PSR‑4 オートローダーを追加。  
  **ZH‑CN:** 精简代码库，移除冗余模块并增加小写 PSR‑4 回退。  
  **KO:** 코드베이스 정리 — 중복 모듈 제거 및 소문자 PSR‑4 폴백 추가.  

---

## 📄 License / ライセンス / 许可证 / 라이선스
**Plugin:** GPL‑2.0+ **Front‑end:** MIT  
プラグ## 付録: 参考ディレクトリ（バックエンド/フロントエンド）

```
plugins/roro-core/   ← WordPress プラグイン
frontend/            ← React + Vite SPA
docker/              ← ローカルスタック
tests/               ← テストコード
```