-- =====================================================================
-- RORO 修正版スキーマ & マイグレーション（2025-08-14）
-- 目的:
--   1) 犬猫種: roro_pet_master を正、roro_pet_breed / roro_dog_breed は廃止
--   2) 施設: roro_gmapm（取込）→ roro_facility（正）へ統合・拡張
-- 方針:
--   - 何度実行しても壊れない idempotent 設計（IF EXISTS/IF NOT EXISTS、多くは例外握り）
--   - データ移行は「名称一致」を基本に段階的（breed_label → 旧テーブル経由）
-- 想定DB: MariaDB 10.5+ / MySQL 8+（XServer MariaDB 10.5+）
-- =====================================================================

/* ---------------------------------------------------------------------
 * 0. 事前設定（安全モード）
 * ------------------------------------------------------------------ */
SET NAMES utf8mb4;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

/* ---------------------------------------------------------------------
 * 0.1 ユーティリティ（例外握りの実行/既存FKのドロップ/条件付きFK追加）
 * ------------------------------------------------------------------ */
DROP PROCEDURE IF EXISTS _roro_try_exec;
DROP PROCEDURE IF EXISTS _roro_drop_fk_if_exists;
DROP PROCEDURE IF EXISTS _roro_add_fk_if_absent;
DELIMITER //
CREATE PROCEDURE _roro_try_exec(IN q TEXT)
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  SET @s = q; PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
END//
CREATE PROCEDURE _roro_drop_fk_if_exists(IN tbl VARCHAR(128), IN fk_name VARCHAR(128))
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  SET @s = CONCAT('ALTER TABLE `', tbl, '` DROP FOREIGN KEY `', fk_name, '`');
  PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
END//
CREATE PROCEDURE _roro_add_fk_if_absent(IN tbl VARCHAR(128), IN fk_name VARCHAR(128), IN ddl TEXT)
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n
    FROM information_schema.table_constraints
   WHERE table_schema = DATABASE() AND table_name = tbl
     AND constraint_name = fk_name AND constraint_type='FOREIGN KEY';
  IF n = 0 THEN
    SET @s = ddl; PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END//
DELIMITER ;

/* ---------------------------------------------------------------------
 * 1. データベース作成（なければ）→ USE
 * ------------------------------------------------------------------ */
CREATE DATABASE IF NOT EXISTS `wp_roro_log`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_ja_0900_as_cs;
USE `wp_roro_log`;

