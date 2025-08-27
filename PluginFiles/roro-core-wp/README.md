# Project RORO Web App Guide

このガイドは、プロジェクト RORO Web アプリの使い方と構築手順を図解したものです。セットアップからログイン、マップ表示、お気に入り管理、デプロイ方法まで、全体の流れをメインの視点から俯瞰できるようにまとめています。より詳細な内容は [sections](./sections) ディレクトリの各ファイルを参照してください。

## 全体像マインドマップ

```mermaid
mindmap
  root((Project RORO Web App))
    Setup & Keys
      "ローカルサーバー起動"
      "APIキーのBase64埋込"
    Authentication
      "新規登録/ログイン"
      "Google/LINE ソーシャルログイン"
    Map Features
      "言語切替 日本語/英語/中国語/韓国語"
      "カテゴリフィルタ イベント/レストラン/ホテル/アクティビティ/美術館・博物館/施設"
      "マーカー詳細とお気に入り登録"
      "ボトムナビ マップ/AI/お気に入り/雑誌/マイページ"
    Favorites & Magazine
      "お気に入り一覧"
      "月刊雑誌コンテンツ"
    Deployment
      "Windows セットアップ"
      "XServer への静的公開"
      "WordPress 連携"
```

## ファイル構成と操作フロー

以下のフローチャートは、ZIP 展開後のファイル構成とローカルサーバー起動、ユーザー操作の流れを表しています。

```mermaid
flowchart TD
    A[ZIP 展開<br/>project_root/] --> B[ディレクトリ構成]
    B --> B1[css/ : スタイル]
    B --> B2[js/ : JavaScript]
    B --> B3[images/ : アイコン等]
    B --> B4[data/ : events.json など]
    B --> B5[index.html : ログイン]
    B --> B6[signup.html : 新規登録]
    B --> B7[map.html : マップ]
    B --> B8[favorites.html : お気に入り]
    B --> B9[magazine.html : 雑誌]
    B --> B10[profile.html : マイページ]
    B --> B11[dify.html : AIチャット]
    A --> C[ローカルサーバー起動]
    C -->|python3 -m http.server 8000| D[http://localhost:8000/index.html]
    D --> E[ログイン]
    E -->|成功| F[map.html]
    E -->|未登録| G[signup.html]
    F --> H[カテゴリフィルタ/言語切替/お気に入り登録]
    F --> I[favorites.html]
    F --> J[magazine.html]
    F --> K[profile.html]
    F --> L[dify.html]
```

## APIキー注入シーケンス

```mermaid
sequenceDiagram
    participant Env as 環境変数
    participant Base64 as Base64 エンコード
    participant Meta as HTML metaタグ
    participant JS as JavaScript
    participant API as Google/HERE SDK

    Env->>Base64: APIキー、OAuthクライアントID、LIFF ID を提供
    Base64->>Meta: エンコードした値を<br/>content 属性へ埋込
    JS->>Meta: atob() でデコード
    JS->>API: 復号したキーで SDK を初期化
    API-->>JS: マップ読み込み / 認証フロー開始
```

## 言語切替ステート図

```mermaid
stateDiagram-v2
    [*] --> Japanese : 初期状態
    Japanese --> English : 地球アイコンをクリック
    English --> Chinese : 再クリック
    Chinese --> Korean : 再クリック
    Korean --> Japanese : 再クリック

    state Japanese {
      [*] --> GoogleMaps
    }
    state English {
      [*] --> GoogleMaps
    }
    state Korean {
      [*] --> GoogleMaps
    }
    state Chinese {
      [*] --> GoogleMaps
    }
```

## お気に入り ER 図

```mermaid
erDiagram
    USERS ||--o{ FAVORITE_LISTS : has
    FAVORITE_LISTS ||--|{ FAVORITE_ITEMS : contains
    EVENTS ||--o{ FAVORITE_ITEMS : "登録先イベント"

    USERS {
      string user_id "ローカルStorageキー"
      string name
      string email
      string password
      string pet_info
    }
    FAVORITE_LISTS {
      string list_id
      string user_id
      string list_name "お気に入り/行ってみたい/旅行プラン/スター付き"
    }
    FAVORITE_ITEMS {
      string item_id
      string list_id
      string event_id
    }
    EVENTS {
      string event_id
      string name
      string category
      date date
      string address
      string url
    }
```

各図は概要を示しており、実装の詳細はセクションごとに説明しています。
