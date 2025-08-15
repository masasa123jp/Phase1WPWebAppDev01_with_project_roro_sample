<?php
/**
 * schema_20250815.php (revised from schema_20250811.php fixed)
 * Single-file installer for RoRo schema (SQL + dbDelta), with editor-safe guards.
 *
 * Key changes vs 2025-08-11 base:
 *  - Drop roro_dog_breed, add roro_breed_master (BREEDM_ID, category_code FK)
 *  - roro_pet: species ENUM('DOG','CAT','OTHER'), breed_id -> BREEDM_ID (VARCHAR(32))
 *  - roro_auth_token: reference customer_id (not account_id)
 *  - Add roro_category_master / roro_one_point_advice_master / roro_category_data_link
 *  - Keep staging tables (travel_spot/gmapm/facility) and also add *_master tables for ER alignment
 *  - Post-DDL: FKs, generated columns, SPATIAL indexes applied idempotently with MySQL version guards
 *
 * Usage:
 *   - Plugin activation: register_activation_hook(__FILE__, 'roro_schema_install');
 *   - WP-CLI: wp roro-schema --mode=dbdelta  (or --mode=raw)
 */

if (!defined('ABSPATH')) { define('ABSPATH', dirname(__FILE__) . '/'); } // for safety when parsed by editors

