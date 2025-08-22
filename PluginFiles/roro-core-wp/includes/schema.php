<?php
if (!defined('ABSPATH')) { exit; }

/**
 * プラグイン有効化時に DDL を反映。
 * 添付 DDL（2025-08-22 Final）に準拠：RORO_* 正式名、wp_roro_* は互換ビュー。 
 * 参考: DDL_20250822.sql / ER_20250818.md の定義。 
 */
function roro_install_schema() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // --- マスタ
    $sqls = array();

    // RORO_CATEGORY_MASTER / RORO_BREED_MASTER
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_CATEGORY_MASTER (
        category_code VARCHAR(32) NOT NULL,
        category_name VARCHAR(255) NULL,
        sort_order INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (category_code)
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_BREED_MASTER (
        BREEDM_ID VARCHAR(32) NOT NULL,
        pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
        breed_name VARCHAR(255) NOT NULL,
        category_code VARCHAR(32) NOT NULL,
        population INT NULL,
        population_rate DECIMAL(6,3) NULL,
        category_description VARCHAR(255) NULL,
        old_category VARCHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (BREEDM_ID),
        INDEX IDX_RBM_CATEGORY (category_code),
        INDEX IDX_RBM_TYPE_NAME (pet_type, breed_name),
        CONSTRAINT FK_RBM_CATEGORY FOREIGN KEY (category_code) REFERENCES RORO_CATEGORY_MASTER(category_code)
    ) {$charset};";

    // --- 顧客 / WPリンク / 認証
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_CUSTOMER (
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
        isActive TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id),
        UNIQUE KEY UK_RORO_CUSTOMER_EMAIL (email),
        INDEX IDX_RORO_CUSTOMER_LOCATION (prefecture, city),
        INDEX IDX_RORO_CUSTOMER_DEFAULTPET (default_pet_id)
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_USER_LINK_WP (
        customer_id INT NOT NULL,
        wp_user_id BIGINT NOT NULL,
        linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id),
        UNIQUE KEY UK_RORO_USER_LINK_WP_WPUSER (wp_user_id),
        CONSTRAINT FK_RORO_USER_LINK_WP_CUSTOMER FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_AUTH_ACCOUNT (
        account_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        provider ENUM('local','google','line','apple','facebook') NOT NULL DEFAULT 'local',
        provider_user_id VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        password_hash VARCHAR(255) NULL,
        status ENUM('active','locked','deleted') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL,
        PRIMARY KEY (account_id),
        UNIQUE KEY UK_AUTH_PROVIDER_ID (provider, provider_user_id),
        INDEX IDX_AUTH_ACCOUNT_CUSTOMER (customer_id),
        INDEX IDX_AUTH_EMAIL (email),
        CONSTRAINT FK_AUTH_ACCOUNT_CUSTOMER FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_AUTH_SESSION (
        session_id BIGINT NOT NULL AUTO_INCREMENT,
        account_id BIGINT NOT NULL,
        customer_id INT NOT NULL,
        refresh_token_hash CHAR(64) NOT NULL,
        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        ip VARCHAR(64) NULL,
        user_agent_hash CHAR(64) NULL,
        PRIMARY KEY (session_id),
        INDEX IDX_AUTH_SESSION_ACCOUNT (account_id),
        INDEX IDX_AUTH_SESSION_CUSTOMER (customer_id),
        INDEX IDX_AUTH_SESSION_REFRESH (refresh_token_hash),
        INDEX IDX_AUTH_SESSION_EXPIRES (expires_at),
        CONSTRAINT FK_AUTH_SESSION_ACCOUNT FOREIGN KEY (account_id) REFERENCES RORO_AUTH_ACCOUNT(account_id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT FK_AUTH_SESSION_CUSTOMER FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_AUTH_TOKEN (
        token_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        kind ENUM('verify_email','password_reset','oauth_state') NOT NULL,
        token_hash CHAR(64) NOT NULL,
        payload_json JSON NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (token_id),
        UNIQUE KEY UK_AUTH_TOKEN_HASH (token_hash),
        INDEX IDX_AUTH_TOKEN_CUSTOMER (customer_id),
        INDEX IDX_AUTH_TOKEN_KIND (kind),
        INDEX IDX_AUTH_TOKEN_EXPIRES (expires_at),
        CONSTRAINT FK_AUTH_TOKEN_CUSTOMER FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) {$charset};";

    // --- ペット / 写真
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_PET (
        pet_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        species ENUM('DOG','CAT','OTHER') NOT NULL,
        BREEDM_ID VARCHAR(32) NULL,
        breed_label VARCHAR(255) NULL,
        sex ENUM('unknown','male','female') NOT NULL DEFAULT 'unknown',
        birth_date DATE NULL,
        weight_kg DECIMAL(5,2) NULL,
        photo_attachment_id BIGINT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (pet_id),
        INDEX IDX_RORO_PET_OWNER (customer_id),
        INDEX IDX_RORO_PET_BREED (BREEDM_ID),
        CONSTRAINT FK_RORO_PET_OWNER FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_PHOTO (
        photo_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        pet_id BIGINT NULL,
        target_type ENUM('gmapm','travel_spot','none') NOT NULL DEFAULT 'none',
        source_id VARCHAR(64) NULL,
        storage_key VARCHAR(512) NOT NULL,
        caption TEXT NULL,
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (photo_id),
        INDEX IDX_RORO_PHOTO_CUSTOMER (customer_id),
        INDEX IDX_RORO_PHOTO_PET (pet_id),
        INDEX IDX_RORO_PHOTO_TARGET (target_type, source_id)
    ) {$charset};";

    // --- 施設/スポット
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_GOOGLE_MAPS_MASTER (
        GMAPM_ID VARCHAR(64) NOT NULL,
        name VARCHAR(255) NOT NULL,
        prefecture VARCHAR(64) NULL,
        region VARCHAR(64) NULL,
        genre VARCHAR(64) NULL,
        postal_code VARCHAR(16) NULL,
        address VARCHAR(255) NULL,
        phone VARCHAR(64) NULL,
        opening_time VARCHAR(64) NULL,
        closing_time VARCHAR(64) NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        source_url VARCHAR(512) NULL,
        review TEXT NULL,
        google_rating DECIMAL(3,2) NULL,
        google_review_count INT NULL,
        description TEXT NULL,
        category_code VARCHAR(32) NULL,
        pet_allowed TINYINT(1) NULL,
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        source_updated_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (GMAPM_ID),
        INDEX IDX_RORO_GMAPM_NAME (name),
        INDEX IDX_RORO_GMAPM_ADDR (prefecture, postal_code),
        INDEX IDX_RORO_GMAPM_LATLNG (latitude, longitude)
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_TRAVEL_SPOT_MASTER (
        TSM_ID VARCHAR(64) NOT NULL,
        branch_no INT NOT NULL DEFAULT 0,
        prefecture VARCHAR(64) NULL,
        region VARCHAR(64) NULL,
        spot_area VARCHAR(128) NULL,
        genre VARCHAR(64) NULL,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(64) NULL,
        address VARCHAR(255) NULL,
        opening_time VARCHAR(64) NULL,
        closing_time VARCHAR(64) NULL,
        url VARCHAR(512) NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        google_rating DECIMAL(3,2) NULL,
        google_review_count INT NULL,
        english_support TINYINT(1) NULL,
        review TEXT NULL,
        category_code VARCHAR(32) NULL,
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        source_updated_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (TSM_ID, branch_no),
        INDEX IDX_RORO_TRAVEL_SPOT_BASIC (prefecture, region, genre),
        INDEX IDX_RORO_TRAVEL_SPOT_LATLNG (latitude, longitude),
        INDEX IDX_RORO_TRAVEL_SPOT_CATEGORY (category_code)
    ) {$charset};";

    // --- お気に入り
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_MAP_FAVORITE (
        favorite_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        target_type ENUM('gmapm','travel_spot','custom') NOT NULL,
        source_id VARCHAR(64) NULL,
        label VARCHAR(255) NULL,
        lat DECIMAL(10,7) NULL,
        lng DECIMAL(10,7) NULL,
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (favorite_id),
        INDEX IDX_RMF_CUSTOMER (customer_id),
        INDEX IDX_RMF_TARGET (target_type, source_id),
        INDEX IDX_RMF_CUSTOM_PT (lat, lng),
        CONSTRAINT FK_RMF_CUSTOMER FOREIGN KEY (customer_id) REFERENCES RORO_CUSTOMER(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) {$charset};";

    // --- 記事/カテゴリ連携 ＋ 版管理（OPAM / CDL）
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_ONE_POINT_ADVICE_MASTER (
        OPAM_ID VARCHAR(64) NOT NULL,
        pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
        category_code VARCHAR(32) NULL,
        title VARCHAR(255) NOT NULL,
        body MEDIUMTEXT NULL,
        url VARCHAR(512) NULL,
        for_which_pets VARCHAR(255) NULL,
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (OPAM_ID),
        INDEX IDX_RORO_OPAM_TYPE_CATEGORY (pet_type, category_code),
        CONSTRAINT FK_RORO_OPAM_CATEGORY FOREIGN KEY (category_code) REFERENCES RORO_CATEGORY_MASTER(category_code) ON DELETE SET NULL ON UPDATE CASCADE
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_CATEGORY_DATA_LINK_MASTER (
        CDLM_ID VARCHAR(64) NOT NULL,
        pet_type ENUM('DOG','CAT','OTHER') NOT NULL,
        OPAM_ID VARCHAR(64) NULL,
        category_code VARCHAR(32) NOT NULL,
        GMAPM_ID VARCHAR(64) NULL,
        as_of_date DATE NOT NULL,
        version_no INT NOT NULL DEFAULT 1,
        is_current TINYINT(1) NOT NULL DEFAULT 0,
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (CDLM_ID, pet_type, version_no),
        INDEX IDX_CDL_CATEGORY_CURRENT (category_code, pet_type, is_current),
        INDEX IDX_CDL_OPAM (OPAM_ID),
        INDEX IDX_CDL_GMAPM (GMAPM_ID),
        INDEX IDX_CDL_ASOF (as_of_date),
        CONSTRAINT FK_CDL_CATEGORY FOREIGN KEY (category_code) REFERENCES RORO_CATEGORY_MASTER(category_code) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT FK_CDL_OPAM FOREIGN KEY (OPAM_ID) REFERENCES RORO_ONE_POINT_ADVICE_MASTER(OPAM_ID) ON DELETE SET NULL ON UPDATE CASCADE
    ) {$charset};";

    // --- レコメンド履歴
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_RECOMMENDATION_LOG (
        rec_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        rec_date DATE NOT NULL,
        CDLM_ID VARCHAR(64) NOT NULL,
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
        PRIMARY KEY (rec_id),
        UNIQUE KEY UK_RORO_RECO_DEDUP (customer_id, CDLM_ID, rec_date, rank),
        INDEX IDX_RRL_CUST_DATE (customer_id, rec_date)
    ) {$charset};";

    // --- イベント
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_EVENTS_MASTER (
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
        isVisible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (event_id),
        INDEX IDX_RORO_EVENTS_NAME (name),
        INDEX IDX_RORO_EVENTS_LOC (prefecture, city),
        INDEX IDX_RORO_EVENTS_DATE_STR (date),
        INDEX IDX_RORO_EVENTS_LATLON (lat, lon)
    ) {$charset};";

    // --- AI 会話
    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_AI_CONVERSATION (
        conv_id BIGINT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        provider ENUM('openai','dify','azure','other') NOT NULL DEFAULT 'openai',
        model VARCHAR(128) NOT NULL,
        purpose ENUM('advice','qa','support','other') NOT NULL DEFAULT 'advice',
        meta JSON NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (conv_id),
        INDEX IDX_RORO_AI_CONV_CUST (customer_id),
        INDEX IDX_RORO_AI_CONV_START (started_at)
    ) {$charset};";

    $sqls[] = "CREATE TABLE IF NOT EXISTS RORO_AI_MESSAGE (
        msg_id BIGINT NOT NULL AUTO_INCREMENT,
        conv_id BIGINT NOT NULL,
        role ENUM('system','user','assistant','tool') NOT NULL,
        content MEDIUMTEXT NOT NULL,
        token_input INT NULL,
        token_output INT NULL,
        cost_usd DECIMAL(10,4) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (msg_id),
        INDEX IDX_RORO_AI_MSG_CONV (conv_id),
        INDEX IDX_RORO_AI_MSG_CREATED (created_at)
    ) {$charset};";

    // --- 実行
    foreach ($sqls as $sql) { $wpdb->query($sql); }

    // 後置FK（循環対応）：CUSTOMER.default_pet_id -> PET
    $wpdb->query("ALTER TABLE RORO_CUSTOMER
        ADD CONSTRAINT FK_RORO_CUSTOMER_DEFAULTPET
        FOREIGN KEY (default_pet_id) REFERENCES RORO_PET(pet_id)
        ON UPDATE CASCADE ON DELETE SET NULL");

    // 互換ビュー（既存コードの wp_roro_* 呼称を吸収）
    $views = array(
        'wp_roro_ai_conversation' => 'RORO_AI_CONVERSATION',
        'wp_roro_ai_message'      => 'RORO_AI_MESSAGE',
        'wp_roro_customer'        => 'RORO_CUSTOMER',
        'wp_roro_event_master'    => 'RORO_EVENTS_MASTER',
        'wp_roro_map_favorite'    => 'RORO_MAP_FAVORITE',
        'wp_roro_one_point_advice_master' => 'RORO_ONE_POINT_ADVICE_MASTER',
        'wp_roro_pet'             => 'RORO_PET',
        'wp_roro_travel_spot_master' => 'RORO_TRAVEL_SPOT_MASTER',
    );
    foreach ($views as $v => $t) {
        $wpdb->query("CREATE OR REPLACE VIEW `{$v}` AS SELECT * FROM `{$t}`");
    }
}
