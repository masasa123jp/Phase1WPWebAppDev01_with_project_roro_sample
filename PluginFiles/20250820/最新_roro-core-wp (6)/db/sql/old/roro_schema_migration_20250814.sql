
-- =====================================================================
-- RORO 修正版スキーマ & マイグレーションスクリプト（2025-08-14）
-- 要件反映：
--   1) 犬猫種マスタは roro_pet_master を正とする（従属テーブルFK切替）
--   2) 施設は roro_gmapm（取込）→ roro_facility（正）へ統合
-- 対象: MariaDB / MySQL 8+（XServer MariaDB 10.5+想定）
-- 安全設計: できるだけ IF EXISTS/IF NOT EXISTS を使用し、再実行可能に配慮
-- =====================================================================

/* ---------------------------------------------------------------------
 * 0. 事前設定
 * ------------------------------------------------------------------ */
SET NAMES utf8mb4;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

/* ---------------------------------------------------------------------
 * 1. 正マスタ: roro_pet_master （存在しない場合の作成 + 補助インデックス）
 * ------------------------------------------------------------------ */
CREATE TABLE IF NOT EXISTS `roro_pet_master` (
  `petm_id`          VARCHAR(32) NOT NULL               COMMENT '種別マスタID（例: PETM_00001）',
  `pet_type`         VARCHAR(16) NOT NULL               COMMENT 'ペット種別（DOG/CAT/DOG_CAT等）',
  `breed_name`       VARCHAR(120) NOT NULL              COMMENT '犬猫種名',
  `category_code`    VARCHAR(10) DEFAULT NULL           COMMENT 'カテゴリコード（A〜H、CAT等）',
  `population`       INT UNSIGNED DEFAULT NULL          COMMENT '頭数推定値',
  `population_rate`  DECIMAL(5,2) DEFAULT NULL          COMMENT '割合(%)',
  `old_category`     VARCHAR(10) DEFAULT NULL           COMMENT '旧カテゴリコード',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
  PRIMARY KEY (`petm_id`),
  KEY `idx_roro_pet_master_type` (`pet_type`),
  KEY `idx_roro_pet_master_category` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ja_0900_as_cs COMMENT='犬猫種別マスタ（PET_MASTER 正）';

-- 品種名の一意性（必要ならコメントアウト解除）
ALTER TABLE `roro_pet_master`
  ADD UNIQUE KEY `uk_roro_pet_master_breed_name` (`breed_name`);

/* ---------------------------------------------------------------------
 * 2. 従属テーブルのFKを roro_pet_master へ切替（roro_pet / roro_photo）
 *    - 新列 petm_id を追加（NULL許容）→ データ移行 → 旧列/旧FKを撤去
 *    - 旧マスタ roro_pet_breed / roro_dog_breed から名称一致でマッピング
 * ------------------------------------------------------------------ */

-- 2-1. roro_pet
ALTER TABLE `roro_pet`
  ADD COLUMN IF NOT EXISTS `petm_id` VARCHAR(32) NULL COMMENT 'PET_MASTERのID';

-- 旧FK の存在に備えて緩和（存在しない場合は無視）
-- FK 名は環境により異なるため、エラー時は無視するハンドラを一時設定
DROP PROCEDURE IF EXISTS _roro_drop_fk_if_exists;
DELIMITER //
CREATE PROCEDURE _roro_drop_fk_if_exists(IN tbl VARCHAR(128), IN fk_name VARCHAR(128))
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  SET @s = CONCAT('ALTER TABLE `', tbl, '` DROP FOREIGN KEY `', fk_name, '`');
  PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
END//
DELIMITER ;

CALL _roro_drop_fk_if_exists('roro_pet','fk_roro_pet_breed');
CALL _roro_drop_fk_if_exists('roro_pet','roro_pet_ibfk_2'); -- 汎用名の保険

-- 名称一致で petm_id を埋める（roro_pet_breed or roro_dog_breed を考慮）
-- roro_pet.breed_label があれば優先してマッチ
UPDATE `roro_pet` p
LEFT JOIN `roro_pet_master` pm
       ON (pm.breed_name = COALESCE(p.breed_label, ''))
SET p.petm_id = pm.petm_id
WHERE p.petm_id IS NULL AND COALESCE(p.breed_label,'') <> '';

-- 次に roro_pet_breed 経由でマッチ
UPDATE `roro_pet` p
JOIN `roro_pet_breed` b ON p.breed_id = b.breed_id
JOIN `roro_pet_master` pm ON pm.breed_name = b.name
SET p.petm_id = pm.petm_id
WHERE p.petm_id IS NULL;

-- 次に roro_dog_breed がある環境向けのフォールバック
UPDATE `roro_pet` p
JOIN `roro_dog_breed` b ON p.breed_id = b.breed_id
JOIN `roro_pet_master` pm ON pm.breed_name = b.name
SET p.petm_id = pm.petm_id
WHERE p.petm_id IS NULL;

-- 新FK付与（存在済でもOKなようハンドラ利用）
DELIMITER //
CREATE PROCEDURE _roro_add_fk_pet_petm()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  ALTER TABLE `roro_pet`
    ADD CONSTRAINT `fk_roro_pet_petm`
    FOREIGN KEY (`petm_id`) REFERENCES `roro_pet_master`(`petm_id`) ON DELETE SET NULL;
END//
DELIMITER ;
CALL _roro_add_fk_pet_petm();

-- 旧列の撤去（存在する場合のみ）
ALTER TABLE `roro_pet` DROP COLUMN IF EXISTS `breed_id`;
DROP INDEX `idx_roro_pet_breed` ON `roro_pet`;

-- 2-2. roro_photo
ALTER TABLE `roro_photo`
  ADD COLUMN IF NOT EXISTS `petm_id` VARCHAR(32) NULL COMMENT 'PET_MASTERのID';

CALL _roro_drop_fk_if_exists('roro_photo','fk_roro_photo_breed');
CALL _roro_drop_fk_if_exists('roro_photo','roro_photo_ibfk_3');

-- roro_photo は犬種名を持たない想定のため、breed_id→name 経由でマッチ
UPDATE `roro_photo` ph
JOIN `roro_pet_breed` b ON ph.breed_id = b.breed_id
JOIN `roro_pet_master` pm ON pm.breed_name = b.name
SET ph.petm_id = pm.petm_id
WHERE ph.petm_id IS NULL;

UPDATE `roro_photo` ph
JOIN `roro_dog_breed` b ON ph.breed_id = b.breed_id
JOIN `roro_pet_master` pm ON pm.breed_name = b.name
SET ph.petm_id = pm.petm_id
WHERE ph.petm_id IS NULL;

DELIMITER //
CREATE PROCEDURE _roro_add_fk_photo_petm()
BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
  ALTER TABLE `roro_photo`
    ADD CONSTRAINT `fk_roro_photo_petm`
    FOREIGN KEY (`petm_id`) REFERENCES `roro_pet_master`(`petm_id`) ON DELETE SET NULL;
END//
DELIMITER ;
CALL _roro_add_fk_photo_petm();

ALTER TABLE `roro_photo` DROP COLUMN IF EXISTS `breed_id`;
DROP INDEX `idx_roro_photo_breed` ON `roro_photo`;

-- 不要になった一時プロシージャを削除
DROP PROCEDURE IF EXISTS _roro_add_fk_pet_petm;
DROP PROCEDURE IF EXISTS _roro_add_fk_photo_petm;
DROP PROCEDURE IF EXISTS _roro_drop_fk_if_exists;

/* ---------------------------------------------------------------------
 * 3. 施設統合: roro_gmapm（取込）→ roro_facility（正）
 *    - roro_facility を拡張（Place ID/営業時間/評価 等）
 *    - name+address または google_place_id で重複回避
 *    - 統合対応表: roro_facility_source_map を使用
 * ------------------------------------------------------------------ */

-- 3-1. 正テーブルを拡張（不足属性を追加）
ALTER TABLE `roro_facility`
  ADD COLUMN IF NOT EXISTS `google_place_id` VARCHAR(255) DEFAULT NULL COMMENT 'Google Place ID' AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `opening_hours`   VARCHAR(191) DEFAULT NULL COMMENT '営業時間' AFTER `google_place_id`,
  ADD COLUMN IF NOT EXISTS `regular_holiday` VARCHAR(191) DEFAULT NULL COMMENT '定休日' AFTER `opening_hours`,
  ADD COLUMN IF NOT EXISTS `homepage`        VARCHAR(255) DEFAULT NULL COMMENT '公式サイト' AFTER `regular_holiday`,
  ADD COLUMN IF NOT EXISTS `google_rating`   DECIMAL(3,2) DEFAULT NULL COMMENT 'Google口コミ点数' AFTER `homepage`,
  ADD COLUMN IF NOT EXISTS `google_reviews`  INT DEFAULT NULL COMMENT 'Google口コミ件数' AFTER `google_rating`,
  ADD COLUMN IF NOT EXISTS `cats_ok`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '猫OK' AFTER `google_reviews`,
  ADD COLUMN IF NOT EXISTS `pet_summary`     TEXT DEFAULT NULL COMMENT 'ペット情報要約' AFTER `cats_ok`;

-- 重複回避キー（存在していなければ追加）
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

-- 3-2. 対応表（roro_facility_source_map）
CREATE TABLE IF NOT EXISTS `roro_facility_source_map` (
  `source`       ENUM('travel_spot','gmapm') NOT NULL COMMENT 'ソース種別',
  `source_key`   VARCHAR(64) NOT NULL                 COMMENT 'ソース側キー（tsm_id/gmapm_id等）',
  `facility_id`  INT UNSIGNED NOT NULL                COMMENT '施設ID（roro_facility）',
  `linked_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '紐付け日時',
  PRIMARY KEY (`source`,`source_key`),
  KEY `idx_roro_facility_source_map_facility` (`facility_id`),
  CONSTRAINT `fk_roro_facility_source_map_facility`
      FOREIGN KEY (`facility_id`) REFERENCES `roro_facility` (`facility_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ja_0900_as_cs COMMENT='施設ID紐付けマップ（重複回避）';

-- 3-3. 取込（UPSERT）: roro_gmapm → roro_facility
--     ※ gmapm 側に緯度経度が無い場合は別途ジオコーディングで埋めてから実施することを推奨
WITH mapped AS (
  SELECT
    g.gmapm_id,
    g.shop_name                              AS name,
    NULL AS lat, NULL AS lng,
    g.address,
    g.operating_hours                        AS opening_hours,
    g.regular_holiday,
    g.homepage,
    g.google_rating,
    g.google_reviews,
    g.cats_ok,
    g.pet_summary,
    CASE
      WHEN g.genre REGEXP 'カフェ|喫茶|茶|Cafe|coffee' THEN 'cafe'
      WHEN g.genre REGEXP '病院|クリニック|獣医|Hospital|Vet' THEN 'hospital'
      WHEN g.genre REGEXP 'サロン|トリミング|groom' THEN 'salon'
      WHEN g.genre REGEXP '公園|park' THEN 'park'
      WHEN g.genre REGEXP 'ホテル|宿|Hotel' THEN 'hotel'
      WHEN g.genre REGEXP '学校|スクール|School' THEN 'school'
      ELSE 'store'
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
  `pet_summary`     = COALESCE(VALUES(`pet_summary`), `roro_facility`.`pet_summary`);

-- 3-4. 対応表の更新（name+address マッチで facility_id を引く）
INSERT INTO `roro_facility_source_map` (`source`,`source_key`,`facility_id`)
SELECT
  'gmapm' AS source,
  g.`gmapm_id` AS source_key,
  f.`facility_id`
FROM `roro_gmapm` g
JOIN `roro_facility` f
  ON f.`name` = g.`shop_name` AND f.`address` = g.`address`
ON DUPLICATE KEY UPDATE `facility_id` = VALUES(`facility_id`);

/* ---------------------------------------------------------------------
 * 4. 後片付け
 * ------------------------------------------------------------------ */
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- 実行完了
