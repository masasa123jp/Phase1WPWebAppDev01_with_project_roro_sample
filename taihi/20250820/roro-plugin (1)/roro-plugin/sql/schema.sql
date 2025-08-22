/*
 * RORO Core Plugin - Database schema
 *
 * This file contains the CREATE TABLE statements required by the
 * RORO plugin.  It is executed on plugin activation via the
 * Roro_Plugin_Activator class.  Tables are created with IF NOT EXISTS
 * to avoid overwriting existing data.  See README for details.
 */

-- Customer master
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_customer (
  customer_id INT NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  postal_code CHAR(7) NULL,
  country_code VARCHAR(2) NULL,
  prefecture VARCHAR(64) NULL,
  city VARCHAR(128) NULL,
  address_line1 VARCHAR(255) NULL,
  address_line2 VARCHAR(255) NULL,
  building VARCHAR(255) NULL,
  user_type ENUM('local','social','admin') NOT NULL DEFAULT 'local',
  default_pet_id BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (customer_id),
  UNIQUE KEY email (email),
  KEY idx_roro_customer_default_pet (default_pet_id),
  KEY idx_roro_customer_location (prefecture, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WP user link (optional one-to-one)
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_user_link_wp (
  customer_id INT NOT NULL,
  wp_user_id BIGINT NOT NULL,
  linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (customer_id),
  UNIQUE KEY wp_user_id (wp_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auth account
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_auth_account (
  account_id BIGINT NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  provider ENUM('local','google','line','apple','facebook') NOT NULL DEFAULT 'local',
  provider_user_id VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NULL,
  status ENUM('active','locked','deleted') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY  (account_id),
  UNIQUE KEY uk_provider_user (provider, provider_user_id),
  KEY idx_auth_account_customer (customer_id),
  KEY idx_auth_account_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auth session
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_auth_session (
  session_id BIGINT NOT NULL AUTO_INCREMENT,
  account_id BIGINT NOT NULL,
  customer_id INT NOT NULL,
  refresh_token_hash CHAR(64) NOT NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  ip VARCHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  PRIMARY KEY  (session_id),
  KEY idx_auth_session_account (account_id),
  KEY idx_auth_session_customer (customer_id),
  KEY idx_auth_session_refresh (refresh_token_hash),
  KEY idx_auth_session_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auth token
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_auth_token (
  token_id BIGINT NOT NULL AUTO_INCREMENT,
  account_id BIGINT NOT NULL,
  kind ENUM('verify_email','password_reset','oauth_state') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  payload_json JSON NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (token_id),
  UNIQUE KEY token_hash (token_hash),
  KEY idx_auth_token_account (account_id),
  KEY idx_auth_token_kind (kind),
  KEY idx_auth_token_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Category master
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}category_master (
  category_code VARCHAR(32) NOT NULL,
  category_name VARCHAR(255) NULL,
  sort_order INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pet master (breed list) linked to category
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}pet_master (
  PETM_ID VARCHAR(32) NOT NULL,
  pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
  breed_name VARCHAR(255) NOT NULL,
  category_code VARCHAR(32) NOT NULL,
  population INT NULL,
  population_rate DECIMAL(6,3) NULL,
  old_category VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (PETM_ID),
  KEY idx_pet_master_category (category_code),
  KEY idx_pet_master_type_name (pet_type, breed_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pets owned by customers
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_pet (
  pet_id BIGINT NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  species ENUM('DOG','CAT','OTHER') NOT NULL,
  PETM_ID VARCHAR(32) NULL,
  breed_label VARCHAR(255) NULL,
  sex ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
  birth_date DATE NULL,
  weight_kg DECIMAL(5,2) NULL,
  photo_attachment_id BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (pet_id),
  KEY idx_roro_pet_customer (customer_id),
  KEY idx_roro_pet_breed (PETM_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Favourites (map/custom)
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_map_favorite (
  favorite_id BIGINT NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  target_type ENUM('gmapm','travel_spot','custom') NOT NULL,
  source_id VARCHAR(64) NULL,
  label VARCHAR(255) NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (favorite_id),
  KEY idx_rmf_customer (customer_id),
  KEY idx_rmf_target (target_type, source_id),
  KEY idx_rmf_custom_latlng (lat, lng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OPAM advice articles
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}opam (
  OPAM_ID VARCHAR(64) NOT NULL,
  pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
  category_code VARCHAR(32) NULL,
  title VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NULL,
  url VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (OPAM_ID),
  KEY idx_opam_type_category (pet_type, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Category data link (recommendation sets)
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}category_data_link (
  CATEGORY_ID VARCHAR(64) NOT NULL,
  pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
  OPAM_ID VARCHAR(64) NULL,
  category_code VARCHAR(32) NOT NULL,
  GMAPM_ID VARCHAR(64) NULL,
  as_of_date DATE NOT NULL,
  version_no INT NOT NULL DEFAULT 1,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (CATEGORY_ID, pet_type, version_no),
  KEY idx_cdl_category_current (category_code, pet_type, is_current),
  KEY idx_cdl_opam (OPAM_ID),
  KEY idx_cdl_gmapm (GMAPM_ID),
  KEY idx_cdl_asof (as_of_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recommendation log (history only)
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_recommendation_log (
  rec_id BIGINT NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  rec_date DATE NOT NULL DEFAULT (CURRENT_DATE),
  CATEGORY_ID VARCHAR(64) NOT NULL,
  pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
  category_code VARCHAR(32) NOT NULL,
  OPAM_ID VARCHAR(64) NULL,
  GMAPM_ID VARCHAR(64) NULL,
  rank INT NOT NULL DEFAULT 1,
  status ENUM('planned','delivered','seen','clicked','dismissed','converted') NOT NULL DEFAULT 'planned',
  reason JSON NULL,
  planned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at DATETIME NULL,
  impression_at DATETIME NULL,
  click_at DATETIME NULL,
  converted_at DATETIME NULL,
  dedup_key VARCHAR(255) NULL,
  note TEXT NULL,
  PRIMARY KEY  (rec_id),
  UNIQUE KEY uk_roro_reco_dedup (customer_id, CATEGORY_ID, rec_date, rank),
  KEY idx_rrl_customer_date (customer_id, rec_date),
  KEY idx_rrl_customer_status_date (customer_id, status, rec_date),
  KEY idx_rrl_customer_category_date (customer_id, CATEGORY_ID, rec_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events table
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}events (
  event_id VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  date VARCHAR(50) NULL,
  location VARCHAR(255) NULL,
  venue VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  prefecture VARCHAR(50) NULL,
  city VARCHAR(50) NULL,
  lat DOUBLE NULL,
  lon DOUBLE NULL,
  source VARCHAR(50) NULL,
  url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (event_id),
  KEY idx_events_loc (prefecture, city),
  KEY idx_events_date (date),
  KEY idx_events_latlon (lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI conversation
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_ai_conversation (
  conv_id BIGINT NOT NULL AUTO_INCREMENT,
  customer_id INT NOT NULL,
  provider ENUM('openai','dify','azure','other') NOT NULL DEFAULT 'openai',
  model VARCHAR(128) NOT NULL,
  purpose ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
  meta JSON NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (conv_id),
  KEY idx_ai_conv_customer (customer_id),
  KEY idx_ai_conv_start (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI message
CREATE TABLE IF NOT EXISTS {$wpdb_prefix}roro_ai_message (
  msg_id BIGINT NOT NULL AUTO_INCREMENT,
  conv_id BIGINT NOT NULL,
  role ENUM('system','user','assistant','tool') NOT NULL,
  content MEDIUMTEXT NOT NULL,
  token_input INT NULL,
  token_output INT NULL,
  cost_usd DECIMAL(10,4) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (msg_id),
  KEY idx_ai_msg_conv (conv_id),
  KEY idx_ai_msg_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;