-- DELIMITER を用いた簡易サンプル（当プラグインのDELIMITER処理の検証用）
-- 本番では権限やポリシーの確認が必須です
DELIMITER $$

DROP PROCEDURE IF EXISTS roro_upsert_advice $$

CREATE PROCEDURE roro_upsert_advice (
  IN p_category VARCHAR(64),
  IN p_text TEXT,
  IN p_lang VARCHAR(8),
  IN p_weight INT
)
BEGIN
  DECLARE v_id BIGINT;
  SELECT id INTO v_id FROM RORO_ONE_POINT_ADVICE_MASTER
   WHERE category = p_category AND lang = p_lang AND advice_text = p_text LIMIT 1;

  IF v_id IS NULL THEN
    INSERT INTO RORO_ONE_POINT_ADVICE_MASTER (category, advice_text, lang, weight, active)
    VALUES (p_category, p_text, p_lang, p_weight, 1);
  ELSE
    UPDATE RORO_ONE_POINT_ADVICE_MASTER SET weight = p_weight, active = 1 WHERE id = v_id;
  END IF;
END $$

DELIMITER ;

-- 例: CALL roro_upsert_advice('walk', '朝の散歩は短めに、水分補給を忘れずに。', 'ja', 120);