if (!class_exists('Roro_Schema_20250815')) {
class Roro_Schema_20250815 {

  const VERSION = '2025.08.15';

  /* -----------------------------------------------------------
   * Helpers (MySQL capability checks / idempotent DDL utilities)
   * ----------------------------------------------------------- */

  private static function mysql_version() {
    global $wpdb;
    // db_version() returns e.g. "8.0.35"
    $v = method_exists($wpdb,'db_version') ? $wpdb->db_version() : $wpdb->get_var('SELECT VERSION()');
    return is_string($v) ? $v : '5.7.0';
  }

  private static function version_at_least($ver) {
    return version_compare(self::mysql_version(), $ver, '>=');
  }

  private static function table_exists($table) {
    global $wpdb;
    $sql = $wpdb->prepare("SHOW TABLES LIKE %s", $table);
    return (bool) $wpdb->get_var($sql);
  }

  private static function column_exists($table, $column) {
    global $wpdb;
    $row = $wpdb->get_var($wpdb->prepare(
      "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
      $table, $column
    ));
    return !empty($row);
  }

  private static function index_exists($table, $index) {
    global $wpdb;
    $res = $wpdb->get_results("SHOW INDEX FROM {$table}");
    if (!$res) return false;
    foreach ($res as $r) {
      if (!empty($r['Key_name']) && $r['Key_name'] === $index) return true;
    }
    return false;
  }

  private static function maybe_add_column($table, $column, $definition) {
    global $wpdb;
    if (!self::column_exists($table, $column)) {
      $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
  }

  private static function maybe_add_index($table, $indexName, $indexDDL) {
    global $wpdb;
    if (!self::index_exists($table, $indexName)) {
      $wpdb->query("ALTER TABLE {$table} ADD {$indexDDL}");
    }
  }

  /** Add FK helper (idempotent). */
  private static function maybe_add_fk($table, $keyName, $sqlConstraint) {
    global $wpdb;
    $exists = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = %s",
        $keyName
      )
    );
    if ($exists) return;
    $wpdb->query("ALTER TABLE {$table} ADD CONSTRAINT {$keyName} {$sqlConstraint}");
  }

  /* -----------------------------------------------------------
   * RAW SQL (no WP prefix) — primarily for WP-CLI `--mode=raw`
   * ----------------------------------------------------------- */
  public static function raw_sql() {
    return <<<SQL
/*!40101 SET NAMES utf8mb4 */;
/*!40101 SET SQL_MODE='' */;
/*!40014 SET FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET CHARACTER_SET_CLIENT=utf8mb4 */;
/*!40101 SET CHARACTER_SET_RESULTS=utf8mb4 */;
/*!40101 SET COLLATION_CONNECTION=utf8mb4_general_ci */;

-- ============= Core (Master & Accounts) =============
CREATE TABLE IF NOT EXISTS roro_category_master (
  category_code   VARCHAR(32)  NOT NULL,
  category_name   VARCHAR(255) NULL,
  sort_order      INT          NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_breed_master (
  BREEDM_ID       VARCHAR(32)  NOT NULL,
  pet_type        ENUM('DOG','CAT','OTHER') NOT NULL,
  breed_name      VARCHAR(255) NOT NULL,
  category_code   VARCHAR(32)  NOT NULL,
  population      INT          NULL,
  population_rate DECIMAL(6,3) NULL,
  old_category    VARCHAR(64)  NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (BREEDM_ID),
  KEY idx_rbm_category (category_code),
  KEY idx_rbm_type_name (pet_type, breed_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  default_pet_id    BIGINT UNSIGNED NULL,
  isActive          TINYINT(1) NOT NULL DEFAULT 1,
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
  customer_id  INT UNSIGNED NOT NULL,
  kind         ENUM('verify_email','password_reset','oauth_state') NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  payload_json JSON NULL,
  expires_at   DATETIME NOT NULL,
  used_at      DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_id),
  UNIQUE KEY uk_kind_token (kind, token_hash),
  KEY idx_token_customer_kind (customer_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============= Pets & Photos =============
CREATE TABLE IF NOT EXISTS roro_pet (
  pet_id               BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id          INT UNSIGNED NOT NULL,
  species              ENUM('DOG','CAT','OTHER') NOT NULL,
  BREEDM_ID            VARCHAR(32)  NULL,
  breed_label          VARCHAR(255) NULL,
  sex                  ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
  birth_date           DATE NULL,
  weight_kg            DECIMAL(5,2) NULL,
  photo_attachment_id  BIGINT UNSIGNED NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pet_id),
  KEY idx_pet_customer (customer_id),
  KEY idx_pet_breed (BREEDM_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_photo (
  photo_id        BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id     INT UNSIGNED NOT NULL,
  pet_id          BIGINT UNSIGNED NULL,
  target_type     ENUM('gmapm','travel_spot','none') NOT NULL DEFAULT 'none',
  source_id       VARCHAR(64)  NULL,
  storage_key     VARCHAR(512) NOT NULL,
  caption         TEXT NULL,
  isVisible       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (photo_id),
  KEY idx_photo_customer (customer_id),
  KEY idx_photo_pet (pet_id),
  KEY idx_photo_target (target_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============= Facility: Master (ER) & Staging (base) =============
CREATE TABLE IF NOT EXISTS roro_google_maps_master (
  gmapm_id          VARCHAR(64)  NOT NULL,
  name              VARCHAR(255) NOT NULL,
  prefecture        VARCHAR(64)  NULL,
  region            VARCHAR(64)  NULL,
  genre             VARCHAR(64)  NULL,
  postal_code       VARCHAR(16)  NULL,
  address           VARCHAR(255) NULL,
  phone             VARCHAR(64)  NULL,
  opening_time      VARCHAR(64)  NULL,
  closing_time      VARCHAR(64)  NULL,
  latitude          DECIMAL(10,7) NULL,
  longitude         DECIMAL(10,7) NULL,
  google_rating     DECIMAL(3,2)  NULL,
  google_review_count INT        NULL,
  pet_allowed       TINYINT(1)    NULL,
  description       TEXT          NULL,
  isVisible         TINYINT(1)    NOT NULL DEFAULT 1,
  source_updated_at DATETIME      NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (gmapm_id),
  KEY idx_gm_name (name),
  KEY idx_gm_addr (prefecture, postal_code),
  KEY idx_gm_latlng (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_travel_spot_master (
  tsm_id           VARCHAR(64)  NOT NULL,
  branch_no        INT          NOT NULL DEFAULT 0,
  prefecture       VARCHAR(64)  NULL,
  region           VARCHAR(64)  NULL,
  spot_area        VARCHAR(128) NULL,
  genre            VARCHAR(64)  NULL,
  name             VARCHAR(255) NOT NULL,
  phone            VARCHAR(64)  NULL,
  address          VARCHAR(255) NULL,
  opening_time     VARCHAR(64)  NULL,
  closing_time     VARCHAR(64)  NULL,
  url              VARCHAR(512) NULL,
  latitude         DECIMAL(10,7) NULL,
  longitude        DECIMAL(10,7) NULL,
  google_rating    DECIMAL(3,2)  NULL,
  google_review_count INT        NULL,
  english_support  TINYINT(1)    NULL,
  category_code    VARCHAR(32)   NULL,
  isVisible        TINYINT(1)    NOT NULL DEFAULT 1,
  source_updated_at DATETIME     NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tsm_id, branch_no),
  KEY idx_ts_basic (prefecture, region, genre),
  KEY idx_ts_latlng (latitude, longitude),
  KEY idx_ts_category (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- (Base staging kept as-is)
CREATE TABLE IF NOT EXISTS roro_facility (
  facility_id  BIGINT UNSIGNED AUTO_INCREMENT,
  name         VARCHAR(191) NOT NULL,
  category     VARCHAR(64) NULL,
  lat          DECIMAL(10,8) NULL,
  lng          DECIMAL(11,8) NULL,
  facility_pt  POINT SRID 4326 /*!80003 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(Point(lng,lat),4326))) STORED */,
  PRIMARY KEY (facility_id),
  KEY idx_facility_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  spot_pt         POINT SRID 4326 /*!80003 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(Point(lng,lat),4326))) STORED */,
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

CREATE TABLE IF NOT EXISTS roro_map_favorite (
  favorite_id     BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id     INT UNSIGNED NOT NULL,
  target_type     ENUM('gmapm','travel_spot','custom','place') NOT NULL,
  target_id       VARCHAR(64) NULL,
  google_place_id VARCHAR(128) NULL,
  label           VARCHAR(120) NULL,
  lat             DECIMAL(10,8) NULL,
  lng             DECIMAL(11,8) NULL,
  place_pt        POINT SRID 4326 /*!80003 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(Point(lng,lat),4326))) STORED */,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (favorite_id),
  UNIQUE KEY uk_customer_place (customer_id, google_place_id),
  KEY idx_fav_customer_time (customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============= Advice / Category Link =============
CREATE TABLE IF NOT EXISTS roro_one_point_advice_master (
  opam_id       VARCHAR(64)  NOT NULL,
  pet_type      ENUM('DOG','CAT','OTHER') NOT NULL,
  category_code VARCHAR(32)  NULL,
  title         VARCHAR(255) NOT NULL,
  body          MEDIUMTEXT   NULL,
  url           VARCHAR(512) NULL,
  isVisible     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (opam_id),
  KEY idx_opam_type_category (pet_type, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_category_data_link (
  category_id   VARCHAR(64) NOT NULL,
  pet_type      ENUM('DOG','CAT','OTHER') NOT NULL,
  opam_id       VARCHAR(64) NULL,
  category_code VARCHAR(32) NOT NULL,
  gmapm_id      VARCHAR(64) NULL,
  as_of_date    DATE        NOT NULL,
  version_no    INT         NOT NULL DEFAULT 1,
  is_current    TINYINT(1)  NOT NULL DEFAULT 0,
  isVisible     TINYINT(1)  NOT NULL DEFAULT 1,
  created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (category_id, pet_type, version_no),
  KEY idx_cdl_category_current (category_code, pet_type, is_current),
  KEY idx_cdl_opam (opam_id),
  KEY idx_cdl_gmapm (gmapm_id),
  KEY idx_cdl_asof (as_of_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============= Logs / Events / AI =============
CREATE TABLE IF NOT EXISTS roro_link_click (
  click_id        BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id     INT UNSIGNED NULL,
  context_type    ENUM('ad','advice','facility','event','other') NOT NULL,
  context_id      BIGINT UNSIGNED NULL,
  url             VARCHAR(512) NOT NULL,
  referrer        VARCHAR(255) NULL,
  ip_hash         CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (click_id),
  KEY idx_click_customer_time (customer_id, created_at),
  KEY idx_click_context (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_recommendation_log (
  rec_id        BIGINT UNSIGNED AUTO_INCREMENT,
  customer_id   INT UNSIGNED NOT NULL,
  item_type     ENUM('advice','facility','event','product','pet_item') NOT NULL,
  item_id       VARCHAR(64) NOT NULL,
  channel       ENUM('app','web','email','line','push') NOT NULL DEFAULT 'app',
  reason        JSON NULL,
  delivered_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  impression_at TIMESTAMP NULL DEFAULT NULL,
  click_at      TIMESTAMP NULL DEFAULT NULL,
  dismissed_at  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (rec_id),
  KEY idx_rec_customer_time (customer_id, delivered_at),
  KEY idx_rec_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_events (
  event_id     VARCHAR(50)  NOT NULL,
  name         VARCHAR(255) NOT NULL,
  date         VARCHAR(50)  NULL,
  location     VARCHAR(255) NULL,
  venue        VARCHAR(255) NULL,
  address      VARCHAR(255) NULL,
  prefecture   VARCHAR(50)  NULL,
  city         VARCHAR(50)  NULL,
  lat          DOUBLE       NULL,
  lon          DOUBLE       NULL,
  source       VARCHAR(50)  NULL,
  url          VARCHAR(255) NULL,
  isVisible    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  KEY idx_events_loc (prefecture, city),
  KEY idx_events_date_str (date),
  KEY idx_events_latlon (lat, lon)
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

/*!40014 SET FOREIGN_KEY_CHECKS=1 */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
SQL;
  }

  /* -----------------------------------------------------------
   * dbDelta-compatible schema (uses WP prefix)
   * ----------------------------------------------------------- */
  public static function dbdelta_tables($prefix, $collate) {
    return array(

      /* ---- Category & Breed Master ---- */
      "CREATE TABLE {$prefix}roro_category_master (
        category_code   VARCHAR(32)   NOT NULL,
        category_name   VARCHAR(255)  NULL,
        sort_order      INT           NULL,
        created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (category_code)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_breed_master (
        BREEDM_ID       VARCHAR(32)   NOT NULL,
        pet_type        ENUM('DOG','CAT','OTHER') NOT NULL,
        breed_name      VARCHAR(255)  NOT NULL,
        category_code   VARCHAR(32)   NOT NULL,
        population      INT           NULL,
        population_rate DECIMAL(6,3)  NULL,
        old_category    VARCHAR(64)   NULL,
        created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (BREEDM_ID),
        KEY idx_rbm_category (category_code),
        KEY idx_rbm_type_name (pet_type, breed_name)
      ) {$collate};",

      /* ---- Core Accounts ---- */
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
        isActive          TINYINT(1) NOT NULL DEFAULT 1,
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (customer_id),
        UNIQUE KEY uk_customer_email (email),
        KEY idx_customer_location (prefecture, city),
        KEY idx_customer_defaultpet (default_pet_id)
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
        customer_id  INT UNSIGNED NOT NULL,
        kind         ENUM('verify_email','password_reset','oauth_state') NOT NULL,
        token_hash   CHAR(64) NOT NULL,
        payload_json JSON NULL,
        expires_at   DATETIME NOT NULL,
        used_at      DATETIME NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (token_id),
        UNIQUE KEY uk_kind_token (kind, token_hash),
        KEY idx_token_customer_kind (customer_id, kind),
        KEY idx_token_expires (expires_at)
      ) {$collate};",

      /* ---- Pets & Photos ---- */
      "CREATE TABLE {$prefix}roro_pet (
        pet_id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id          INT UNSIGNED NOT NULL,
        species              ENUM('DOG','CAT','OTHER') NOT NULL,
        BREEDM_ID            VARCHAR(32)  NULL,
        breed_label          VARCHAR(255) NULL,
        sex                  ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
        birth_date           DATE NULL,
        weight_kg            DECIMAL(5,2) NULL,
        photo_attachment_id  BIGINT UNSIGNED NULL,
        created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (pet_id),
        KEY idx_pet_customer (customer_id),
        KEY idx_pet_breed (BREEDM_ID)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_photo (
        photo_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id     INT UNSIGNED NOT NULL,
        pet_id          BIGINT UNSIGNED NULL,
        target_type     ENUM('gmapm','travel_spot','none') NOT NULL DEFAULT 'none',
        source_id       VARCHAR(64)  NULL,
        storage_key     VARCHAR(512) NOT NULL,
        caption         TEXT NULL,
        isVisible       TINYINT(1) NOT NULL DEFAULT 1,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (photo_id),
        KEY idx_photo_customer (customer_id),
        KEY idx_photo_pet (pet_id),
        KEY idx_photo_target (target_type, source_id)
      ) {$collate};",

      /* ---- Facility Masters (ER) ---- */
      "CREATE TABLE {$prefix}roro_google_maps_master (
        gmapm_id          VARCHAR(64)  NOT NULL,
        name              VARCHAR(255) NOT NULL,
        prefecture        VARCHAR(64)  NULL,
        region            VARCHAR(64)  NULL,
        genre             VARCHAR(64)  NULL,
        postal_code       VARCHAR(16)  NULL,
        address           VARCHAR(255) NULL,
        phone             VARCHAR(64)  NULL,
        opening_time      VARCHAR(64)  NULL,
        closing_time      VARCHAR(64)  NULL,
        latitude          DECIMAL(10,7) NULL,
        longitude         DECIMAL(10,7) NULL,
        google_rating     DECIMAL(3,2)  NULL,
        google_review_count INT        NULL,
        pet_allowed       TINYINT(1)    NULL,
        description       TEXT          NULL,
        isVisible         TINYINT(1)    NOT NULL DEFAULT 1,
        source_updated_at DATETIME      NULL,
        created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (gmapm_id),
        KEY idx_gm_name (name),
        KEY idx_gm_addr (prefecture, postal_code),
        KEY idx_gm_latlng (latitude, longitude)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_travel_spot_master (
        tsm_id           VARCHAR(64)  NOT NULL,
        branch_no        INT          NOT NULL DEFAULT 0,
        prefecture       VARCHAR(64)  NULL,
        region           VARCHAR(64)  NULL,
        spot_area        VARCHAR(128) NULL,
        genre            VARCHAR(64)  NULL,
        name             VARCHAR(255) NOT NULL,
        phone            VARCHAR(64)  NULL,
        address          VARCHAR(255) NULL,
        opening_time     VARCHAR(64)  NULL,
        closing_time     VARCHAR(64)  NULL,
        url              VARCHAR(512) NULL,
        latitude         DECIMAL(10,7) NULL,
        longitude        DECIMAL(10,7) NULL,
        google_rating    DECIMAL(3,2)  NULL,
        google_review_count INT        NULL,
        english_support  TINYINT(1)    NULL,
        category_code    VARCHAR(32)   NULL,
        isVisible        TINYINT(1)    NOT NULL DEFAULT 1,
        source_updated_at DATETIME     NULL,
        created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (tsm_id, branch_no),
        KEY idx_ts_basic (prefecture, region, genre),
        KEY idx_ts_latlng (latitude, longitude),
        KEY idx_ts_category (category_code)
      ) {$collate};",

      /* ---- Staging (kept from base) ---- */
      "CREATE TABLE {$prefix}roro_facility (
        facility_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name         VARCHAR(191) NOT NULL,
        category     VARCHAR(64) NULL,
        lat          DECIMAL(10,8) NULL,
        lng          DECIMAL(11,8) NULL,
        PRIMARY KEY  (facility_id),
        KEY idx_facility_name (name)
      ) {$collate};",

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
      ) {$collate};",

      /* ---- Favorites ---- */
      "CREATE TABLE {$prefix}roro_map_favorite (
        favorite_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id     INT UNSIGNED NOT NULL,
        target_type     ENUM('gmapm','travel_spot','custom','place') NOT NULL,
        target_id       VARCHAR(64) NULL,
        google_place_id VARCHAR(128) NULL,
        label           VARCHAR(120) NULL,
        lat             DECIMAL(10,8) NULL,
        lng             DECIMAL(11,8) NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (favorite_id),
        UNIQUE KEY uk_customer_place (customer_id, google_place_id),
        KEY idx_fav_customer_time (customer_id, created_at)
      ) {$collate};",

      /* ---- Advice / Category Link ---- */
      "CREATE TABLE {$prefix}roro_one_point_advice_master (
        opam_id       VARCHAR(64)  NOT NULL,
        pet_type      ENUM('DOG','CAT','OTHER') NOT NULL,
        category_code VARCHAR(32)  NULL,
        title         VARCHAR(255) NOT NULL,
        body          MEDIUMTEXT   NULL,
        url           VARCHAR(512) NULL,
        isVisible     TINYINT(1)   NOT NULL DEFAULT 1,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (opam_id),
        KEY idx_opam_type_category (pet_type, category_code)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_category_data_link (
        category_id   VARCHAR(64) NOT NULL,
        pet_type      ENUM('DOG','CAT','OTHER') NOT NULL,
        opam_id       VARCHAR(64) NULL,
        category_code VARCHAR(32) NOT NULL,
        gmapm_id      VARCHAR(64) NULL,
        as_of_date    DATE        NOT NULL,
        version_no    INT         NOT NULL DEFAULT 1,
        is_current    TINYINT(1)  NOT NULL DEFAULT 0,
        isVisible     TINYINT(1)  NOT NULL DEFAULT 1,
        created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (category_id, pet_type, version_no),
        KEY idx_cdl_category_current (category_code, pet_type, is_current),
        KEY idx_cdl_opam (opam_id),
        KEY idx_cdl_gmapm (gmapm_id),
        KEY idx_cdl_asof (as_of_date)
      ) {$collate};",

      /* ---- Logs / Events / AI ---- */
      "CREATE TABLE {$prefix}roro_link_click (
        click_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id     INT UNSIGNED NULL,
        context_type    ENUM('ad','advice','facility','event','other') NOT NULL,
        context_id      BIGINT UNSIGNED NULL,
        url             VARCHAR(512) NOT NULL,
        referrer        VARCHAR(255) NULL,
        ip_hash         CHAR(64) NULL,
        user_agent_hash CHAR(64) NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (click_id),
        KEY idx_click_customer_time (customer_id, created_at),
        KEY idx_click_context (context_type, context_id)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_recommendation_log (
        rec_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id   INT UNSIGNED NOT NULL,
        item_type     ENUM('advice','facility','event','product','pet_item') NOT NULL,
        item_id       VARCHAR(64) NOT NULL,
        channel       ENUM('app','web','email','line','push') NOT NULL DEFAULT 'app',
        reason        JSON NULL,
        delivered_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        impression_at TIMESTAMP NULL DEFAULT NULL,
        click_at      TIMESTAMP NULL DEFAULT NULL,
        dismissed_at  TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY  (rec_id),
        KEY idx_rec_customer_time (customer_id, delivered_at),
        KEY idx_rec_item (item_type, item_id)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_events (
        event_id     VARCHAR(50)  NOT NULL,
        name         VARCHAR(255) NOT NULL,
        date         VARCHAR(50)  NULL,
        location     VARCHAR(255) NULL,
        venue        VARCHAR(255) NULL,
        address      VARCHAR(255) NULL,
        prefecture   VARCHAR(50)  NULL,
        city         VARCHAR(50)  NULL,
        lat          DOUBLE       NULL,
        lon          DOUBLE       NULL,
        source       VARCHAR(50)  NULL,
        url          VARCHAR(255) NULL,
        isVisible    TINYINT(1)   NOT NULL DEFAULT 1,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (event_id),
        KEY idx_events_loc (prefecture, city),
        KEY idx_events_date_str (date),
        KEY idx_events_latlon (lat, lon)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_consent_log (
        log_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT UNSIGNED NOT NULL,
        old_status  ENUM('unknown','agreed','revoked') NULL,
        new_status  ENUM('unknown','agreed','revoked') NOT NULL,
        changed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (log_id),
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
        PRIMARY KEY  (audit_id),
        KEY idx_audit_time (created_at)
      ) {$collate};",

      "CREATE TABLE {$prefix}roro_ai_conversation (
        conv_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT UNSIGNED NOT NULL,
        provider    ENUM('openai','azure_openai','dify','local') NOT NULL,
        model       VARCHAR(64) NULL,
        purpose     ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
        meta        JSON NULL,
        started_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (conv_id),
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
        PRIMARY KEY  (msg_id),
        KEY idx_msg_conv (conv_id)
      ) {$collate};"
    );
  }

  /* -----------------------------------------------------------
   * Install via dbDelta, then apply FKs / Spatial / Generated cols
   * ----------------------------------------------------------- */
  public static function install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $prefix  = $wpdb->prefix;
    $collate = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    foreach (self::dbdelta_tables($prefix, $collate) as $sql) {
      call_user_func('dbDelta', $sql);
    }

    // Post: Generated columns & SPATIAL (MySQL 8+), idempotent and guarded
    $is80 = self::version_at_least('8.0.0');
    $p = $prefix;

    if ($is80) {
      // Generated POINT columns
      self::maybe_add_column("{$p}roro_facility", 'facility_pt',
        "POINT SRID 4326 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng,lat),4326))) STORED");
      self::maybe_add_column("{$p}roro_travel_spot", 'spot_pt',
        "POINT SRID 4326 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng,lat),4326))) STORED");
      self::maybe_add_column("{$p}roro_map_favorite", 'place_pt',
        "POINT SRID 4326 GENERATED ALWAYS AS (IF(lat IS NULL OR lng IS NULL, NULL, ST_SRID(POINT(lng,lat),4326))) STORED");

      // SPATIAL indexes
      self::maybe_add_index("{$p}roro_facility", 'spx_facility', 'SPATIAL INDEX spx_facility (facility_pt)');
      self::maybe_add_index("{$p}roro_travel_spot", 'spx_travel_spot', 'SPATIAL INDEX spx_travel_spot (spot_pt)');
      self::maybe_add_index("{$p}roro_map_favorite", 'spx_fav_place', 'SPATIAL INDEX spx_fav_place (place_pt)');
    }

    // Foreign Keys (idempotent)
    self::maybe_add_fk("{$p}roro_user_link_wp", "fk_link_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_customer", "fk_customer_default_pet",
      "FOREIGN KEY (default_pet_id) REFERENCES {$p}roro_pet(pet_id) ON DELETE SET NULL ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_auth_account", "fk_auth_account_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_auth_session", "fk_auth_session_account",
      "FOREIGN KEY (account_id) REFERENCES {$p}roro_auth_account(account_id) ON DELETE CASCADE ON UPDATE RESTRICT");
    self::maybe_add_fk("{$p}roro_auth_session", "fk_auth_session_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_auth_token", "fk_auth_token_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_breed_master", "fk_rbm_category",
      "FOREIGN KEY (category_code) REFERENCES {$p}roro_category_master(category_code) ON DELETE RESTRICT ON UPDATE CASCADE");

    self::maybe_add_fk("{$p}roro_pet", "fk_pet_owner",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");
    self::maybe_add_fk("{$p}roro_pet", "fk_pet_breedm",
      "FOREIGN KEY (BREEDM_ID) REFERENCES {$p}roro_breed_master(BREEDM_ID) ON DELETE SET NULL ON UPDATE CASCADE");

    self::maybe_add_fk("{$p}roro_photo", "fk_photo_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");
    self::maybe_add_fk("{$p}roro_photo", "fk_photo_pet",
      "FOREIGN KEY (pet_id) REFERENCES {$p}roro_pet(pet_id) ON DELETE SET NULL ON UPDATE CASCADE");

    self::maybe_add_fk("{$p}roro_one_point_advice_master", "fk_opam_category",
      "FOREIGN KEY (category_code) REFERENCES {$p}roro_category_master(category_code) ON DELETE SET NULL ON UPDATE CASCADE");

    self::maybe_add_fk("{$p}roro_category_data_link", "fk_cdl_category",
      "FOREIGN KEY (category_code) REFERENCES {$p}roro_category_master(category_code) ON DELETE RESTRICT ON UPDATE CASCADE");
    self::maybe_add_fk("{$p}roro_category_data_link", "fk_cdl_opam",
      "FOREIGN KEY (opam_id) REFERENCES {$p}roro_one_point_advice_master(opam_id) ON DELETE SET NULL ON UPDATE CASCADE");

    self::maybe_add_fk("{$p}roro_map_favorite", "fk_fav_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_ai_conversation", "fk_conv_customer",
      "FOREIGN KEY (customer_id) REFERENCES {$p}roro_customer(customer_id) ON DELETE CASCADE ON UPDATE RESTRICT");

    self::maybe_add_fk("{$p}roro_ai_message", "fk_msg_conv",
      "FOREIGN KEY (conv_id) REFERENCES {$p}roro_ai_conversation(conv_id) ON DELETE CASCADE ON UPDATE RESTRICT");

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

  /* -----------------------------------------------------------
   * Execute RAW SQL (no prefix) — for WP-CLI --mode=raw
   * ----------------------------------------------------------- */
  public static function run_raw_sql() {
    global $wpdb;
    $sql = self::raw_sql();
    // Split by semicolon carefully (no procedures here), then run.
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
      if ($stmt === '' || strpos($stmt, '/*') === 0) continue;
      $wpdb->query($stmt);
    }
  }
}}
/* =======================================================================
 * Bootstrap
 * ======================================================================= */
if (!function_exists('roro_schema_install')) {
  function roro_schema_install() {
    if (class_exists('Roro_Schema_20250815')) {
      \Roro_Schema_20250815::install();
    }
  }
}
if (defined('WP_CLI') && class_exists('WP_CLI')) {
  $wpcliClass = 'WP_CLI';
  $wpcliClass::add_command('roro-schema', function($args, $assoc_args) {
    $mode = isset($assoc_args['mode']) ? $assoc_args['mode'] : 'dbdelta';
    if ($mode === 'raw') {
      \Roro_Schema_20250815::run_raw_sql();
      $cls = 'WP_CLI'; $cls::success('RoRo schema installed via RAW SQL.');
    } else {
      \Roro_Schema_20250815::install();
      $cls = 'WP_CLI'; $cls::success('RoRo schema installed via dbDelta + post-DDL (FK/Spatial).');
    }
  });
}
