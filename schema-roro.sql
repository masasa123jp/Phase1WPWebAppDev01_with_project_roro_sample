-- ---------------------------------------------------------------------------
-- RORO マスタ／業務テーブル定義
--
-- このスクリプトは、WordPress 環境から独立した MariaDB 用のスキーマです。
-- セクションごとにテーブルをまとめ、各テーブルおよびカラムに日本語の説明を付与しました。
-- 外部キーを参照するテーブルは参照先より後に記述されないよう並び替えています。
-- ALTER 文で追加されていた列は CREATE 文に統合済みです。
-- エンジンは InnoDB、文字コードは UTF8MB4 を統一的に指定しています。

/* -------------------------------------------------------------------------
 * 0. データベース作成
 * ---------------------------------------------------------------------- */

-- ログ専用データベースを作成します。WordPress 本体とは別 DB を利用する場合に使用します。
CREATE DATABASE IF NOT EXISTS `wp_roro_log`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_ja_0900_as_cs;
USE `wp_roro_log`;

/* -------------------------------------------------------------------------
 * 1. マスタ系
 * ---------------------------------------------------------------------- */

-- 犬種マスタ：犬種の基本情報を管理します。
CREATE TABLE IF NOT EXISTS `roro_dog_breed` (
  `breed_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '犬種ID',
  `name`         VARCHAR(64)  NOT NULL                COMMENT '犬種名',
  `category`     CHAR(1)      NOT NULL                COMMENT 'グループ区分（A〜H）',
  `size`         VARCHAR(32)  DEFAULT NULL            COMMENT 'サイズ区分',
  `risk_profile` TEXT         DEFAULT NULL            COMMENT '注意事項やリスクプロファイル',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
  PRIMARY KEY (`breed_id`),
  UNIQUE KEY `uk_roro_dog_breed_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='犬種マスタ';

-- 施設マスタ：各種施設（カフェ、病院等）の基本情報を保持します。
CREATE TABLE IF NOT EXISTS `roro_facility` (
  `facility_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '施設ID',
  `name`        VARCHAR(120)  NOT NULL                COMMENT '施設名',
  `category`    ENUM('cafe','hospital','salon','park','hotel','school','store') NOT NULL COMMENT '施設カテゴリ',
  `lat`         DECIMAL(10,8) NOT NULL               COMMENT '緯度',
  `lng`         DECIMAL(11,8) NOT NULL               COMMENT '経度',
  `facility_pt` POINT SRID 4326 NOT NULL /*!80000 INVISIBLE */ COMMENT '位置情報（ポイント型）',
  `address`     VARCHAR(191) DEFAULT NULL           COMMENT '住所',
  `phone`       VARCHAR(32)  DEFAULT NULL           COMMENT '電話番号',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
  PRIMARY KEY (`facility_id`),
  KEY `idx_roro_facility_category` (`category`),
  SPATIAL INDEX `spx_roro_facility_pt` (`facility_pt`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='施設マスタ';

-- スポンサーマスタ：広告主情報を管理します。
CREATE TABLE IF NOT EXISTS `roro_sponsor` (
  `sponsor_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'スポンサーID',
  `name`        VARCHAR(120) NOT NULL                COMMENT 'スポンサー名',
  `logo_url`    VARCHAR(255) DEFAULT NULL            COMMENT 'ロゴ画像URL',
  `website_url` VARCHAR(255) DEFAULT NULL            COMMENT 'WebサイトURL',
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状態（有効／無効）',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
  PRIMARY KEY (`sponsor_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='スポンサーマスタ';

/* -------------------------------------------------------------------------
 * 2. 顧客および認証関連
 * ---------------------------------------------------------------------- */

-- 顧客テーブル：アプリ利用者（犬の飼い主）の情報を格納します。
CREATE TABLE IF NOT EXISTS `roro_customer` (
  `customer_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '顧客ID',
  `name`           VARCHAR(80)   NOT NULL                COMMENT '氏名',
  `email`          VARCHAR(191)  NOT NULL                COMMENT 'メールアドレス',
  `auth_provider`  ENUM('local','firebase','line','google','facebook') NOT NULL DEFAULT 'local' COMMENT '認証プロバイダ',
  `user_type`      ENUM('free','premium','admin')        NOT NULL DEFAULT 'free' COMMENT 'ユーザー種別',
  `consent_status` ENUM('unknown','agreed','revoked')     NOT NULL DEFAULT 'unknown' COMMENT '規約同意状況',
  `phone`          VARCHAR(32)   DEFAULT NULL            COMMENT '電話番号',
  `zipcode`        CHAR(8)       DEFAULT NULL            COMMENT '郵便番号',
  `breed_id`       INT UNSIGNED NOT NULL                 COMMENT '飼っている犬種ID',
  `birth_date`     DATE         DEFAULT NULL            COMMENT '飼い主の生年月日',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uk_roro_customer_email` (`email`),
  KEY `idx_roro_customer_zipcode` (`zipcode`),
  KEY `idx_roro_customer_auth_provider` (`auth_provider`),
  CONSTRAINT `fk_roro_customer_breed`
      FOREIGN KEY (`breed_id`) REFERENCES `roro_dog_breed` (`breed_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='顧客情報';

-- 外部認証ID：Firebase／SNS などの UID と顧客・WordPress ユーザーを紐付けます。
CREATE TABLE IF NOT EXISTS `roro_identity` (
  `uid`         VARCHAR(128) NOT NULL                COMMENT '外部認証ID（UID）',
  `customer_id` INT UNSIGNED NOT NULL               COMMENT '顧客ID',
  `wp_user_id`  BIGINT UNSIGNED NOT NULL            COMMENT 'WordPress ユーザーID',
  `provider`    ENUM('firebase','line','google','facebook') NOT NULL DEFAULT 'firebase' COMMENT '認証プロバイダ',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
  PRIMARY KEY (`uid`),
  UNIQUE KEY `uk_roro_identity_customer` (`customer_id`),
  UNIQUE KEY `uk_roro_identity_user`     (`wp_user_id`),
  CONSTRAINT `fk_roro_identity_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='顧客の外部認証紐付け';

-- 通知設定：顧客が受け取る通知の種類と状態を保存します。
CREATE TABLE IF NOT EXISTS `roro_notification_pref` (
  `customer_id`        INT UNSIGNED NOT NULL COMMENT '顧客ID',
  `email_on`           TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'メール通知フラグ',
  `line_on`            TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'LINE 通知フラグ',
  `fcm_on`             TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'FCM プッシュ通知フラグ',
  `category_email_on`  TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'カテゴリ別メール通知フラグ',
  `category_push_on`   TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'カテゴリ別プッシュ通知フラグ',
  `token_expires_at`   TIMESTAMP   NULL DEFAULT NULL COMMENT '通知トークン有効期限',
  `updated_at`         TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`customer_id`),
  CONSTRAINT `fk_roro_notification_pref_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='通知設定';

/* -------------------------------------------------------------------------
 * 3. コンテンツ系
 * ---------------------------------------------------------------------- */

-- アドバイス：ペットに関するアドバイス記事を保存します。
CREATE TABLE IF NOT EXISTS `roro_advice` (
  `advice_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'アドバイスID',
  `title`      VARCHAR(120)  NOT NULL                COMMENT 'タイトル',
  `body`       MEDIUMTEXT    NOT NULL                COMMENT '本文',
  `category`   CHAR(1)       NOT NULL                COMMENT 'カテゴリ（A〜H）',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`advice_id`),
  KEY `idx_roro_advice_category` (`category`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='アドバイス記事';

-- 施設サブタイプ：施設の種別ごとの追加属性を管理します（カフェ、病院など）。
-- カフェ用サブテーブル
CREATE TABLE IF NOT EXISTS `roro_facility_cafe` (
  `facility_id`   INT UNSIGNED NOT NULL COMMENT '施設ID',
  `opening_hours` VARCHAR(191) DEFAULT NULL COMMENT '営業時間',
  `pet_menu`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'ペット用メニューの有無',
  PRIMARY KEY (`facility_id`),
  CONSTRAINT `fk_roro_facility_cafe_facility`
      FOREIGN KEY (`facility_id`) REFERENCES `roro_facility` (`facility_id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='施設サブタイプ（カフェ）';

-- 病院用サブテーブル
CREATE TABLE IF NOT EXISTS `roro_facility_hospital` (
  `facility_id`         INT UNSIGNED NOT NULL COMMENT '施設ID',
  `treatment_speciality` VARCHAR(191) DEFAULT NULL COMMENT '治療の専門分野',
  `emergency`            TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '救急対応可否',
  PRIMARY KEY (`facility_id`),
  CONSTRAINT `fk_roro_facility_hospital_facility`
      FOREIGN KEY (`facility_id`) REFERENCES `roro_facility` (`facility_id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='施設サブタイプ（病院）';

-- 他のサブタイプ（サロン、パーク、ホテル、スクール、ショップ）についても必要に応じて同様のテーブルを作成してください。

-- 施設レビュー：利用者からのレビュー情報を管理します。
CREATE TABLE IF NOT EXISTS `roro_facility_review` (
  `review_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'レビューID',
  `facility_id`  INT UNSIGNED DEFAULT NULL COMMENT '施設ID',
  `customer_id`  INT UNSIGNED DEFAULT NULL COMMENT '顧客ID',
  `rating`       TINYINT UNSIGNED NOT NULL COMMENT '評価（1〜5）',
  `comment`      TEXT DEFAULT NULL COMMENT 'コメント',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'レビュー作成日時',
  PRIMARY KEY (`review_id`),
  KEY `idx_roro_facility_review_facility` (`facility_id`),
  CONSTRAINT `fk_roro_facility_review_facility`
      FOREIGN KEY (`facility_id`) REFERENCES `roro_facility` (`facility_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_roro_facility_review_rating`
      CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='施設レビュー';

/* -------------------------------------------------------------------------
 * 4. 写真投稿・レポート
 * ---------------------------------------------------------------------- */

-- 写真投稿：ユーザーが投稿した写真と位置情報を保存します。
-- created_at による四半期パーティションを設定しています。
CREATE TABLE IF NOT EXISTS `roro_photo` (
  `photo_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '写真ID',
  `customer_id`   INT UNSIGNED DEFAULT NULL       COMMENT '顧客ID',
  `breed_id`      INT UNSIGNED DEFAULT NULL       COMMENT '犬種ID',
  `facility_id`   INT UNSIGNED DEFAULT NULL       COMMENT '施設ID',
  `attachment_id` BIGINT UNSIGNED NOT NULL        COMMENT 'WordPress 添付ファイルID',
  `zipcode`       CHAR(8)      DEFAULT NULL       COMMENT '郵便番号',
  `lat`           DECIMAL(10,8) DEFAULT NULL      COMMENT '緯度',
  `lng`           DECIMAL(11,8) DEFAULT NULL      COMMENT '経度',
  `photo_pt`      POINT SRID 4326 GENERATED ALWAYS AS (Point(`lng`,`lat`)) STORED COMMENT '位置情報（ポイント型）',
  `analysis_json` JSON DEFAULT NULL               COMMENT 'AI解析結果JSON',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '投稿日時',
  PRIMARY KEY (`photo_id`,`created_at`),
  KEY `idx_roro_photo_customer`   (`customer_id`),
  KEY `idx_roro_photo_breed`      (`breed_id`),
  KEY `idx_roro_photo_facility`   (`facility_id`),
  SPATIAL INDEX `spx_roro_photo` (`photo_pt`),
  CONSTRAINT `fk_roro_photo_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roro_photo_facility`
      FOREIGN KEY (`facility_id`) REFERENCES `roro_facility` (`facility_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roro_photo_breed`
      FOREIGN KEY (`breed_id`) REFERENCES `roro_dog_breed` (`breed_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='写真投稿'
  PARTITION BY RANGE (TO_DAYS(`created_at`)) (
    PARTITION `p2025q3` VALUES LESS THAN (TO_DAYS('2025-10-01')),
    PARTITION `p2025q4` VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION `pFuture` VALUES LESS THAN MAXVALUE
  );

-- レポート投稿：ユーザーが投稿する診断レポート等を保存します。
CREATE TABLE IF NOT EXISTS `roro_report` (
  `report_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'レポートID',
  `customer_id` INT UNSIGNED DEFAULT NULL           COMMENT '顧客ID',
  `content`     JSON NOT NULL                      COMMENT '投稿内容（JSON）',
  `breed_json`  VARCHAR(64) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`content`,'$.breed'))) STORED COMMENT '抽出された犬種',
  `age_month`   INT         GENERATED ALWAYS AS (JSON_EXTRACT(`content`,'$.age_month')) STORED COMMENT '抽出された月齢',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '投稿日時',
  PRIMARY KEY (`report_id`),
  KEY `idx_roro_report_breed_age` (`breed_json`,`age_month`),
  CONSTRAINT `fk_roro_report_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='診断・レポート投稿';

/* -------------------------------------------------------------------------
 * 5. ガチャ／広告／課金系
 * ---------------------------------------------------------------------- */

-- 広告案件：スポンサーによる広告を管理します。
CREATE TABLE IF NOT EXISTS `roro_ad` (
  `ad_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '広告ID',
  `sponsor_id` INT UNSIGNED NOT NULL               COMMENT 'スポンサーID',
  `title`      VARCHAR(120) NOT NULL               COMMENT '広告タイトル',
  `content`    TEXT         DEFAULT NULL           COMMENT '広告内容',
  `image_url`  VARCHAR(255) DEFAULT NULL           COMMENT '広告画像URL',
  `start_date` DATE         DEFAULT NULL           COMMENT '広告開始日',
  `end_date`   DATE         DEFAULT NULL           COMMENT '広告終了日',
  `price`      DECIMAL(10,2) NOT NULL DEFAULT 0    COMMENT '広告掲載料',
  `status`     ENUM('draft','active','expired') NOT NULL DEFAULT 'draft' COMMENT '状態',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`ad_id`),
  KEY `idx_roro_ad_sponsor` (`sponsor_id`),
  CONSTRAINT `fk_roro_ad_sponsor`
      FOREIGN KEY (`sponsor_id`) REFERENCES `roro_sponsor` (`sponsor_id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='広告案件';

-- 広告クリック：広告がクリックされた履歴を記録します。
CREATE TABLE IF NOT EXISTS `roro_ad_click` (
  `click_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'クリックID',
  `ad_id`       INT UNSIGNED NOT NULL              COMMENT '広告ID',
  `customer_id` INT UNSIGNED DEFAULT NULL          COMMENT '顧客ID（匿名の場合は NULL）',
  `clicked_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'クリック日時',
  PRIMARY KEY (`click_id`),
  KEY `idx_roro_ad_click_ad_id` (`ad_id`),
  CONSTRAINT `fk_roro_ad_click_ad`
      FOREIGN KEY (`ad_id`) REFERENCES `roro_ad` (`ad_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roro_ad_click_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='広告クリック履歴';

-- ガチャ履歴：ユーザーが回したガチャの履歴を記録します。四半期単位でパーティション分割しています。
CREATE TABLE IF NOT EXISTS `roro_gacha_log` (
  `spin_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ガチャ履歴ID',
  `customer_id` INT UNSIGNED DEFAULT NULL             COMMENT '顧客ID',
  `facility_id` INT UNSIGNED DEFAULT NULL             COMMENT '施設ID（賞品が施設の場合）',
  `advice_id`   INT UNSIGNED DEFAULT NULL             COMMENT 'アドバイスID（賞品がアドバイスの場合）',
  `prize_type`  ENUM('facility','advice','ad') NOT NULL COMMENT '賞品種別',
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0      COMMENT 'ガチャ金額',
  `sponsor_id`  INT UNSIGNED DEFAULT NULL             COMMENT 'スポンサーID（広告賞品の場合）',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '実行日時',
  PRIMARY KEY (`spin_id`,`created_at`),
  KEY `idx_roro_gacha_log_customer` (`customer_id`,`created_at`),
  CONSTRAINT `fk_roro_gacha_log_sponsor`
      FOREIGN KEY (`sponsor_id`) REFERENCES `roro_sponsor` (`sponsor_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='ガチャ履歴'
  PARTITION BY RANGE (TO_DAYS(`created_at`)) (
    PARTITION `p2025q3` VALUES LESS THAN (TO_DAYS('2025-10-01')),
    PARTITION `p2025q4` VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION `pFuture` VALUES LESS THAN MAXVALUE
  );

-- 収益ログ：広告やアフィリエイトなどの収益を記録します。
CREATE TABLE IF NOT EXISTS `roro_revenue` (
  `rev_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '収益ID',
  `customer_id` INT UNSIGNED DEFAULT NULL             COMMENT '顧客ID（関連がある場合）',
  `amount`      DECIMAL(10,2) NOT NULL                COMMENT '金額',
  `source`      ENUM('ad','affiliate','subscr') NOT NULL COMMENT '収益源',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '記録日時',
  PRIMARY KEY (`rev_id`,`created_at`),
  KEY `idx_roro_revenue_source_date` (`source`,`created_at`),
  CONSTRAINT `fk_roro_revenue_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='収益ログ'
  PARTITION BY RANGE (TO_DAYS(`created_at`)) (
    PARTITION `p2025q3` VALUES LESS THAN (TO_DAYS('2025-10-01')),
    PARTITION `p2025q4` VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION `pMax`  VALUES LESS THAN MAXVALUE
  );

-- 決済履歴：顧客またはスポンサーからの支払いを記録します。
CREATE TABLE IF NOT EXISTS `roro_payment` (
  `payment_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '決済ID',
  `customer_id`  INT UNSIGNED DEFAULT NULL          COMMENT '顧客ID',
  `sponsor_id`   INT UNSIGNED DEFAULT NULL          COMMENT 'スポンサーID',
  `method`       ENUM('credit','paypal','stripe','applepay','googlepay') NOT NULL COMMENT '決済手段',
  `amount`       DECIMAL(10,2) NOT NULL            COMMENT '支払金額',
  `status`       ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending' COMMENT '決済ステータス',
  `transaction_id` VARCHAR(191) DEFAULT NULL        COMMENT '決済トランザクションID',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '決済日時',
  PRIMARY KEY (`payment_id`),
  KEY `idx_roro_payment_customer_status` (`customer_id`,`status`),
  CONSTRAINT `fk_roro_payment_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roro_payment_sponsor`
      FOREIGN KEY (`sponsor_id`) REFERENCES `roro_sponsor` (`sponsor_id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='決済履歴';

/* -------------------------------------------------------------------------
 * 6. サポート・お問い合わせ
 * ---------------------------------------------------------------------- */

-- 課題マスタ：サービス改善のための課題を管理します。
CREATE TABLE IF NOT EXISTS `roro_issue` (
  `issue_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '課題ID',
  `name`       VARCHAR(80) NOT NULL                 COMMENT '課題名',
  `description` TEXT DEFAULT NULL                  COMMENT '説明',
  `priority`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '優先度',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`issue_id`),
  KEY `idx_roro_issue_priority` (`priority`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='課題マスタ';

-- お問い合わせ：ユーザーからの問い合わせを管理します。
CREATE TABLE IF NOT EXISTS `roro_contact` (
  `contact_id`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '問い合わせID',
  `customer_id` INT UNSIGNED DEFAULT NULL             COMMENT '顧客ID（匿名の場合は NULL）',
  `name`        VARCHAR(120) NOT NULL                COMMENT '送信者氏名',
  `email`       VARCHAR(191) NOT NULL                COMMENT '送信者メールアドレス',
  `subject`     VARCHAR(191) DEFAULT NULL            COMMENT '件名',
  `message`     TEXT NOT NULL                       COMMENT '内容',
  `status`      ENUM('new','processing','closed') NOT NULL DEFAULT 'new' COMMENT '対応状況',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '問い合わせ日時',
  PRIMARY KEY (`contact_id`),
  KEY `idx_roro_contact_customer` (`customer_id`),
  CONSTRAINT `fk_roro_contact_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `roro_customer` (`customer_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='お問い合わせ';

/* -------------------------------------------------------------------------
 * 7. イベント管理
 * ---------------------------------------------------------------------- */

-- 情報源マスタ：イベント取得元（ウェブサイト等）の情報を保存します。
CREATE TABLE IF NOT EXISTS `roro_event_source` (
  `source_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '情報源ID',
  `name`        VARCHAR(50)  NOT NULL               COMMENT '名称',
  `description` TEXT DEFAULT NULL                   COMMENT '説明',
  `base_url`    VARCHAR(255) DEFAULT NULL           COMMENT '基底URL',
  `notes`       TEXT DEFAULT NULL                   COMMENT '備考',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`source_id`),
  UNIQUE KEY `uk_roro_event_source_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='イベント情報源マスタ';

-- 開催地マスタ：都道府県・市区町村などの場所を管理します。
CREATE TABLE IF NOT EXISTS `roro_event_location` (
  `location_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '開催地ID',
  `prefecture`   VARCHAR(50) DEFAULT NULL            COMMENT '都道府県',
  `city`         VARCHAR(100) DEFAULT NULL           COMMENT '市区町村',
  `full_address` VARCHAR(255) DEFAULT NULL           COMMENT '詳細住所',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`location_id`),
  KEY `idx_roro_event_location_pref_city` (`prefecture`,`city`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='イベント開催地マスタ';

-- 会場マスタ：開催地に紐付けられる会場情報を格納します。
CREATE TABLE IF NOT EXISTS `roro_event_venue` (
  `venue_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '会場ID',
  `name`        VARCHAR(100) NOT NULL               COMMENT '会場名',
  `location_id` INT UNSIGNED DEFAULT NULL           COMMENT '開催地ID',
  `address`     VARCHAR(255) DEFAULT NULL           COMMENT '詳細住所',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`venue_id`),
  KEY `idx_roro_event_venue_name` (`name`),
  KEY `idx_roro_event_venue_location` (`location_id`),
  CONSTRAINT `fk_roro_event_venue_location`
      FOREIGN KEY (`location_id`) REFERENCES `roro_event_location` (`location_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='イベント会場マスタ';

-- 主催者マスタ：イベントを主催する団体や個人を管理します。
CREATE TABLE IF NOT EXISTS `roro_event_organizer` (
  `organizer_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主催者ID',
  `name`         VARCHAR(100) NOT NULL               COMMENT '主催者名',
  `description`  TEXT DEFAULT NULL                   COMMENT '説明',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`organizer_id`),
  KEY `idx_roro_event_organizer_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='イベント主催者マスタ';

-- イベント：イベント情報を包括的に管理します。
CREATE TABLE IF NOT EXISTS `roro_event` (
  `event_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'イベントID',
  `source_id`    INT UNSIGNED NOT NULL               COMMENT '情報源ID',
  `organizer_id` INT UNSIGNED DEFAULT NULL           COMMENT '主催者ID',
  `name`         VARCHAR(255) NOT NULL               COMMENT 'イベント名',
  `date_start`   DATE DEFAULT NULL                  COMMENT '開始日',
  `date_end`     DATE DEFAULT NULL                  COMMENT '終了日',
  `date_text`    VARCHAR(50) DEFAULT NULL           COMMENT '日付テキスト',
  `location_id`  INT UNSIGNED DEFAULT NULL           COMMENT '開催地ID',
  `venue_id`     INT UNSIGNED DEFAULT NULL           COMMENT '会場ID',
  `description`  TEXT DEFAULT NULL                   COMMENT '説明',
  `url`          VARCHAR(255) DEFAULT NULL           COMMENT 'イベントURL',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`event_id`),
  KEY `idx_roro_event_source_date` (`source_id`,`date_start`),
  KEY `idx_roro_event_date_range` (`date_start`,`date_end`),
  KEY `idx_roro_event_location` (`location_id`),
  KEY `idx_roro_event_venue` (`venue_id`),
  CONSTRAINT `fk_roro_event_source`
      FOREIGN KEY (`source_id`) REFERENCES `roro_event_source` (`source_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_roro_event_organizer`
      FOREIGN KEY (`organizer_id`) REFERENCES `roro_event_organizer` (`organizer_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roro_event_location`
      FOREIGN KEY (`location_id`) REFERENCES `roro_event_location` (`location_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_roro_event_venue`
      FOREIGN KEY (`venue_id`) REFERENCES `roro_event_venue` (`venue_id`) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='イベント';

/* -------------------------------------------------------------------------
 * 8. 観光・CRM 管理（観光スポットおよび顧客管理の拡張）
 * ---------------------------------------------------------------------- */

-- 都道府県マスタ：全国の都道府県を管理します。
CREATE TABLE IF NOT EXISTS `prefectures` (
  `prefecture_id` INT NOT NULL AUTO_INCREMENT COMMENT '都道府県ID',
  `name`          VARCHAR(50) NOT NULL               COMMENT '都道府県名',
  PRIMARY KEY (`prefecture_id`),
  UNIQUE KEY `uk_prefectures_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='都道府県マスタ';

-- 市区町村マスタ：都道府県に紐付く市区町村を管理します。
CREATE TABLE IF NOT EXISTS `cities` (
  `city_id`       INT NOT NULL AUTO_INCREMENT COMMENT '市区町村ID',
  `prefecture_id` INT NOT NULL                     COMMENT '都道府県ID',
  `name`          VARCHAR(100) NOT NULL             COMMENT '市区町村名',
  PRIMARY KEY (`city_id`),
  CONSTRAINT `fk_cities_prefecture`
      FOREIGN KEY (`prefecture_id`) REFERENCES `prefectures` (`prefecture_id`),
  KEY `idx_cities_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='市区町村マスタ';

-- 観光カテゴリマスタ：観光スポットのジャンルを管理します。
CREATE TABLE IF NOT EXISTS `spot_categories` (
  `category_id` INT NOT NULL AUTO_INCREMENT COMMENT 'カテゴリID',
  `name`        VARCHAR(100) NOT NULL               COMMENT 'カテゴリ名',
  `description` TEXT DEFAULT NULL                   COMMENT '説明',
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='観光カテゴリマスタ';

-- 観光スポット：各観光スポットの詳細情報を管理します。
-- 多言語対応のため、中国語・韓国語の名称および説明を CREATE 文に含めています。
CREATE TABLE IF NOT EXISTS `tourist_spots` (
  `spot_id`       INT NOT NULL AUTO_INCREMENT COMMENT 'スポットID',
  `name`          VARCHAR(200) NOT NULL               COMMENT 'スポット名（日本語）',
  `name_zh`       VARCHAR(200) DEFAULT NULL           COMMENT 'スポット名（中国語）',
  `name_ko`       VARCHAR(200) DEFAULT NULL           COMMENT 'スポット名（韓国語）',
  `prefecture_id` INT NOT NULL                        COMMENT '都道府県ID',
  `city_id`       INT NOT NULL                        COMMENT '市区町村ID',
  `category_id`   INT NOT NULL                        COMMENT 'カテゴリID',
  `description`   TEXT DEFAULT NULL                   COMMENT '説明（日本語）',
  `description_zh` TEXT DEFAULT NULL                  COMMENT '説明（中国語）',
  `description_ko` TEXT DEFAULT NULL                  COMMENT '説明（韓国語）',
  `image_url`     VARCHAR(500) DEFAULT NULL           COMMENT '画像URL',
  `address`       VARCHAR(300) DEFAULT NULL           COMMENT '住所',
  `price_range`   VARCHAR(50) DEFAULT NULL            COMMENT '価格帯',
  `rating`        DECIMAL(2,1) DEFAULT NULL           COMMENT '評価（平均）',
  PRIMARY KEY (`spot_id`),
  CONSTRAINT `fk_tourist_spots_prefecture`
      FOREIGN KEY (`prefecture_id`) REFERENCES `prefectures` (`prefecture_id`),
  CONSTRAINT `fk_tourist_spots_city`
      FOREIGN KEY (`city_id`) REFERENCES `cities` (`city_id`),
  CONSTRAINT `fk_tourist_spots_category`
      FOREIGN KEY (`category_id`) REFERENCES `spot_categories` (`category_id`),
  KEY `idx_tourist_spots_name` (`name`),
  KEY `idx_tourist_spots_pref_cat` (`prefecture_id`,`category_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='観光スポット';

-- 顧客テーブル（改修版）：観光系CRMの顧客情報。住所を都道府県と市区町村で保持します。
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` INT NOT NULL AUTO_INCREMENT COMMENT '顧客ID',
  `name`        VARCHAR(100) NOT NULL               COMMENT '氏名',
  `kana`        VARCHAR(100) NOT NULL               COMMENT 'フリガナ',
  `birthday`    DATE DEFAULT NULL                   COMMENT '生年月日',
  `email`       VARCHAR(200) DEFAULT NULL           COMMENT 'メールアドレス',
  `phone`       VARCHAR(20) DEFAULT NULL            COMMENT '電話番号',
  `zip_code`    VARCHAR(10) DEFAULT NULL            COMMENT '郵便番号',
  `prefecture_id` INT DEFAULT NULL                  COMMENT '都道府県ID',
  `city_id`       INT DEFAULT NULL                  COMMENT '市区町村ID',
  `address2`    VARCHAR(200) DEFAULT NULL           COMMENT '番地・建物名など',
  `remarks`     TEXT DEFAULT NULL                   COMMENT '備考',
  PRIMARY KEY (`customer_id`),
  CONSTRAINT `fk_customers_prefecture`
      FOREIGN KEY (`prefecture_id`) REFERENCES `prefectures` (`prefecture_id`),
  CONSTRAINT `fk_customers_city`
      FOREIGN KEY (`city_id`) REFERENCES `cities` (`city_id`),
  KEY `idx_customers_kana` (`kana`),
  UNIQUE KEY `uk_customers_email` (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='顧客（観光CRM）';

-- 顧客嗜好：顧客が好きなカテゴリとの中間テーブル。
CREATE TABLE IF NOT EXISTS `customer_preferences` (
  `customer_id` INT NOT NULL                     COMMENT '顧客ID',
  `category_id` INT NOT NULL                     COMMENT 'カテゴリID',
  PRIMARY KEY (`customer_id`,`category_id`),
  CONSTRAINT `fk_customer_preferences_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `fk_customer_preferences_category`
      FOREIGN KEY (`category_id`) REFERENCES `spot_categories` (`category_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='顧客嗜好カテゴリ中間テーブル';

-- お気に入りスポット：顧客が登録したお気に入りスポットを管理します。
CREATE TABLE IF NOT EXISTS `favorite_spots` (
  `customer_id` INT NOT NULL                     COMMENT '顧客ID',
  `spot_id`     INT NOT NULL                     COMMENT 'スポットID',
  `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
  PRIMARY KEY (`customer_id`,`spot_id`),
  CONSTRAINT `fk_favorite_spots_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `fk_favorite_spots_spot`
      FOREIGN KEY (`spot_id`) REFERENCES `tourist_spots` (`spot_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='お気に入りスポット';

-- ロールマスタ：システムにおけるロール（権限）を定義します。
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` INT NOT NULL AUTO_INCREMENT COMMENT 'ロールID',
  `name`    VARCHAR(50) NOT NULL               COMMENT 'ロール名',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='ロールマスタ';

-- ユーザテーブル：管理画面等で利用するユーザ情報。
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`       INT NOT NULL AUTO_INCREMENT COMMENT 'ユーザID',
  `username`      VARCHAR(50) NOT NULL               COMMENT 'ユーザ名',
  `password_hash` VARCHAR(255) NOT NULL              COMMENT 'パスワードハッシュ',
  `email`         VARCHAR(200) DEFAULT NULL          COMMENT 'メールアドレス',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='ユーザ';

-- ユーザとロールの中間テーブル：多対多の関係を保持します。
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT NOT NULL COMMENT 'ユーザID',
  `role_id` INT NOT NULL COMMENT 'ロールID',
  PRIMARY KEY (`user_id`,`role_id`),
  CONSTRAINT `fk_user_roles_user`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_user_roles_role`
      FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='ユーザロール中間テーブル';

-- 申込（予約）テーブル：観光スポットやサービスに対する申込みを管理します。
CREATE TABLE IF NOT EXISTS `applications` (
  `application_id` INT NOT NULL AUTO_INCREMENT COMMENT '申込ID',
  `customer_id`   INT NOT NULL                     COMMENT '顧客ID',
  `application_type` VARCHAR(50) NOT NULL          COMMENT '申込タイプ',
  `status`       ENUM('pending','approved','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT '状態',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`application_id`),
  CONSTRAINT `fk_applications_customer`
      FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='申込（予約）';

-- 申込項目テーブル：申込に紐付くスポットや数量、価格を保持します。
CREATE TABLE IF NOT EXISTS `application_items` (
  `application_item_id` INT NOT NULL AUTO_INCREMENT COMMENT '申込項目ID',
  `application_id`     INT NOT NULL                 COMMENT '申込ID',
  `spot_id`            INT NOT NULL                 COMMENT 'スポットID',
  `quantity`           INT NOT NULL DEFAULT 1       COMMENT '数量',
  `price`              DECIMAL(10,2) DEFAULT NULL   COMMENT '単価',
  PRIMARY KEY (`application_item_id`),
  CONSTRAINT `fk_application_items_application`
      FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`),
  CONSTRAINT `fk_application_items_spot`
      FOREIGN KEY (`spot_id`) REFERENCES `tourist_spots` (`spot_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_ja_0900_as_cs
  COMMENT='申込項目';