/* ---------------------------------------------------------------------
 * 2. 正マスタ: roro_pet_master（存在しない場合の作成 + 一意性）
 * ------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS `roro_pet_master` (
  `petm_id`          VARCHAR(32)  NOT NULL               COMMENT '種別マスタID（例: PETM_00001）',
  `pet_type`         VARCHAR(16)  NOT NULL               COMMENT 'ペット種別（DOG/CAT/DOG_CAT等）',
  `breed_name`       VARCHAR(120) NOT NULL               COMMENT '犬猫種名',
  `category_code`    VARCHAR(10)  DEFAULT NULL           COMMENT 'カテゴリコード（A〜H、CAT等）',
  `population`       INT UNSIGNED DEFAULT NULL           COMMENT '頭数推定値',
  `population_rate`  DECIMAL(5,2) DEFAULT NULL           COMMENT '割合(%)',
  `old_category`     VARCHAR(10)  DEFAULT NULL           COMMENT '旧カテゴリコード',
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
  PRIMARY KEY (`petm_id`),
  UNIQUE KEY `uk_roro_pet_master_breed_name` (`breed_name`),
  KEY `idx_roro_pet_master_type` (`pet_type`),
  KEY `idx_roro_pet_master_category` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ja_0900_as_cs COMMENT='犬猫種別マスタ（正）';

/* ---------------------------------------------------------------------
 * 3. 施設マスタ（正）: 不足列の拡張・一意キーの追加（既存でも安全に）
 * ------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS `roro_facility` (
  `facility_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '施設ID',
  `name`        VARCHAR(120)  NOT NULL                COMMENT '施設名',
  `category`    ENUM('cafe','hospital','salon','park','hotel','school','store') NOT NULL COMMENT '施設カテゴリ',
  `lat`         DECIMAL(10,8) NOT NULL                COMMENT '緯度',
  `lng`         DECIMAL(11,8) NOT NULL                COMMENT '経度',
  `facility_pt` POINT SRID 4326 NOT NULL /*!80000 INVISIBLE */ COMMENT '位置情報（ポイント型）',
  `address`     VARCHAR(191) DEFAULT NULL             COMMENT '住所',
  `phone`       VARCHAR(32)  DEFAULT NULL             COMMENT '電話番号',
  -- ↓ 統合のための拡張列
  `google_place_id` VARCHAR(255) DEFAULT NULL         COMMENT 'Google Place ID',
  `opening_hours`   VARCHAR(191) DEFAULT NULL         COMMENT '営業時間',
  `regular_holiday` VARCHAR(191) DEFAULT NULL         COMMENT '定休日',
  `homepage`        VARCHAR(255) DEFAULT NULL         COMMENT '公式サイト',
  `google_rating`   DECIMAL(3,2) DEFAULT NULL         COMMENT 'Google口コミ点数',
  `google_reviews`  INT DEFAULT NULL                  COMMENT 'Google口コミ件数',
  `cats_ok`         TINYINT(1) NOT NULL DEFAULT 0     COMMENT '猫OK',
  `pet_summary`     TEXT DEFAULT NULL                 COMMENT 'ペット情報要約',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  PRIMARY KEY (`facility_id`),
  KEY `idx_roro_facility_category` (`category`),
  UNIQUE KEY `uk_roro_facility_place` (`google_place_id`),
  UNIQUE KEY `uk_roro_facility_name_addr` (`name`,`address`),
  SPATIAL INDEX `spx_roro_facility_pt` (`facility_pt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ja_0900_as_cs COMMENT='施設マスタ（正）';

-- 既存環境に対し、拡張列・一意キーが欠けていれば追加
ALTER TABLE `roro_facility`
  ADD COLUMN IF NOT EXISTS `google_place_id` VARCHAR(255) DEFAULT NULL COMMENT 'Google Place ID' AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `opening_hours`   VARCHAR(191) DEFAULT NULL COMMENT '営業時間'        AFTER `google_place_id`,
  ADD COLUMN IF NOT EXISTS `regular_holiday` VARCHAR(191) DEFAULT NULL COMMENT '定休日'          AFTER `opening_hours`,
  ADD COLUMN IF NOT EXISTS `homepage`        VARCHAR(255) DEFAULT NULL COMMENT '公式サイト'      AFTER `regular_holiday`,
  ADD COLUMN IF NOT EXISTS `google_rating`   DECIMAL(3,2) DEFAULT NULL COMMENT 'Google口コミ点数' AFTER `homepage`,
  ADD COLUMN IF NOT EXISTS `google_reviews`  INT DEFAULT NULL           COMMENT 'Google口コミ件数' AFTER `google_rating`,
  ADD COLUMN IF NOT EXISTS `cats_ok`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '猫OK'        AFTER `google_reviews`,
  ADD COLUMN IF NOT EXISTS `pet_summary`     TEXT DEFAULT NULL          COMMENT 'ペット情報要約'  AFTER `cats_ok`;

SET @sql := (SELECT IF(
  (SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roro_facility' AND INDEX_NAME='uk_roro_facility_place')=0,
  'ALTER TABLE `roro_facility` ADD UNIQUE KEY `uk_roro_facility_place` (`google_place_id`)',
  'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
  (SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='roro_facility' AND INDEX_NAME='uk_roro_facility_name_addr')=0,
  'ALTER TABLE `roro_facility` ADD UNIQUE KEY `uk_roro_facility_name_addr` (`name`,`address`)',
  'SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* ---------------------------------------------------------------------
 * 4. 施設ID対応表（外部ソース→正テーブル）
 * ------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS `roro_facility_source_map` (
  `source`       ENUM('travel_spot','gmapm') NOT NULL COMMENT 'ソース種別',
  `source_key`   VARCHAR(64) NOT NULL                COMMENT 'ソース側キー（tsm_id/gmapm_id等）',
  `facility_id`  INT UNSIGNED NOT NULL               COMMENT '施設ID（roro_facility）',
  `linked_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '紐付け日時',
  PRIMARY KEY (`source`,`source_key`),
  KEY `idx_roro_facility_source_map_facility` (`facility_id`),
  CONSTRAINT `fk_roro_facility_source_map_facility`
      FOREIGN KEY (`facility_id`) REFERENCES `roro_facility` (`facility_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ja_0900_as_cs COMMENT='施設ID紐付けマップ';

/* ---------------------------------------------------------------------
 * 5. ペット関連の切替: roro_pet / roro_photo → petm_id（roro_pet_master）
 * ------------------------------------------------------------------ */
-- 5-1. roro_pet に petm_id を追加（NULL許容で先に追加）
ALTER TABLE `roro_pet`
  ADD COLUMN IF NOT EXISTS `petm_id` VARCHAR(32) NULL COMMENT 'PET_MASTERのID';

-- 旧FKの解除（環境で名が異なるため代表的なものを試行）
CALL _roro_drop_fk_if_exists('roro_pet', 'fk_roro_pet_breed');
CALL _roro_drop_fk_if_exists('roro_pet', 'roro_pet_ibfk_2');

-- 名称一致で petm_id を補完（breed_label を優先）
UPDATE `roro_pet` p
LEFT JOIN `roro_pet_master` pm
       ON pm.breed_name = COALESCE(p.breed_label, '')
SET p.petm_id = pm.petm_id
WHERE p.petm_id IS NULL AND COALESCE(p.breed_label,'') <> '';

-- 旧テーブルがあれば breed_id→name 経由で補完（例外は握って続行）
CALL _roro_try_exec('
  UPDATE `roro_pet` p
  JOIN `roro_pet_breed` b ON p.breed_id = b.breed_id
  JOIN `roro_pet_master` pm ON pm.breed_name = b.name
  SET p.petm_id = pm.petm_id
  WHERE p.petm_id IS NULL
');

CALL _roro_try_exec('
  UPDATE `roro_pet` p
  JOIN `roro_dog_breed` b ON p.breed_id = b.breed_id
  JOIN `roro_pet_master` pm ON pm.breed_name = b.name
  SET p.petm_id = pm.petm_id
  WHERE p.petm_id IS NULL
');

-- 新FK（petm_id→roro_pet_master）
CALL _roro_add_fk_if_absent(
  'roro_pet',
  'fk_roro_pet_petm',
  'ALTER TABLE `roro_pet` ADD CONSTRAINT `fk_roro_pet_petm` FOREIGN KEY (`petm_id`) REFERENCES `roro_pet_master`(`petm_id`) ON DELETE SET NULL'
);

-- 旧列（breed_id）とインデックスを撤去（存在時のみ）
ALTER TABLE `roro_pet` DROP COLUMN IF EXISTS `breed_id`;
CALL _roro_try_exec('DROP INDEX `idx_roro_pet_breed` ON `roro_pet`');

-- 5-2. roro_photo に petm_id を追加
ALTER TABLE `roro_photo`
  ADD COLUMN IF NOT EXISTS `petm_id` VARCHAR(32) NULL COMMENT 'PET_MASTERのID';

-- 旧FK解除（代表名を試行）
CALL _roro_drop_fk_if_exists('roro_photo', 'fk_roro_photo_breed');
CALL _roro_drop_fk_if_exists('roro_photo', 'roro_photo_ibfk_3');

-- 旧テーブルがあれば breed_id→name 経由で補完（例外握り）
CALL _roro_try_exec('
  UPDATE `roro_photo` ph
  JOIN `roro_pet_breed` b ON ph.breed_id = b.breed_id
  JOIN `roro_pet_master` pm ON pm.breed_name = b.name
  SET ph.petm_id = pm.petm_id
  WHERE ph.petm_id IS NULL
');
CALL _roro_try_exec('
  UPDATE `roro_photo` ph
  JOIN `roro_dog_breed` b ON ph.breed_id = b.breed_id
  JOIN `roro_pet_master` pm ON pm.breed_name = b.name
  SET ph.petm_id = pm.petm_id
  WHERE ph.petm_id IS NULL
');

-- 新FK（petm_id→roro_pet_master）
CALL _roro_add_fk_if_absent(
  'roro_photo',
  'fk_roro_photo_petm',
  'ALTER TABLE `roro_photo` ADD CONSTRAINT `fk_roro_photo_petm` FOREIGN KEY (`petm_id`) REFERENCES `roro_pet_master`(`petm_id`) ON DELETE SET NULL'
);

-- 旧列（breed_id）とインデックスを撤去
ALTER TABLE `roro_photo` DROP COLUMN IF EXISTS `breed_id`;
CALL _roro_try_exec('DROP INDEX `idx_roro_photo_breed` ON `roro_photo`');

/* ---------------------------------------------------------------------
 * 6. 旧犬種テーブルの廃止（最後に DROP）
 * ------------------------------------------------------------------ */
DROP TABLE IF EXISTS `roro_pet_breed`;
DROP TABLE IF EXISTS `roro_dog_breed`;

/* ---------------------------------------------------------------------
 * 7. 施設統合: roro_gmapm（取込）→ roro_facility（正）
 *    - name+address または google_place_id で重複回避
 *    - 統合結果は roro_facility_source_map に記録
 * ------------------------------------------------------------------ */
-- ステージングが無い環境でもエラーにならないよう定義は任意
CREATE TABLE IF NOT EXISTS `roro_gmapm` (
  `gmapm_id`          VARCHAR(64) NOT NULL               COMMENT 'Excel:GMAPM_ID',
  `region`            VARCHAR(50) DEFAULT NULL           COMMENT '地方',
  `sub_region`        VARCHAR(50) DEFAULT NULL           COMMENT 'ローカルエリア',
  `ward`              VARCHAR(50) DEFAULT NULL           COMMENT '区',
  `genre`             VARCHAR(120) DEFAULT NULL          COMMENT 'ジャンル',
  `shop_name`         VARCHAR(200) DEFAULT NULL          COMMENT '店名',
  `operating_hours`   VARCHAR(120) DEFAULT NULL          COMMENT '営業時間',
  `regular_holiday`   VARCHAR(120) DEFAULT NULL          COMMENT '定休日',
  `address`           VARCHAR(255) DEFAULT NULL          COMMENT '住所',
  `homepage`          VARCHAR(255) DEFAULT NULL          COMMENT '公式サイト',
  `google_rating`     DECIMAL(3,2) DEFAULT NULL          COMMENT 'Google口コミ点数',
  `google_reviews`    INT DEFAULT NULL                   COMMENT 'Google口コミ件数',
  `dogs_category`     VARCHAR(20) DEFAULT NULL           COMMENT '犬種カテゴリ',
  `cats_ok`           TINYINT(1) NOT NULL DEFAULT 0      COMMENT '猫OK',
  `pet_summary`       TEXT DEFAULT NULL                  COMMENT 'ペット情報要約',
  `imported_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '取込日時',
  PRIMARY KEY (`gmapm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ja_0900_as_cs COMMENT='GoogleMapマスタ';

-- 取込（存在時のみ実行）
CALL _roro_try_exec('
WITH mapped AS (
  SELECT
    g.gmapm_id,
    g.shop_name                              AS name,
    NULL AS lat, NULL AS lng,  -- 位置不明の場合は後続のジオコーディングで補完
    g.address,
    g.operating_hours                        AS opening_hours,
    g.regular_holiday,
    g.homepage,
    g.google_rating,
    g.google_reviews,
    g.cats_ok,
    g.pet_summary,
    CASE
      WHEN g.genre REGEXP ''カフェ|喫茶|茶|Cafe|coffee'' THEN ''cafe''
      WHEN g.genre REGEXP ''病院|クリニック|獣医|Hospital|Vet'' THEN ''hospital''
      WHEN g.genre REGEXP ''サロン|トリミング|groom'' THEN ''salon''
      WHEN g.genre REGEXP ''公園|park'' THEN ''park''
      WHEN g.genre REGEXP ''ホテル|宿|Hotel'' THEN ''hotel''
      WHEN g.genre REGEXP ''学校|スクール|School'' THEN ''school''
      ELSE ''store''
    END AS category
  FROM `roro_gmapm` g
)
INSERT INTO `roro_facility`
  (`name`,`category`,`lat`,`lng`,`facility_pt`,`address`,`phone`,
   `google_place_id`,`opening_hours`,`regular_holiday`,`homepage`,
   `google_rating`,`google_reviews`,`cats_ok`,`pet_summary`)
SELECT
  m.name, m.category,
  m.lat, m.lng,
  IF(m.lat IS NULL OR m.lng IS NULL, ST_SRID(POINT(0,0),4326), ST_SRID(POINT(m.lng,m.lat),4326)) AS facility_pt,
  m.address,
  NULL AS phone,
  NULL AS google_place_id,
  m.opening_hours, m.regular_holiday, m.homepage,
  m.google_rating, m.google_reviews, m.cats_ok, m.pet_summary
FROM mapped m
ON DUPLICATE KEY UPDATE
  `category`        = VALUES(`category`),
  `opening_hours`   = COALESCE(VALUES(`opening_hours`), `roro_facility`.`opening_hours`),
  `regular_holiday` = COALESCE(VALUES(`regular_holiday`), `roro_facility`.`regular_holiday`),
  `homepage`        = COALESCE(VALUES(`homepage`), `roro_facility`.`homepage`),
  `google_rating`   = COALESCE(VALUES(`google_rating`), `roro_facility`.`google_rating`),
  `google_reviews`  = COALESCE(VALUES(`google_reviews`), `roro_facility`.`google_reviews`),
  `cats_ok`         = GREATEST(`roro_facility`.`cats_ok`, VALUES(`cats_ok`)),
  `pet_summary`     = COALESCE(VALUES(`pet_summary`), `roro_facility`.`pet_summary`)
');

-- name+address で facility_id を解決し対応表に反映
CALL _roro_try_exec('
INSERT INTO `roro_facility_source_map` (`source`,`source_key`,`facility_id`)
SELECT ''gmapm'', g.`gmapm_id`, f.`facility_id`
FROM `roro_gmapm` g
JOIN `roro_facility` f
  ON f.`name` = g.`shop_name` AND f.`address` = g.`address`
ON DUPLICATE KEY UPDATE `facility_id` = VALUES(`facility_id`)
');

/* ---------------------------------------------------------------------
 * 8. 後片付け（ユーティリティの削除 & 各種設定戻し）
 * ------------------------------------------------------------------ */
DROP PROCEDURE IF EXISTS _roro_add_fk_if_absent;
DROP PROCEDURE IF EXISTS _roro_drop_fk_if_exists;
DROP PROCEDURE IF EXISTS _roro_try_exec;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- 実行完了
