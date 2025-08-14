/* ============================================================
   スキーマ初期化（必要に応じて）
   ============================================================ */
-- CREATE DATABASE IF NOT EXISTS roro_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE roro_app;

SET NAMES utf8mb4;

/* ============================================================
   1. マスタ（カテゴリ／犬種）
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_CATEGORY_MASTER (
  category_code   VARCHAR(32)   NOT NULL COMMENT 'カテゴリコード（例:A/B/C...）',
  category_name   VARCHAR(255)  NULL COMMENT '表示名',
  sort_order      INT           NULL COMMENT '並び順',
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_CATEGORY_MASTER PRIMARY KEY (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='カテゴリ小マスタ';

CREATE TABLE IF NOT EXISTS RORO_PET_MASTER (
  PETM_ID         VARCHAR(32)   NOT NULL COMMENT '犬種/ペット種ID',
  pet_type        ENUM('DOG','CAT','OTHER') NOT NULL COMMENT 'ペット区分',
  breed_name      VARCHAR(255)  NOT NULL COMMENT '種名/犬種名',
  category_code   VARCHAR(32)   NOT NULL COMMENT '→ RORO_CATEGORY_MASTER.category_code',
  population      INT           NULL,
  population_rate DECIMAL(6,3)  NULL,
  old_category    VARCHAR(64)   NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_PET_MASTER PRIMARY KEY (PETM_ID),
  INDEX IDX_RORO_PET_MASTER_CATEGORY (category_code),
  INDEX IDX_RORO_PET_MASTER_TYPE_NAME (pet_type, breed_name),
  CONSTRAINT FK_RORO_PET_MASTER_CATEGORY
    FOREIGN KEY (category_code) REFERENCES RORO_CATEGORY_MASTER(category_code)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ペット種マスタ（カテゴリ紐付け）';

/* ============================================================
   2. 顧客／WPリンク／認証
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_CUSTOMER (
  customer_id     INT           NOT NULL AUTO_INCREMENT COMMENT '顧客ID',
  email           VARCHAR(255)  NOT NULL COMMENT '一意メール',
  postal_code     CHAR(7)       NULL,
  country_code    VARCHAR(2)    NULL,
  prefecture      VARCHAR(64)   NULL,
  city            VARCHAR(128)  NULL,
  address_line1   VARCHAR(255)  NULL,
  address_line2   VARCHAR(255)  NULL,
  building        VARCHAR(255)  NULL,
  user_type       ENUM('local','social','admin') NOT NULL DEFAULT 'local',
  default_pet_id  BIGINT        NULL COMMENT '任意: 代表ペット',
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_CUSTOMER PRIMARY KEY (customer_id),
  CONSTRAINT UK_RORO_CUSTOMER_EMAIL UNIQUE (email),
  INDEX IDX_RORO_CUSTOMER_LOCATION (prefecture, city),
  INDEX IDX_RORO_CUSTOMER_DEFAULTPET (default_pet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='顧客';

-- WP_USERS との遅延リンク（WP側にはFKを貼らない）
CREATE TABLE IF NOT EXISTS RORO_USER_LINK_WP (
  customer_id     INT          NOT NULL,
  wp_user_id      BIGINT       NOT NULL,
  linked_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_USER_LINK_WP PRIMARY KEY (customer_id),
  CONSTRAINT UK_RORO_USER_LINK_WP_WPUSER UNIQUE (wp_user_id),
  CONSTRAINT FK_RORO_USER_LINK_WP_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='顧客とWPユーザーの遅延リンク';

CREATE TABLE IF NOT EXISTS RORO_AUTH_ACCOUNT (
  account_id      BIGINT       NOT NULL AUTO_INCREMENT,
  customer_id     INT          NOT NULL,
  provider        ENUM('local','google','line','apple','facebook') NOT NULL DEFAULT 'local',
  provider_user_id VARCHAR(255) NULL COMMENT '外部プロバイダのユーザーID',
  email           VARCHAR(255) NULL,
  email_verified  TINYINT(1)   NOT NULL DEFAULT 0,
  password_hash   VARCHAR(255) NULL COMMENT 'local時のみ',
  status          ENUM('active','locked','deleted') NOT NULL DEFAULT 'active',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at   DATETIME     NULL,
  CONSTRAINT PK_RORO_AUTH_ACCOUNT PRIMARY KEY (account_id),
  INDEX IDX_AUTH_ACCOUNT_CUSTOMER (customer_id),
  CONSTRAINT UK_AUTH_PROVIDER_ID UNIQUE (provider, provider_user_id),
  INDEX IDX_AUTH_EMAIL (email),
  CONSTRAINT FK_AUTH_ACCOUNT_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='認証アカウント';

CREATE TABLE IF NOT EXISTS RORO_AUTH_SESSION (
  session_id        BIGINT      NOT NULL AUTO_INCREMENT,
  account_id        BIGINT      NOT NULL,
  customer_id       INT         NOT NULL,
  refresh_token_hash CHAR(64)   NOT NULL,
  issued_at         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at        DATETIME    NOT NULL,
  revoked_at        DATETIME    NULL,
  ip                VARCHAR(64) NULL,
  user_agent_hash   CHAR(64)    NULL,
  CONSTRAINT PK_RORO_AUTH_SESSION PRIMARY KEY (session_id),
  INDEX IDX_AUTH_SESSION_ACCOUNT (account_id),
  INDEX IDX_AUTH_SESSION_CUSTOMER (customer_id),
  INDEX IDX_AUTH_SESSION_REFRESH (refresh_token_hash),
  INDEX IDX_AUTH_SESSION_EXPIRES (expires_at),
  CONSTRAINT FK_AUTH_SESSION_ACCOUNT
    FOREIGN KEY (account_id) REFERENCES RORO_AUTH_ACCOUNT(account_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT FK_AUTH_SESSION_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='認証セッション';

CREATE TABLE IF NOT EXISTS RORO_AUTH_TOKEN (
  token_id       BIGINT       NOT NULL AUTO_INCREMENT,
  account_id     BIGINT       NOT NULL,
  kind           ENUM('verify_email','password_reset','oauth_state') NOT NULL,
  token_hash     CHAR(64)     NOT NULL,
  payload_json   JSON         NULL,
  expires_at     DATETIME     NOT NULL,
  used_at        DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_AUTH_TOKEN PRIMARY KEY (token_id),
  INDEX IDX_AUTH_TOKEN_ACCOUNT (account_id),
  INDEX IDX_AUTH_TOKEN_KIND (kind),
  INDEX IDX_AUTH_TOKEN_EXPIRES (expires_at),
  CONSTRAINT UK_AUTH_TOKEN_HASH UNIQUE (token_hash),
  CONSTRAINT FK_AUTH_TOKEN_ACCOUNT
    FOREIGN KEY (account_id) REFERENCES RORO_AUTH_ACCOUNT(account_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='認証トークン';

/* ============================================================
   3. 飼育ペット／写真
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_PET (
  pet_id          BIGINT       NOT NULL AUTO_INCREMENT,
  customer_id     INT          NOT NULL,
  species         ENUM('DOG','CAT','OTHER') NOT NULL,
  PETM_ID         VARCHAR(32)  NULL COMMENT '→ RORO_PET_MASTER.PETM_ID',
  breed_label     VARCHAR(255) NULL,
  sex             ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
  birth_date      DATE         NULL,
  weight_kg       DECIMAL(5,2) NULL,
  photo_attachment_id BIGINT   NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_PET PRIMARY KEY (pet_id),
  INDEX IDX_RORO_PET_OWNER (customer_id),
  INDEX IDX_RORO_PET_BREED (PETM_ID),
  CONSTRAINT FK_RORO_PET_OWNER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT FK_RORO_PET_BREED
    FOREIGN KEY (PETM_ID) REFERENCES RORO_PET_MASTER(PETM_ID)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='飼育ペット';

CREATE TABLE IF NOT EXISTS RORO_PHOTO (
  photo_id        BIGINT       NOT NULL AUTO_INCREMENT,
  customer_id     INT          NOT NULL,
  pet_id          BIGINT       NULL,
  target_type     ENUM('gmapm','travel_spot','none') NOT NULL DEFAULT 'none',
  source_id       VARCHAR(64)  NULL COMMENT 'GMAPM_ID / TSM_ID|branch など',
  storage_key     VARCHAR(512) NOT NULL COMMENT 'オブジェクトストレージキー/パス',
  caption         TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_PHOTO PRIMARY KEY (photo_id),
  INDEX IDX_RORO_PHOTO_CUSTOMER (customer_id),
  INDEX IDX_RORO_PHOTO_PET (pet_id),
  INDEX IDX_RORO_PHOTO_TARGET (target_type, source_id),
  CONSTRAINT FK_RORO_PHOTO_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT FK_RORO_PHOTO_PET
    FOREIGN KEY (pet_id) REFERENCES RORO_PET(pet_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='写真';

/* ============================================================
   4. 施設ソース（独立運用：FKは貼らない）
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_GMAPM (
  GMAPM_ID         VARCHAR(64)  NOT NULL,
  name             VARCHAR(255) NOT NULL,
  prefecture       VARCHAR(64)  NULL,
  region           VARCHAR(64)  NULL,
  genre            VARCHAR(64)  NULL,
  postal_code      VARCHAR(16)  NULL,
  address          VARCHAR(255) NULL,
  phone            VARCHAR(64)  NULL,
  opening_time     VARCHAR(64)  NULL,
  closing_time     VARCHAR(64)  NULL,
  latitude         DECIMAL(10,7) NULL,
  longitude        DECIMAL(10,7) NULL,
  google_rating    DECIMAL(3,2)  NULL,
  google_review_count INT        NULL,
  pet_allowed      TINYINT(1)    NULL,
  description      TEXT          NULL,
  source_updated_at DATETIME     NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_GMAPM PRIMARY KEY (GMAPM_ID),
  INDEX IDX_RORO_GMAPM_NAME (name),
  INDEX IDX_RORO_GMAPM_ADDR (prefecture, postal_code),
  INDEX IDX_RORO_GMAPM_LATLNG (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='施設（GMAPMソース）';

CREATE TABLE IF NOT EXISTS RORO_TRAVEL_SPOT (
  TSM_ID           VARCHAR(64)  NOT NULL,
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
  source_updated_at DATETIME     NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_TRAVEL_SPOT PRIMARY KEY (TSM_ID, branch_no),
  INDEX IDX_RORO_TRAVEL_SPOT_BASIC (prefecture, region, genre),
  INDEX IDX_RORO_TRAVEL_SPOT_LATLNG (latitude, longitude),
  INDEX IDX_RORO_TRAVEL_SPOT_CATEGORY (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='観光スポット（外部ソース）';

/* お気に入り（施設テーブルなし：ソースID直参照 or カスタム地点） */
CREATE TABLE IF NOT EXISTS RORO_MAP_FAVORITE (
  favorite_id     BIGINT       NOT NULL AUTO_INCREMENT,
  customer_id     INT          NOT NULL,
  target_type     ENUM('gmapm','travel_spot','custom') NOT NULL,
  source_id       VARCHAR(64)  NULL COMMENT '対象ソースID（gmapm/travel_spot時）',
  label           VARCHAR(255) NULL,
  lat             DECIMAL(10,7) NULL COMMENT 'custom時に使用',
  lng             DECIMAL(10,7) NULL COMMENT 'custom時に使用',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_MAP_FAVORITE PRIMARY KEY (favorite_id),
  INDEX IDX_RMF_CUSTOMER (customer_id),
  INDEX IDX_RMF_TARGET (target_type, source_id),
  INDEX IDX_RMF_CUSTOM_PT (lat, lng),
  CONSTRAINT FK_RMF_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='地図お気に入り';

/* ============================================================
   5. 記事/カテゴリ連携（+ 推薦セット管理）
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_OPAM (
  OPAM_ID         VARCHAR(64)  NOT NULL,
  pet_type        ENUM('DOG','CAT','OTHER') NOT NULL,
  category_code   VARCHAR(32)  NULL,
  title           VARCHAR(255) NOT NULL,
  body            MEDIUMTEXT   NULL,
  url             VARCHAR(512) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_OPAM PRIMARY KEY (OPAM_ID),
  INDEX IDX_RORO_OPAM_TYPE_CATEGORY (pet_type, category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='記事/アドバイス(OPAM)';

CREATE TABLE IF NOT EXISTS RORO_CATEGORY_DATA_LINK (
  CATEGORY_ID     VARCHAR(64)  NOT NULL COMMENT '例: CATEGORY_A_000001',
  pet_type        ENUM('DOG','CAT','OTHER') NOT NULL,
  OPAM_ID         VARCHAR(64)  NULL COMMENT '→ OPAM.OPAM_ID',
  category_code   VARCHAR(32)  NOT NULL COMMENT '→ RORO_CATEGORY_MASTER.category_code',
  GMAPM_ID        VARCHAR(64)  NULL COMMENT '→ GMAPM.GMAPM_ID（FK無し）',
  as_of_date      DATE         NOT NULL COMMENT 'このセット定義の有効日',
  version_no      INT          NOT NULL DEFAULT 1 COMMENT '版',
  is_current      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'カテゴリ×pet_type内の現行版',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_CATEGORY_DATA_LINK PRIMARY KEY (CATEGORY_ID, pet_type, version_no),
  INDEX IDX_CDL_RORO_CATEGORY_CURRENT (category_code, pet_type, is_current),
  INDEX IDX_CDL_RORO_OPAM (OPAM_ID),
  INDEX IDX_CDL_RORO_GMAPM (GMAPM_ID),
  INDEX IDX_CDL_RORO_ASOF (as_of_date),
  CONSTRAINT FK_CDL_CATEGORY
    FOREIGN KEY (category_code) REFERENCES RORO_CATEGORY_MASTER(category_code)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT FK_CDL_RORO_OPAM
    FOREIGN KEY (OPAM_ID) REFERENCES RORO_OPAM(OPAM_ID)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='カテゴリ⇔OPAM/お店（推薦セット、版管理）';

/* is_currentを1にしたら、同一(category_code,pet_type)の他行を自動で0にする（業務ルール担保） */
DELIMITER $$
CREATE TRIGGER TR_CDL_AFTER_INS
AFTER INSERT ON RORO_CATEGORY_DATA_LINK
FOR EACH ROW
BEGIN
  IF NEW.is_current = 1 THEN
    UPDATE RORO_CATEGORY_DATA_LINK
      SET is_current = 0
    WHERE category_code = NEW.category_code
      AND pet_type = NEW.pet_type
      AND NOT (CATEGORY_ID = NEW.CATEGORY_ID AND version_no = NEW.version_no);
  END IF;
END$$

CREATE TRIGGER TR_CDL_AFTER_UPD
AFTER UPDATE ON RORO_CATEGORY_DATA_LINK
FOR EACH ROW
BEGIN
  IF NEW.is_current = 1 AND (OLD.is_current <> NEW.is_current
                             OR OLD.category_code <> NEW.category_code
                             OR OLD.pet_type <> NEW.pet_type) THEN
    UPDATE RORO_CATEGORY_DATA_LINK
      SET is_current = 0
    WHERE category_code = NEW.category_code
      AND pet_type = NEW.pet_type
      AND NOT (CATEGORY_ID = NEW.CATEGORY_ID AND version_no = NEW.version_no);
  END IF;
END$$
DELIMITER ;

/* 現行版ビュー（運用でよく使う） */
CREATE OR REPLACE VIEW V_RORO_CATEGORY_DATA_CURRENT AS
SELECT
  CATEGORY_ID, pet_type, OPAM_ID, category_code, GMAPM_ID,
  as_of_date, version_no, is_current, created_at, updated_at
FROM RORO_CATEGORY_DATA_LINK
WHERE is_current = 1;

/* ============================================================
   6. レコメンド履歴（履歴のみで運用）
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_RECOMMENDATION_LOG (
  rec_id          BIGINT       NOT NULL AUTO_INCREMENT,
  customer_id     INT          NOT NULL COMMENT '→ RORO_CUSTOMER',
  rec_date        DATE         NOT NULL DEFAULT (CURRENT_DATE) COMMENT '推薦日(YYYY-MM-DD)',
  CATEGORY_ID     VARCHAR(64)  NOT NULL COMMENT '→ CATEGORY_DATA_LINK.CATEGORY_ID（論理参照）',
  pet_type        ENUM('DOG','CAT','OTHER') NOT NULL COMMENT '冗長保持（CDLと一致）',
  category_code   VARCHAR(32)  NOT NULL COMMENT '冗長保持（CDLと一致）',
  OPAM_ID         VARCHAR(64)  NULL COMMENT '冗長保持（必要に応じ）',
  GMAPM_ID        VARCHAR(64)  NULL COMMENT '冗長保持（必要に応じ）',
  rank            INT          NOT NULL DEFAULT 1 COMMENT '提示順位',
  status          ENUM('planned','delivered','seen','clicked','dismissed','converted') NOT NULL DEFAULT 'planned',
  reason          JSON         NULL,
  planned_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at    DATETIME     NULL,
  impression_at   DATETIME     NULL,
  click_at        DATETIME     NULL,
  converted_at    DATETIME     NULL,
  dedup_key       VARCHAR(255) NULL COMMENT '重複抑止キー（customer|CATEGORY_ID|rec_date|rank）',
  note            TEXT         NULL,
  CONSTRAINT PK_RORO_RECO_LOG PRIMARY KEY (rec_id),
  CONSTRAINT UK_RORO_RECO_DEDUP UNIQUE (customer_id, CATEGORY_ID, rec_date, rank),
  INDEX IDX_RRL_CUST_DATE (customer_id, rec_date),
  INDEX IDX_RRL_CUST_STATUS_DATE (customer_id, status, rec_date),
  INDEX IDX_RRL_CUST_CATEGORY_DATE (customer_id, CATEGORY_ID, rec_date),
  CONSTRAINT FK_RRL_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='レコメンド履歴（ログのみで運用）';

/* 挿入時に dedup_key がNULLなら自動設定（利便用） */
DELIMITER $$
CREATE TRIGGER TR_RRL_BEFORE_INS
BEFORE INSERT ON RORO_RECOMMENDATION_LOG
FOR EACH ROW
BEGIN
  IF NEW.dedup_key IS NULL OR NEW.dedup_key = '' THEN
    SET NEW.dedup_key = CONCAT(NEW.customer_id,'|',NEW.CATEGORY_ID,'|',DATE_FORMAT(NEW.rec_date,'%Y-%m-%d'),'|',NEW.rank);
  END IF;
END$$
DELIMITER ;

/* よく使うビュー：顧客×日付の最新提示（rank=1想定） */
CREATE OR REPLACE VIEW V_RECO_LAST_BY_CUSTOMER AS
SELECT rl.*
FROM RORO_RECOMMENDATION_LOG rl
JOIN (
  SELECT customer_id, MAX(rec_date) AS max_date
  FROM RORO_RECOMMENDATION_LOG
  GROUP BY customer_id
) x ON x.customer_id = rl.customer_id AND x.max_date = rl.rec_date
WHERE rl.rank = 1;

/* ============================================================
   7. イベント
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_EVENTS (
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
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_EVENTS PRIMARY KEY (event_id),
  INDEX IDX_RORO_EVENTS_LOC (prefecture, city),
  INDEX IDX_RORO_EVENTS_DATE_STR (date),
  INDEX IDX_RORO_EVENTS_LATLON (lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='イベント（取り込み仕様準拠）';

/* ============================================================
   8. AI 会話
   ============================================================ */
CREATE TABLE IF NOT EXISTS RORO_AI_CONVERSATION (
  conv_id        BIGINT       NOT NULL AUTO_INCREMENT,
  customer_id    INT          NOT NULL,
  provider       ENUM('openai','dify','azure','other') NOT NULL DEFAULT 'openai',
  model          VARCHAR(128) NOT NULL,
  purpose        ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
  meta           JSON         NULL,
  started_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_AI_CONV PRIMARY KEY (conv_id),
  INDEX IDX_RORO_AI_CONV_CUST (customer_id),
  INDEX IDX_RORO_AI_CONV_START (started_at),
  CONSTRAINT FK_RORO_AI_CONV_CUSTOMER
    FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI会話';

CREATE TABLE IF NOT EXISTS RORO_AI_MESSAGE (
  msg_id         BIGINT       NOT NULL AUTO_INCREMENT,
  conv_id        BIGINT       NOT NULL,
  role           ENUM('system','user','assistant','tool') NOT NULL,
  content        MEDIUMTEXT   NOT NULL,
  token_input    INT          NULL,
  token_output   INT          NULL,
  cost_usd       DECIMAL(10,4) NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT PK_RORO_AI_MSG PRIMARY KEY (msg_id),
  INDEX IDX_RORO_AI_MSG_CONV (conv_id),
  INDEX IDX_RORO_AI_MSG_CREATED (created_at),
  CONSTRAINT FK_RORO_AI_MSG_CONV
    FOREIGN KEY (conv_id) REFERENCES RORO_AI_CONVERSATION(conv_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AIメッセージ';
