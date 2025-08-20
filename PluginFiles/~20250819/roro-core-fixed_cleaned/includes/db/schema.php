<?php
/**
 * schema_20250811.php (fixed)
 * Single-file installer for RoRo schema (SQL + dbDelta), with editor-safe guards.
 * - Avoids "unknown function dbDelta" in static analysis by using call_user_func.
 * - Guards WP-CLI usage so editors don't flag unknown class.
 */

if (!defined('ABSPATH')) { define('ABSPATH', dirname(__FILE__) . '/'); } // for safety when parsed by editors

if (!class_exists('Roro_Schema_20250811')) {
class Roro_Schema_20250811 {

  const VERSION = '2025.08.11';

  /** Standalone raw SQL (FK/Spatial included). */
  public static function raw_sql() {
    return <<<SQL
/*!40101 SET NAMES utf8mb4 */;
/*!40101 SET SQL_MODE='' */;
/*!40014 SET FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET CHARACTER_SET_CLIENT=utf8mb4 */;
/*!40101 SET CHARACTER_SET_RESULTS=utf8mb4 */;
/*!40101 SET COLLATION_CONNECTION=utf8mb4_general_ci */;

-- =========================
--  Core
-- =========================

CREATE TABLE IF NOT EXISTS roro_customer (
  customer_id       INT UNSIGNED AUTO_INCREMENT,
  email             VARCHAR(191) NOT NULL,
  postal_code       CHAR(7) NULL,
  country_code      CHAR(2) NULL,
  prefecture        VARCHAR(50) NULL,
  city              VARCHAR(100) NULL,
  address_line1     VARCHAR(120) NULL,
  address_line2     VARCHAR(120) NULL,
  building          VARCHAR(120) NULL,
  user_type         ENUM('local','social','admin') NOT NULL DEFAULT 'local',
  default_pet_id    BIGINT UNSIGNED NULL, -- FK added later
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  UNIQUE KEY uk_customer_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_user_link_wp (
  customer_id  INT UNSIGNED NOT NULL,
  wp_user_id   BIGINT UNSIGNED NOT NULL,
  linked_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  UNIQUE KEY uk_wp_user (wp_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_auth_account (
  account_id        BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id       INT UNSIGNED NOT NULL,
  provider          ENUM('local','google','line','x','facebook','apple','github','other') NOT NULL,
  provider_user_id  VARCHAR(191) NULL,
  email             VARCHAR(191) NULL,
  email_verified    TINYINT(1) NOT NULL DEFAULT 0,
  password_hash     VARCHAR(255) NULL,
  status            ENUM('active','locked','deleted') NOT NULL DEFAULT 'active',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at     DATETIME NULL,
  PRIMARY KEY (account_id),
  UNIQUE KEY uk_provider_uid (provider, provider_user_id),
  UNIQUE KEY uk_provider_email (provider, email),
  KEY idx_auth_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_auth_session (
  session_id         BIGINT UNSIGNED AUTO_INCREMENT,
  account_id         BIGINT UNSIGNED NOT NULL,
  customer_id        INT UNSIGNED NOT NULL,
  refresh_token_hash CHAR(64) NOT NULL,
  issued_at          DATETIME NOT NULL,
  expires_at         DATETIME NOT NULL,
  revoked_at         DATETIME NULL,
  ip                 VARCHAR(64) NULL,
  user_agent_hash    CHAR(64) NULL,
  PRIMARY KEY (session_id),
  UNIQUE KEY uk_refresh (refresh_token_hash),
  KEY idx_sess_account (account_id),
  KEY idx_sess_customer_exp (customer_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_auth_token (
  token_id     BIGINT UNSIGNED AUTO_INCREMENT,
  account_id   BIGINT UNSIGNED NOT NULL,
  kind         ENUM('verify_email','password_reset','oauth_state') NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  payload_json JSON NULL,
  expires_at   DATETIME NOT NULL,
  used_at      DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_id),
  UNIQUE KEY uk_kind_token (kind, token_hash),
  KEY idx_token_account_kind (account_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_dog_breed (
  breed_id    INT UNSIGNED AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  group_name  VARCHAR(120) NULL,
  PRIMARY KEY (breed_id),
  UNIQUE KEY uk_breed_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_pet (
  pet_id               BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id          INT UNSIGNED NOT NULL,
  species              ENUM('dog','cat','other') NOT NULL,
  breed_id             INT UNSIGNED NULL,
  breed_label          VARCHAR(255) NULL,
  sex                  ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
  birth_date           DATE NULL,
  weight_kg            DECIMAL(5,2) NULL,
  photo_attachment_id  BIGINT UNSIGNED NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pet_id),
  KEY idx_pet_customer (customer_id),
  KEY idx_pet_breed (breed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_facility (
  facility_id  BIGINT UNSIGNED AUTO_INCREMENT,
  name         VARCHAR(191) NOT NULL,
  category     VARCHAR(64) NULL,
  lat          DECIMAL(10,8) NULL,
  lng          DECIMAL(11,8) NULL,
  facility_pt  POINT SRID 4326
    /*!80003 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(Point(lng,lat),4326))) STORED */,
  PRIMARY KEY (facility_id),
  KEY idx_facility_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_map_favorite (
  favorite_id     BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id     INT UNSIGNED NOT NULL,
  target_type     ENUM('facility','spot','custom','place') NOT NULL,
  target_id       BIGINT UNSIGNED NULL,
  google_place_id VARCHAR(128) NULL,
  label           VARCHAR(120) NULL,
  lat             DECIMAL(10,8) NULL,
  lng             DECIMAL(11,8) NULL,
  place_pt        POINT SRID 4326
    /*!80003 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(Point(lng,lat),4326))) STORED */,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (favorite_id),
  UNIQUE KEY uk_customer_place (customer_id, google_place_id),
  KEY idx_fav_customer_time (customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_ai_conversation (
  conv_id     BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  provider    ENUM('openai','azure_openai','dify','local') NOT NULL,
  model       VARCHAR(64) NULL,
  purpose     ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
  meta        JSON NULL,
  started_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conv_id),
  KEY idx_conv_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_ai_message (
  msg_id       BIGINT UNSIGNED AUTO_INCREMENT,
  conv_id      BIGINT UNSIGNED NOT NULL,
  role         ENUM('system','user','assistant','tool') NOT NULL,
  content      MEDIUMTEXT NOT NULL,
  token_input  INT NULL,
  token_output INT NULL,
  cost_usd     DECIMAL(10,4) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (msg_id),
  KEY idx_msg_conv (conv_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_link_click (
  click_id       BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id    INT UNSIGNED NULL,
  context_type   ENUM('ad','advice','facility','event','other') NOT NULL,
  context_id     BIGINT UNSIGNED NULL,
  url            VARCHAR(512) NOT NULL,
  referrer       VARCHAR(255) NULL,
  ip_hash        CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (click_id),
  KEY idx_click_customer_time (customer_id, created_at),
  KEY idx_click_context (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_recommendation_log (
  rec_id       BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  item_type    ENUM('advice','facility','event','product','pet_item') NOT NULL,
  item_id      BIGINT UNSIGNED NOT NULL,
  channel      ENUM('app','web','email','line','push') NOT NULL DEFAULT 'app',
  reason       JSON NULL,
  delivered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  impression_at TIMESTAMP NULL DEFAULT NULL,
  click_at      TIMESTAMP NULL DEFAULT NULL,
  dismissed_at  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (rec_id),
  KEY idx_rec_customer_time (customer_id, delivered_at),
  KEY idx_rec_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_consent_log (
  log_id      BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  old_status  ENUM('unknown','agreed','revoked') NULL,
  new_status  ENUM('unknown','agreed','revoked') NOT NULL,
  changed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_consent_customer_time (customer_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_audit_event (
  audit_id          BIGINT UNSIGNED AUTO_INCREMENT,
  actor_type        ENUM('user','admin','system') NOT NULL,
  actor_wp_user_id  BIGINT UNSIGNED NULL,
  actor_customer_id INT UNSIGNED NULL,
  event_type        VARCHAR(50) NOT NULL,
  entity_table      VARCHAR(64) NOT NULL,
  entity_pk         VARCHAR(64) NOT NULL,
  before_json       JSON NULL,
  after_json        JSON NULL,
  ip                VARCHAR(64) NULL,
  user_agent        VARCHAR(255) NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
--  New: Staging tables for Excel/Maps (de-duplicated by facility_source_map)
-- =========================

CREATE TABLE IF NOT EXISTS roro_travel_spot (
  tsm_id          VARCHAR(32) NOT NULL,
  branch_no       INT NULL,
  prefecture      VARCHAR(50) NULL,
  region          VARCHAR(50) NULL,
  spot_area       VARCHAR(120) NULL,
  genre           VARCHAR(120) NULL,
  name            VARCHAR(200) NOT NULL,
  phone           VARCHAR(50) NULL,
  address         VARCHAR(255) NULL,
  opening_time    VARCHAR(40) NULL,
  closing_time    VARCHAR(40) NULL,
  url             VARCHAR(255) NULL,
  lat             DECIMAL(10,8) NULL,
  lng             DECIMAL(11,8) NULL,
  google_rating   DECIMAL(3,2) NULL,
  google_reviews  INT NULL,
  english_support TINYINT(1) NOT NULL DEFAULT 0,
  top5_review     TEXT NULL,
  category_code   VARCHAR(10) NULL,
  spot_pt         POINT SRID 4326
    /*!80003 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(Point(lng,lat),4326))) STORED */,
  imported_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tsm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_gmapm (
  gmapm_id        VARCHAR(64) NOT NULL,
  region          VARCHAR(50) NULL,
  sub_region      VARCHAR(50) NULL,
  ward            VARCHAR(50) NULL,
  genre           VARCHAR(120) NULL,
  shop_name       VARCHAR(200) NULL,
  operating_hours VARCHAR(120) NULL,
  regular_holiday VARCHAR(120) NULL,
  address         VARCHAR(255) NULL,
  homepage        VARCHAR(255) NULL,
  google_rating   DECIMAL(3,2) NULL,
  google_reviews  INT NULL,
  dogs_category   VARCHAR(20) NULL,
  cats_ok         TINYINT(1) NOT NULL DEFAULT 0,
  pet_summary     TEXT NULL,
  imported_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (gmapm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_facility_source_map (
  source       ENUM('travel_spot','gmapm') NOT NULL,
  source_key   VARCHAR(64) NOT NULL,
  facility_id  BIGINT UNSIGNED NOT NULL,
  linked_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (source, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
--  Post-Creation: Indexes
-- =========================

-- Spatial indexes (MySQL 8+)
ALTER TABLE roro_facility
  ADD SPATIAL INDEX spx_facility (facility_pt);

ALTER TABLE roro_map_favorite
  ADD SPATIAL INDEX spx_fav_place (place_pt);

ALTER TABLE roro_travel_spot
  ADD SPATIAL INDEX spx_travel_spot (spot_pt);

-- =========================
--  Foreign Keys
-- =========================

SET FOREIGN_KEY_CHECKS=1;

-- Link WP and Customer
ALTER TABLE roro_user_link_wp
  ADD CONSTRAINT fk_link_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- Default pet (optional)
ALTER TABLE roro_customer
  ADD CONSTRAINT fk_customer_default_pet
    FOREIGN KEY (default_pet_id) REFERENCES roro_pet(pet_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

-- Pet owner & breed
ALTER TABLE roro_pet
  ADD CONSTRAINT fk_pet_owner
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_pet_breed
    FOREIGN KEY (breed_id) REFERENCES roro_dog_breed(breed_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

-- AI
ALTER TABLE roro_ai_conversation
  ADD CONSTRAINT fk_conv_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE roro_ai_message
  ADD CONSTRAINT fk_msg_conv
    FOREIGN KEY (conv_id) REFERENCES roro_ai_conversation(conv_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- Favorites
ALTER TABLE roro_map_favorite
  ADD CONSTRAINT fk_fav_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- Logs / Consent / Audit
ALTER TABLE roro_link_click
  ADD CONSTRAINT fk_click_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE roro_recommendation_log
  ADD CONSTRAINT fk_rec_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE roro_consent_log
  ADD CONSTRAINT fk_consent_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE roro_audit_event
  ADD CONSTRAINT fk_audit_actor_wp
    FOREIGN KEY (actor_wp_user_id) REFERENCES wp_users(ID)
    ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE roro_audit_event
  ADD CONSTRAINT fk_audit_actor_customer
    FOREIGN KEY (actor_customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

-- Staging source map → facility
ALTER TABLE roro_facility_source_map
  ADD CONSTRAINT fk_facmap_facility
    FOREIGN KEY (facility_id) REFERENCES roro_facility(facility_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

SQL;
  }

  /** dbDelta-compatible schema (indices/FK added afterward). */
  public static function dbdelta_tables($prefix, $collate) {
    return array(
      "CREATE TABLE {$prefix}roro_customer (
        customer_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
        email             VARCHAR(191) NOT NULL,
        postal_code       CHAR(7) NULL,
        country_code      CHAR(2) NULL,
        prefecture        VARCHAR(50) NULL,
        city              VARCHAR(100) NULL,
        address_line1     VARCHAR(120) NULL,
        address_line2     VARCHAR(120) NULL,
        building          VARCHAR(120) NULL,
        user_type         ENUM('local','social','admin') NOT NULL DEFAULT 'local',
        default_pet_id    BIGINT UNSIGNED NULL,
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (customer_id),
        UNIQUE KEY uk_customer_email (email)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_user_link_wp (
        customer_id  INT UNSIGNED NOT NULL,
        wp_user_id   BIGINT UNSIGNED NOT NULL,
        linked_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id),
        UNIQUE KEY uk_wp_user (wp_user_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_auth_account (
        account_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id       INT UNSIGNED NOT NULL,
        provider          ENUM('local','google','line','x','facebook','apple','github','other') NOT NULL,
        provider_user_id  VARCHAR(191) NULL,
        email             VARCHAR(191) NULL,
        email_verified    TINYINT(1) NOT NULL DEFAULT 0,
        password_hash     VARCHAR(255) NULL,
        status            ENUM('active','locked','deleted') NOT NULL DEFAULT 'active',
        created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login_at     DATETIME NULL,
        PRIMARY KEY (account_id),
        UNIQUE KEY uk_provider_uid (provider, provider_user_id),
        UNIQUE KEY uk_provider_email (provider, email),
        KEY idx_auth_customer (customer_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_auth_session (
        session_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        account_id         BIGINT UNSIGNED NOT NULL,
        customer_id        INT UNSIGNED NOT NULL,
        refresh_token_hash CHAR(64) NOT NULL,
        issued_at          DATETIME NOT NULL,
        expires_at         DATETIME NOT NULL,
        revoked_at         DATETIME NULL,
        ip                 VARCHAR(64) NULL,
        user_agent_hash    CHAR(64) NULL,
        PRIMARY KEY (session_id),
        UNIQUE KEY uk_refresh (refresh_token_hash),
        KEY idx_sess_account (account_id),
        KEY idx_sess_customer_exp (customer_id, expires_at)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_auth_token (
        token_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        account_id   BIGINT UNSIGNED NOT NULL,
        kind         ENUM('verify_email','password_reset','oauth_state') NOT NULL,
        token_hash   CHAR(64) NOT NULL,
        payload_json JSON NULL,
        expires_at   DATETIME NOT NULL,
        used_at      DATETIME NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (token_id),
        UNIQUE KEY uk_kind_token (kind, token_hash),
        KEY idx_token_account_kind (account_id, kind)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_dog_breed (
        breed_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name        VARCHAR(120) NOT NULL,
        group_name  VARCHAR(120) NULL,
        PRIMARY KEY (breed_id),
        UNIQUE KEY uk_breed_name (name)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_pet (
        pet_id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id          INT UNSIGNED NOT NULL,
        species              ENUM('dog','cat','other') NOT NULL,
        breed_id             INT UNSIGNED NULL,
        breed_label          VARCHAR(255) NULL,
        sex                  ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
        birth_date           DATE NULL,
        weight_kg            DECIMAL(5,2) NULL,
        photo_attachment_id  BIGINT UNSIGNED NULL,
        created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (pet_id),
        KEY idx_pet_customer (customer_id),
        KEY idx_pet_breed (breed_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_facility (
        facility_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name         VARCHAR(191) NOT NULL,
        category     VARCHAR(64) NULL,
        lat          DECIMAL(10,8) NULL,
        lng          DECIMAL(11,8) NULL,
        PRIMARY KEY (facility_id),
        KEY idx_facility_name (name)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_map_favorite (
        favorite_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id     INT UNSIGNED NOT NULL,
        target_type     ENUM('facility','spot','custom','place') NOT NULL,
        target_id       BIGINT UNSIGNED NULL,
        google_place_id VARCHAR(128) NULL,
        label           VARCHAR(120) NULL,
        lat             DECIMAL(10,8) NULL,
        lng             DECIMAL(11,8) NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (favorite_id),
        UNIQUE KEY uk_customer_place (customer_id, google_place_id),
        KEY idx_fav_customer_time (customer_id, created_at)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_ai_conversation (
        conv_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT UNSIGNED NOT NULL,
        provider    ENUM('openai','azure_openai','dify','local') NOT NULL,
        model       VARCHAR(64) NULL,
        purpose     ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
        meta        JSON NULL,
        started_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (conv_id),
        KEY idx_conv_customer (customer_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_ai_message (
        msg_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        conv_id      BIGINT UNSIGNED NOT NULL,
        role         ENUM('system','user','assistant','tool') NOT NULL,
        content      MEDIUMTEXT NOT NULL,
        token_input  INT NULL,
        token_output INT NULL,
        cost_usd     DECIMAL(10,4) NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (msg_id),
        KEY idx_msg_conv (conv_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_link_click (
        click_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id    INT UNSIGNED NULL,
        context_type   ENUM('ad','advice','facility','event','other') NOT NULL,
        context_id     BIGINT UNSIGNED NULL,
        url            VARCHAR(512) NOT NULL,
        referrer       VARCHAR(255) NULL,
        ip_hash        CHAR(64) NULL,
        user_agent_hash CHAR(64) NULL,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (click_id),
        KEY idx_click_customer_time (customer_id, created_at),
        KEY idx_click_context (context_type, context_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_recommendation_log (
        rec_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id  INT UNSIGNED NOT NULL,
        item_type    ENUM('advice','facility','event','product','pet_item') NOT NULL,
        item_id      BIGINT UNSIGNED NOT NULL,
        channel      ENUM('app','web','email','line','push') NOT NULL DEFAULT 'app',
        reason       JSON NULL,
        delivered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        impression_at TIMESTAMP NULL DEFAULT NULL,
        click_at      TIMESTAMP NULL DEFAULT NULL,
        dismissed_at  TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (rec_id),
        KEY idx_rec_customer_time (customer_id, delivered_at),
        KEY idx_rec_item (item_type, item_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_consent_log (
        log_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT UNSIGNED NOT NULL,
        old_status  ENUM('unknown','agreed','revoked') NULL,
        new_status  ENUM('unknown','agreed','revoked') NOT NULL,
        changed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (log_id),
        KEY idx_consent_customer_time (customer_id, changed_at)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_audit_event (
        audit_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_type        ENUM('user','admin','system') NOT NULL,
        actor_wp_user_id  BIGINT UNSIGNED NULL,
        actor_customer_id INT UNSIGNED NULL,
        event_type        VARCHAR(50) NOT NULL,
        entity_table      VARCHAR(64) NOT NULL,
        entity_pk         VARCHAR(64) NOT NULL,
        before_json       JSON NULL,
        after_json        JSON NULL,
        ip                VARCHAR(64) NULL,
        user_agent        VARCHAR(255) NULL,
        created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (audit_id),
        KEY idx_audit_time (created_at)
      ) {$collate};",
      /** 新規：ステージング & 出所マップ（dbDelta では生成列/SPATIALは張らない）*/
      "CREATE TABLE {$prefix}roro_travel_spot (
        tsm_id          VARCHAR(32) NOT NULL,
        branch_no       INT NULL,
        prefecture      VARCHAR(50) NULL,
        region          VARCHAR(50) NULL,
        spot_area       VARCHAR(120) NULL,
        genre           VARCHAR(120) NULL,
        name            VARCHAR(200) NOT NULL,
        phone           VARCHAR(50) NULL,
        address         VARCHAR(255) NULL,
        opening_time    VARCHAR(40) NULL,
        closing_time    VARCHAR(40) NULL,
        url             VARCHAR(255) NULL,
        lat             DECIMAL(10,8) NULL,
        lng             DECIMAL(11,8) NULL,
        google_rating   DECIMAL(3,2) NULL,
        google_reviews  INT NULL,
        english_support TINYINT(1) NOT NULL DEFAULT 0,
        top5_review     TEXT NULL,
        category_code   VARCHAR(10) NULL,
        imported_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (tsm_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_gmapm (
        gmapm_id        VARCHAR(64) NOT NULL,
        region          VARCHAR(50) NULL,
        sub_region      VARCHAR(50) NULL,
        ward            VARCHAR(50) NULL,
        genre           VARCHAR(120) NULL,
        shop_name       VARCHAR(200) NULL,
        operating_hours VARCHAR(120) NULL,
        regular_holiday VARCHAR(120) NULL,
        address         VARCHAR(255) NULL,
        homepage        VARCHAR(255) NULL,
        google_rating   DECIMAL(3,2) NULL,
        google_reviews  INT NULL,
        dogs_category   VARCHAR(20) NULL,
        cats_ok         TINYINT(1) NOT NULL DEFAULT 0,
        pet_summary     TEXT NULL,
        imported_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (gmapm_id)
      ) {$collate};",
      "CREATE TABLE {$prefix}roro_facility_source_map (
        source       ENUM('travel_spot','gmapm') NOT NULL,
        source_key   VARCHAR(64) NOT NULL,
        facility_id  BIGINT UNSIGNED NOT NULL,
        linked_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (source, source_key)
      ) {$collate};"
    );
  }

  /** Add FK helper (idempotent). */
  private static function maybe_add_fk($table, $keyName, $sqlConstraint) {
    global $wpdb;
    $exists = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
         $table, $keyName
      )
    );
    if ($exists) return;
    $wpdb->query("ALTER TABLE {$table} ADD CONSTRAINT {$keyName} {$sqlConstraint}");
  }

  /** Install via dbDelta + post indexes/FK for features dbDelta can't express well. */
  public static function install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $prefix = $wpdb->prefix;
    $collate = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables = self::dbdelta_tables($prefix, $collate);

    // dbDelta
    foreach ($tables as $sql) {
      call_user_func('dbDelta', $sql);
    }

    // Post indexes & generated columns (only where safe)
    // NOTE: dbDelta can't create generated columns or SPATIAL index reliably.
    // Add facility_pt/place_pt/spot_pt and spatial index where applicable.
    $p = $prefix;
    $wpdb->query("ALTER TABLE {$p}roro_facility
      ADD COLUMN IF NOT EXISTS facility_pt POINT SRID 4326
      GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng,lat),4326))) STORED");
    $wpdb->query("ALTER TABLE {$p}roro_map_favorite
      ADD COLUMN IF NOT EXISTS place_pt POINT SRID 4326
      GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng,lat),4326))) STORED");
    $wpdb->query("ALTER TABLE {$p}roro_travel_spot
      ADD COLUMN IF NOT EXISTS spot_pt POINT SRID 4326
      GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng,lat),4326))) STORED");

    // SPATIAL indexes (ignore errors if already exist)
    $wpdb->query("ALTER TABLE {$p}roro_facility ADD SPATIAL INDEX spx_facility (facility_pt)");
    $wpdb->query("ALTER TABLE {$p}roro_map_favorite ADD SPATIAL INDEX spx_fav_place (place_pt)");
    $wpdb->query("ALTER TABLE {$p}roro_travel_spot ADD SPATIAL INDEX spx_travel_spot (spot_pt)");

    // FKs
    self::maybe_add_fk("{$p}roro_user_link_wp", "fk_link_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_customer", "fk_customer_default_pet",
      "FOREIGN KEY (default_pet_id) REFERENCES {$p}roro_pet(pet_id) ON DELETE SET NULL ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_pet", "fk_pet_owner",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");
    self::maybe_add_fk("{$p}roro_pet", "fk_pet_breed",
      "FOREIGN KEY (breed_id) REFERENCES {$p}roro_dog_breed(breed_id) ON DELETE SET NULL ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_ai_conversation", "fk_conv_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_ai_message", "fk_msg_conv",
      "FOREIGN KEY (conv_id) REFERENCES {$p}roro_ai_conversation(conv_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_map_favorite", "fk_fav_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_link_click", "fk_click_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE SET NULL ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_recommendation_log", "fk_rec_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_consent_log", "fk_consent_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_audit_event", "fk_audit_actor_wp",
      "FOREIGN KEY (actor_wp_user_id) REFERENCES {$p}users(ID) ON DELETE SET NULL ON UPDATE RESTRICT");
    self::maybe_add_fk("{$p}roro_audit_event", "fk_audit_actor_customer",
      "FOREIGN KEY (actor_customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE SET NULL ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_facility_source_map", "fk_facmap_facility",
      "FOREIGN KEY (facility_id) REFERENCES {$p}roro_facility(facility_id) ON DELETE CASCADE ON UPDATE RESTRICT");
  }

  /** Execute the embedded raw SQL using $wpdb (WP context required). */
  public static function run_raw_sql() {
    global $wpdb;
    $sql = self::raw_sql();
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
      if ($stmt === '') continue;
      $wpdb->query($stmt);
    }
  }
}}
/** ------------------------------------------------------------------------------------------------
 * Bootstrap: call from plugin activation or WP-CLI
 *   - Plugin: register_activation_hook( __FILE__, ['Roro_Schema_20250811','install'] );
 *   - WP-CLI: wp roro-schema --mode=dbdelta (or --mode=raw)
 * ------------------------------------------------------------------------------------------------ */
if (!function_exists('roro_schema_install')) {
  function roro_schema_install() {
    if (class_exists('Roro_Schema_20250811')) {
      \Roro_Schema_20250811::install();
    }
  }
}

// WP-CLI binding (guarded to avoid editor warnings)
if (defined('WP_CLI') && class_exists('WP_CLI')) {
  $wpcliClass = 'WP_CLI';
  $wpcliClass::add_command('roro-schema', function($args, $assoc_args) {
    $mode = isset($assoc_args['mode']) ? $assoc_args['mode'] : 'dbdelta';
    if ($mode === 'raw') {
      \Roro_Schema_20250811::run_raw_sql();
      $cls = 'WP_CLI'; $cls::success('RoRo schema installed via RAW SQL.');
    } else {
      \Roro_Schema_20250811::install();
      $cls = 'WP_CLI'; $cls::success('RoRo schema installed via dbDelta + FKs.');
    }
  });
}

