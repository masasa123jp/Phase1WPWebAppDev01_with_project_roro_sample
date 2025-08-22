-- roro_schema_20250811.sql
-- Generated from ERD on 2025-08-11 (Asia/Tokyo)
-- Target: MySQL 8.0+ / MariaDB 10.5+ (WordPress on XServer)
-- Notes:
--  - All tables use InnoDB and utf8mb4.
--  - POINT columns use SRID 4326 with generated expressions.
--  - Foreign keys are added after base table creation.
--  - Run as a privileged DB user (same as WordPress DB user).
--  - If your WordPress $table_prefix is not 'wp_', replace 'wp_users' below with '<prefix>users'.

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

-- =========================
--  Base Tables (no FKs yet)
-- =========================

CREATE TABLE IF NOT EXISTS roro_customer (
  customer_id       INT UNSIGNED AUTO_INCREMENT,
  email             VARCHAR(191) NOT NULL,
  postal_code       CHAR(7) NULL,
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
  ip                 VARCHAR(45) NULL,
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
  species              ENUM('dog','cat','other') NOT NULL DEFAULT 'dog',
  breed_id             INT UNSIGNED NULL,
  breed_label          VARCHAR(64) NULL,
  sex                  ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
  birth_date           DATE NULL,
  weight_kg            DECIMAL(4,1) NULL,
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
  customer_id INT UNSIGNED NULL,
  provider    ENUM('openai','azure_openai','dify','local') NOT NULL,
  model       VARCHAR(64) NULL,
  purpose     ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
  meta        JSON NULL,
  started_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conv_id),
  KEY idx_conv_customer (customer_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roro_ai_message (
  msg_id      BIGINT UNSIGNED AUTO_INCREMENT,
  conv_id     BIGINT UNSIGNED NOT NULL,
  role        ENUM('system','user','assistant','tool') NOT NULL,
  content     MEDIUMTEXT NOT NULL,
  token_input  INT UNSIGNED NOT NULL DEFAULT 0,
  token_output INT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd     DECIMAL(12,6) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (msg_id),
  KEY idx_msg_conv_time (conv_id, created_at)
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
  ip                VARCHAR(45) NULL,
  user_agent        VARCHAR(255) NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_entity (entity_table, entity_pk),
  KEY idx_audit_actor_time (actor_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
--  Post-Creation: Indexes
-- =========================

-- Spatial indexes (MySQL 8+)
ALTER TABLE roro_facility
  ADD SPATIAL INDEX spx_facility (facility_pt);

ALTER TABLE roro_map_favorite
  ADD SPATIAL INDEX spx_fav_place (place_pt);

-- =========================
--  Foreign Keys
-- =========================

SET FOREIGN_KEY_CHECKS=1;

-- Link WP and Customer
ALTER TABLE roro_user_link_wp
  ADD CONSTRAINT fk_link_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- NOTE: Adjust 'wp_users' to '<prefix>users' if your table prefix differs.
ALTER TABLE roro_user_link_wp
  ADD CONSTRAINT fk_link_wp_user
    FOREIGN KEY (wp_user_id) REFERENCES wp_users(ID)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- Auth relations
ALTER TABLE roro_auth_account
  ADD CONSTRAINT fk_auth_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE roro_auth_session
  ADD CONSTRAINT fk_sess_account
    FOREIGN KEY (account_id) REFERENCES roro_auth_account(account_id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sess_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE roro_auth_token
  ADD CONSTRAINT fk_token_account
    FOREIGN KEY (account_id) REFERENCES roro_auth_account(account_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- Pet relations
ALTER TABLE roro_pet
  ADD CONSTRAINT fk_pet_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_pet_breed
    FOREIGN KEY (breed_id) REFERENCES roro_dog_breed(breed_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

-- Customer.default_pet_id (after roro_pet exists)
ALTER TABLE roro_customer
  ADD CONSTRAINT fk_customer_default_pet
    FOREIGN KEY (default_pet_id) REFERENCES roro_pet(pet_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

-- Map favorites
ALTER TABLE roro_map_favorite
  ADD CONSTRAINT fk_fav_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- AI logs
ALTER TABLE roro_ai_conversation
  ADD CONSTRAINT fk_conv_customer
    FOREIGN KEY (customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE roro_ai_message
  ADD CONSTRAINT fk_msg_conv
    FOREIGN KEY (conv_id) REFERENCES roro_ai_conversation(conv_id)
    ON DELETE CASCADE ON UPDATE RESTRICT;

-- Clicks / Recommendations / Consent / Audit
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
    ON DELETE SET NULL ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_audit_actor_customer
    FOREIGN KEY (actor_customer_id) REFERENCES roro_customer(customer_id)
    ON DELETE SET NULL ON UPDATE RESTRICT;

-- Done
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
