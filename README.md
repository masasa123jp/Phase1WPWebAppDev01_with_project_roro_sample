# RoRo Core — Phase 1.6 Pet Platform
> **EN:** AI‑driven WordPress plugin & React front‑end for pet‑care.  
> **JA:** ペットケア向け AI 活用 WordPress プラグイン + React フロントエンド。  
> **ZH‑CN:** 面向宠物护理的 AI 驱动 WordPress 插件与 React 前端。  
> **KO:** 반려동물 케어를 위한 AI 기반 WordPress 플러그인과 React 프런트엔드.

---

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
プラグインは GPL‑2.0+、フロントエンドは MIT ライセンスです。  
插件遵循 GPL‑2.0+，前端遵循 MIT 许可证。  
플러그인은 GPL‑2.0+, 프런트엔드는 MIT 라이선스입니다.
