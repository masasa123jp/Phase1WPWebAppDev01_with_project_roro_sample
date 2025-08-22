    # RORO Plugins Release 2025-08-22

    ## Tags
    - roro-auth: `v1.6.0`
    - roro-map: `v1.6.0`
    - roro-chatbot: `v1.6.0-rc2` (P3 Release Candidate)

    ## Highlights
    - **共通方針**: 「置くだけ運用」を徹底（assets/sql/{schema,seed} を配置 → 管理UIで実行）。
    - **セキュリティ**: Nonce 検証 / Capability チェック / XSS 対策（出力エスケープ）を各所で徹底。

    ---
    ## roro-auth v1.6.0
    ### Added
    - ソーシャル連携の解除 UI（Users → *Roro Social*）。唯一連携の誤解除を抑止。
    - プロフィール画像アップロードのショートコード `[roro_profile_avatar_form]`。
    - 通知テンプレート (verify/reset/unlink) と `roro_auth_mail_subject`/`roro_auth_mail_body` フィルタ。
    ### Changed
    - 解除処理は論理削除（`status='deleted'`）へ統一。通知メールを自動送信。

    ---
    ## roro-map v1.6.0
    ### Added
    - 自宅位置 GUI 保存ショートコード `[roro_home_location]`（REST `roro/v1/me/home` に POST）。
    - 距離ソート/ナイーブクラスタのヘルパ JS（マップライブラリ非依存）。

    ---
    ## roro-chatbot v1.6.0-rc2 (P3 RC)
    ### Added
    - Dify の SSE を WP REST でプロキシ中継（逐次描画）。
    - 会話ログダッシュボード（会話/メッセージ一覧、email 部分一致検索）。
    - チップ/カード UI（エラー時チップ、ユーザー/アシスタント装飾）。
    - 仕様: 近傍スポットカード `type:"spot_cards"`（JSON スキーマ別紙）。
    ### Notes
    - RC のため、UI/翻訳/細部の微調整は P3 Fix タグで追従予定。

    ---
    ## Database (roro-core-wp)
    - **DDL**: `DDL_20250822.sql`（テーブル/ビュー/トリガを 1 ファイル集約）。
    - **Seed**: カテゴリ/犬種/スポット/イベントの最小データ。
    - **Importer**: 管理画面「ツール → Roro DB Importer」（DDL/Seed 実行ボタン）。

    ## Upgrade Guide
    1. 事前に DB/ファイルをバックアップ。
    2. プラグイン Zip を管理画面からアップロード→有効化/更新。
    3. roro-core-wp の Importer で `schema → seed` の順に実行。
    4. 各プラグインの設定値（API キーなど）を投入。

    ## Checksums
    54601106bd78b25f57e8c4846bf87d54c0ac7518c4473c091e83889868948f9d  roro-auth-1.6.0.zip
cc2422391f91a710617e8254609d1681da463243857c8c4a1440a1f59c06c4df  roro-map-1.6.0.zip
c5cb53485861970fd0d3c87799412386f9f6448f998ead021a32051e1d51d35f  roro-chatbot-1.6.0-rc2.zip

