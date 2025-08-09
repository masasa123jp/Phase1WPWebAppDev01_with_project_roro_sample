<?php
/**
 * RoRo Core – DB schema installer / upgrader (Phase 1.6 対応版)
 * ---------------------------------------------------------------------------
 *  本モジュールはプラグイン有効化時に下記を自動作成・更新します。
 *
 *    1. マスタ                       : 犬種 / 施設 / スポンサー
 *    2. 顧客 & 認証                 : customer / identity / 通知設定
 *    3. コンテンツ                   : advice / facility_review / facility_* サブタイプ
 *    4. 投稿・履歴                   : photo / report / gacha_log / revenue
 *    5. 広告・課金                   : ad / ad_click / payment
 *    6. サポート                     : issue / contact
 *
 *  制約事項
 *    • WordPress dbDelta() は外部キーとパーティションを処理しないため、
 *      ①本体 CREATE/ALTER → dbDelta()          ②外部キー → ALTER
 *      ③パーティション → ALTER の３段階で実行します。
 *    • 既存サイトでも不足列は自動追加されるため手動 ALTER は不要です。
 *
 *  更新履歴
 *    1.6.0 : Phase 1.6 スキーマ統合版
 *
 * @package RoroCore
 */

namespace RoroCore\Db;
defined( 'ABSPATH' ) || exit;

use wpdb;

final class Schema {

	/** スキーマバージョン（変更時に +0.0.1 ずつ上げる） */
	const VERSION = '1.6.0';

	/**
	 * register_activation_hook() から呼ばれるエントリポイント
	 *
	 * @return void
	 */
	public static function install(): void {

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();    // 例: utf8mb4_ja_0900_as_cs
		$p       = $wpdb->prefix;                   // WP テーブル接頭辞

		/* -----------------------------------------------------------------
		 * 1) dbDelta() 実行対象 – 外部キー／パーティションを含まない完全定義
		 * ----------------------------------------------------------------- */
$schema = <<<SQL
/* ====================== 1. マスタ ====================== */
CREATE TABLE {$p}roro_dog_breed(
  breed_id      INT UNSIGNED AUTO_INCREMENT,
  name          VARCHAR(64)  NOT NULL,
  category      CHAR(1)      NOT NULL,              -- A〜H
  size          VARCHAR(32),
  risk_profile  TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (breed_id),
  UNIQUE KEY uk_breed_name(name)
) $charset ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE {$p}roro_facility(
  facility_id INT UNSIGNED AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  category    ENUM('cafe','hospital','salon','park','hotel','school','store') NOT NULL,
  lat         DECIMAL(10,8) NOT NULL,
  lng         DECIMAL(11,8) NOT NULL,
  address     VARCHAR(191),
  phone       VARCHAR(32),
  facility_pt POINT SRID 4326 NOT NULL /*!80000 INVISIBLE */,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (facility_id),
  KEY idx_category(category),
  SPATIAL INDEX spx_fac_pt(facility_pt)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_sponsor(
  sponsor_id  INT UNSIGNED AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  logo_url    VARCHAR(255) DEFAULT NULL,
  website_url VARCHAR(255) DEFAULT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(sponsor_id)
) $charset ENGINE=InnoDB;

/* ================= 2. 顧客 & 認証関連 ================= */
CREATE TABLE {$p}roro_customer(
  customer_id    INT UNSIGNED AUTO_INCREMENT,
  name           VARCHAR(80)  NOT NULL,
  email          VARCHAR(191) NOT NULL,
  auth_provider  ENUM('local','firebase','line','google','facebook') NOT NULL DEFAULT 'local',
  user_type      ENUM('free','premium','admin') NOT NULL DEFAULT 'free',
  consent_status ENUM('unknown','agreed','revoked') NOT NULL DEFAULT 'unknown',
  phone          VARCHAR(32),
  zipcode        CHAR(8),
  breed_id       INT UNSIGNED NOT NULL,
  birth_date     DATE,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(customer_id),
  UNIQUE KEY uk_email(email),
  KEY idx_zip(zipcode),
  KEY idx_auth_provider(auth_provider)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_notification_pref(
  customer_id       INT UNSIGNED PRIMARY KEY,
  email_on          TINYINT(1) DEFAULT 1,
  line_on           TINYINT(1) DEFAULT 1,
  fcm_on            TINYINT(1) DEFAULT 0,
  category_email_on TINYINT(1) DEFAULT 1,
  category_push_on  TINYINT(1) DEFAULT 1,
  token_expires_at  TIMESTAMP NULL DEFAULT NULL,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_identity(
  uid         VARCHAR(128) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  wp_user_id  BIGINT UNSIGNED NOT NULL,
  provider    ENUM('firebase','line','google','facebook') NOT NULL DEFAULT 'firebase',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(uid),
  UNIQUE KEY uk_customer(customer_id),
  UNIQUE KEY uk_wpuser(wp_user_id)
) $charset ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

/* ================ 3. コンテンツ & サブタイプ ================ */
CREATE TABLE {$p}roro_advice(
  advice_id  INT UNSIGNED AUTO_INCREMENT,
  title      VARCHAR(120) NOT NULL,
  body       MEDIUMTEXT   NOT NULL,
  category   CHAR(1)      NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(advice_id),
  KEY idx_cat(category)
) $charset ENGINE=InnoDB;

/* 施設サブタイプ（カフェ例） */
CREATE TABLE {$p}roro_facility_cafe(
  facility_id   INT UNSIGNED PRIMARY KEY,
  opening_hours VARCHAR(191),
  pet_menu      TINYINT(1) DEFAULT 0
) $charset ENGINE=InnoDB;

/* 施設サブタイプ（病院例） */
CREATE TABLE {$p}roro_facility_hospital(
  facility_id          INT UNSIGNED PRIMARY KEY,
  treatment_speciality VARCHAR(191),
  emergency            TINYINT(1) DEFAULT 0
) $charset ENGINE=InnoDB;

/* 施設レビュー */
CREATE TABLE {$p}roro_facility_review(
  review_id    BIGINT UNSIGNED AUTO_INCREMENT,
  facility_id  INT UNSIGNED,
  customer_id  INT UNSIGNED,
  rating       TINYINT UNSIGNED NOT NULL CHECK(rating BETWEEN 1 AND 5),
  comment      TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(review_id),
  KEY idx_fac_rating(facility_id, rating)
) $charset ENGINE=InnoDB;

/* ================ 4. 投稿・レポート・ログ ================ */
CREATE TABLE {$p}roro_photo(
  photo_id      BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id   INT UNSIGNED,
  breed_id      INT UNSIGNED,
  facility_id   INT UNSIGNED DEFAULT NULL,
  attachment_id BIGINT UNSIGNED NOT NULL,
  zipcode       CHAR(8),
  lat           DECIMAL(10,8),
  lng           DECIMAL(11,8),
  photo_pt      POINT SRID 4326 GENERATED ALWAYS AS
                (IF(lat IS NULL OR lng IS NULL,NULL,Point(lng,lat))) STORED,
  analysis_json JSON NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(photo_id, created_at),
  KEY idx_cust(customer_id),
  KEY idx_breed(breed_id),
  KEY idx_facility(facility_id),
  SPATIAL INDEX spx_photo(photo_pt)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_report(
  report_id   BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id INT UNSIGNED,
  content     JSON NOT NULL,
  breed_json  VARCHAR(64) GENERATED ALWAYS AS
              (JSON_UNQUOTE(JSON_EXTRACT(content,'$.breed'))) STORED,
  age_month   INT GENERATED ALWAYS AS
              (JSON_EXTRACT(content,'$.age_month')) STORED,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(report_id),
  KEY idx_breed_age(breed_json, age_month),
  KEY idx_cust(customer_id)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_gacha_log(
  spin_id     BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id INT UNSIGNED,
  facility_id INT UNSIGNED,
  advice_id   INT UNSIGNED,
  prize_type  ENUM('facility','advice','ad') NOT NULL,
  price       DECIMAL(10,2) DEFAULT 0,
  sponsor_id  INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(spin_id, created_at),
  KEY idx_cust_date(customer_id, created_at)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_revenue(
  rev_id      BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id INT UNSIGNED,
  amount      DECIMAL(10,2) NOT NULL,
  source      ENUM('ad','affiliate','subscr') NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(rev_id, created_at),
  KEY idx_source(source)
) $charset ENGINE=InnoDB;

/* ================ 5. 広告 & 課金 ================ */
CREATE TABLE {$p}roro_ad(
  ad_id      INT UNSIGNED AUTO_INCREMENT,
  sponsor_id INT UNSIGNED NOT NULL,
  title      VARCHAR(120) NOT NULL,
  content    TEXT,
  image_url  VARCHAR(255) DEFAULT NULL,
  start_date DATE,
  end_date   DATE,
  price      DECIMAL(10,2) DEFAULT 0,
  status     ENUM('draft','active','expired') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(ad_id),
  KEY idx_sponsor(sponsor_id)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_ad_click(
  click_id    BIGINT UNSIGNED AUTO_INCREMENT,
  ad_id       INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  clicked_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(click_id),
  KEY idx_ad(ad_id)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_payment(
  payment_id     BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id    INT UNSIGNED NULL,
  sponsor_id     INT UNSIGNED NULL,
  method         ENUM('credit','paypal','stripe','applepay','googlepay') NOT NULL,
  amount         DECIMAL(10,2) NOT NULL,
  status         ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  transaction_id VARCHAR(191) DEFAULT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(payment_id),
  KEY idx_cust_status(customer_id, status)
) $charset ENGINE=InnoDB;

/* ================ 6. サポート ================ */
CREATE TABLE {$p}roro_issue(
  issue_id    INT UNSIGNED AUTO_INCREMENT,
  name        VARCHAR(80) NOT NULL,
  description TEXT,
  priority    TINYINT UNSIGNED DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(issue_id),
  KEY idx_priority(priority)
) $charset ENGINE=InnoDB;

CREATE TABLE {$p}roro_contact(
  contact_id  BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id INT UNSIGNED NULL,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(191) NOT NULL,
  subject     VARCHAR(191) DEFAULT NULL,
  message     TEXT NOT NULL,
  status      ENUM('new','processing','closed') NOT NULL DEFAULT 'new',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(contact_id),
  KEY idx_contact_customer(customer_id)
) $charset ENGINE=InnoDB;

    /* ====================== 7. イベント管理 ====================== */

    /* 7-1. 情報源マスタ */
    CREATE TABLE {$p}roro_event_source(
      source_id   INT UNSIGNED AUTO_INCREMENT,
      name        VARCHAR(50) NOT NULL,
      description TEXT,
      base_url    VARCHAR(255),
      notes       TEXT,
      created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (source_id),
      UNIQUE KEY uk_event_source_name(name)
    ) $charset ENGINE=InnoDB;

    /* 7-2. 開催地（都道府県・市区町村など） */
    CREATE TABLE {$p}roro_event_location(
      location_id   INT UNSIGNED AUTO_INCREMENT,
      prefecture    VARCHAR(50) DEFAULT NULL,
      city          VARCHAR(100) DEFAULT NULL,
      full_address  VARCHAR(255) DEFAULT NULL,
      created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY(location_id),
      KEY idx_event_location(prefecture, city)
    ) $charset ENGINE=InnoDB;

    /* 7-3. 会場マスタ */
    CREATE TABLE {$p}roro_event_venue(
      venue_id    INT UNSIGNED AUTO_INCREMENT,
      name        VARCHAR(100) NOT NULL,
      location_id INT UNSIGNED DEFAULT NULL,
      address     VARCHAR(255) DEFAULT NULL,
      created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (venue_id),
      KEY idx_event_venue_name(name),
      KEY idx_event_venue_location(location_id)
    ) $charset ENGINE=InnoDB;

    /* 7-4. 主催者マスタ */
    CREATE TABLE {$p}roro_event_organizer(
      organizer_id INT UNSIGNED AUTO_INCREMENT,
      name         VARCHAR(100) NOT NULL,
      description  TEXT,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (organizer_id),
      KEY idx_event_organizer_name(name)
    ) $charset ENGINE=InnoDB;

    /* 7-5. イベント */
    CREATE TABLE {$p}roro_event(
      event_id     BIGINT UNSIGNED AUTO_INCREMENT,
      source_id    INT UNSIGNED NOT NULL,
      organizer_id INT UNSIGNED DEFAULT NULL,
      name         VARCHAR(255) NOT NULL,
      date_start   DATE DEFAULT NULL,
      date_end     DATE DEFAULT NULL,
      date_text    VARCHAR(50) DEFAULT NULL,
      location_id  INT UNSIGNED DEFAULT NULL,
      venue_id     INT UNSIGNED DEFAULT NULL,
      description  TEXT,
      url          VARCHAR(255) DEFAULT NULL,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (event_id),
      KEY idx_event_source_date(source_id, date_start),
      KEY idx_event_date_range(date_start, date_end),
      KEY idx_event_location(location_id),
      KEY idx_event_venue(venue_id)
    ) $charset ENGINE=InnoDB;
SQL;

		/* ---------- 1. dbDelta() で CREATE / ALTER ---------- */
		dbDelta( $schema );

		/* -----------------------------------------------------------------
		 * 2) 外部キー追加（dbDelta は無視するため手動 ALTER）
		 * ----------------------------------------------------------------- */
		$fk_queries = [

			/* 顧客関連 */
			"ALTER TABLE {$p}roro_customer
			   ADD CONSTRAINT fk_customer_breed
			   FOREIGN KEY(breed_id) REFERENCES {$p}roro_dog_breed(breed_id)
			   ON DELETE RESTRICT",

			"ALTER TABLE {$p}roro_notification_pref
			   ADD CONSTRAINT fk_pref_customer
			   FOREIGN KEY(customer_id) REFERENCES {$p}roro_customer(customer_id)
			   ON DELETE CASCADE",

			/* Identity → customer / users */
			"ALTER TABLE {$p}roro_identity
			   ADD CONSTRAINT fk_ident_customer FOREIGN KEY(customer_id)
			   REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_identity
			   ADD CONSTRAINT fk_ident_user FOREIGN KEY(wp_user_id)
			   REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE",

			/* Photo */
			"ALTER TABLE {$p}roro_photo
			   ADD CONSTRAINT fk_photo_customer FOREIGN KEY(customer_id)
			   REFERENCES {$p}roro_customer(customer_id) ON DELETE SET NULL",

			"ALTER TABLE {$p}roro_photo
			   ADD CONSTRAINT fk_photo_breed FOREIGN KEY(breed_id)
			   REFERENCES {$p}roro_dog_breed(breed_id) ON DELETE SET NULL",

			"ALTER TABLE {$p}roro_photo
			   ADD CONSTRAINT fk_photo_facility FOREIGN KEY(facility_id)
			   REFERENCES {$p}roro_facility(facility_id) ON DELETE SET NULL",

			/* Facility review */
			"ALTER TABLE {$p}roro_facility_review
			   ADD CONSTRAINT fk_review_facility FOREIGN KEY(facility_id)
			   REFERENCES {$p}roro_facility(facility_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_facility_review
			   ADD CONSTRAINT fk_review_customer FOREIGN KEY(customer_id)
			   REFERENCES {$p}roro_customer(customer_id) ON DELETE SET NULL",

			/* サブタイプ → facility */
			"ALTER TABLE {$p}roro_facility_cafe
			   ADD CONSTRAINT fk_cafe_facility FOREIGN KEY(facility_id)
			   REFERENCES {$p}roro_facility(facility_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_facility_hospital
			   ADD CONSTRAINT fk_hosp_facility FOREIGN KEY(facility_id)
			   REFERENCES {$p}roro_facility(facility_id) ON DELETE CASCADE",

			/* Ad & click */
			"ALTER TABLE {$p}roro_ad
			   ADD CONSTRAINT fk_ad_sponsor FOREIGN KEY(sponsor_id)
			   REFERENCES {$p}roro_sponsor(sponsor_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_ad_click
			   ADD CONSTRAINT fk_click_ad FOREIGN KEY(ad_id)
			   REFERENCES {$p}roro_ad(ad_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_ad_click
			   ADD CONSTRAINT fk_click_customer FOREIGN KEY(customer_id)
			   REFERENCES {$p}roro_customer(customer_id) ON DELETE SET NULL",

			/* Payment */
			"ALTER TABLE {$p}roro_payment
			   ADD CONSTRAINT fk_payment_customer FOREIGN KEY(customer_id)
			   REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_payment
			   ADD CONSTRAINT fk_payment_sponsor FOREIGN KEY(sponsor_id)
			   REFERENCES {$p}roro_sponsor(sponsor_id) ON DELETE CASCADE",

			/* Gacha log */
			"ALTER TABLE {$p}roro_gacha_log
			   ADD CONSTRAINT fk_gacha_sponsor FOREIGN KEY(sponsor_id)
			   REFERENCES {$p}roro_sponsor(sponsor_id) ON DELETE SET NULL",

			/* イベント関連 外部キー */
			"ALTER TABLE {$p}roro_event_venue
			   ADD CONSTRAINT fk_event_venue_location FOREIGN KEY(location_id)
			   REFERENCES {$p}roro_event_location(location_id) ON DELETE SET NULL",

			"ALTER TABLE {$p}roro_event
			   ADD CONSTRAINT fk_event_source FOREIGN KEY(source_id)
			   REFERENCES {$p}roro_event_source(source_id) ON DELETE CASCADE",

			"ALTER TABLE {$p}roro_event
			   ADD CONSTRAINT fk_event_organizer FOREIGN KEY(organizer_id)
			   REFERENCES {$p}roro_event_organizer(organizer_id) ON DELETE SET NULL",

			"ALTER TABLE {$p}roro_event
			   ADD CONSTRAINT fk_event_location FOREIGN KEY(location_id)
			   REFERENCES {$p}roro_event_location(location_id) ON DELETE SET NULL",

			"ALTER TABLE {$p}roro_event
			   ADD CONSTRAINT fk_event_venue FOREIGN KEY(venue_id)
			   REFERENCES {$p}roro_event_venue(venue_id) ON DELETE SET NULL",

		];
		foreach ( $fk_queries as $q ) {
			$wpdb->query( $q );   // すでに存在する場合はスキップされる
		}

		/* -----------------------------------------------------------------
		 * 3) 大量テーブルのパーティション化
		 * -----------------------------------------------------------------
		 *   dbDelta 後に ALTER で追加。既存環境で失敗してもログ出力のみ。
		 * ----------------------------------------------------------------- */
		$part_queries = [

			/* 四半期パーティション例：photo */
			"ALTER TABLE {$p}roro_photo
			   PARTITION BY RANGE (TO_DAYS(created_at)) (
			     PARTITION p2025q3 VALUES LESS THAN (TO_DAYS('2025-10-01')),
			     PARTITION p2025q4 VALUES LESS THAN (TO_DAYS('2026-01-01')),
			     PARTITION pFuture VALUES LESS THAN MAXVALUE
			   )",

			/* 月パーティション例：gacha_log */
			"ALTER TABLE {$p}roro_gacha_log
			   PARTITION BY RANGE (TO_DAYS(created_at)) (
			     PARTITION p2025q3 VALUES LESS THAN (TO_DAYS('2025-10-01')),
			     PARTITION p2025q4 VALUES LESS THAN (TO_DAYS('2026-01-01')),
			     PARTITION pFuture VALUES LESS THAN MAXVALUE
			   )",

			/* 年パーティション例：revenue */
			"ALTER TABLE {$p}roro_revenue
			   PARTITION BY RANGE (YEAR(created_at)) (
			     PARTITION p2025 VALUES LESS THAN (2026),
			     PARTITION pMax  VALUES LESS THAN MAXVALUE
			   )",
		];
		foreach ( $part_queries as $q ) {
			@$wpdb->query( $q ); // 失敗しても致命的でないので抑制
		}

		/* -----------------------------------------------------------------
		 * 4) バージョン保存 & ルール再生成
		 * ----------------------------------------------------------------- */
		update_option( 'roro_schema_version', self::VERSION );
		flush_rewrite_rules();
	}
}
