-- 推奨インデックス（近傍検索・日付・カテゴリ検索の高速化）
-- テーブル: wp_RORO_EVENTS_MASTER
CREATE INDEX IF NOT EXISTS idx_roro_events_start_time ON RORO_EVENTS_MASTER (start_time);
CREATE INDEX IF NOT EXISTS idx_roro_events_category   ON RORO_EVENTS_MASTER (category);
CREATE INDEX IF NOT EXISTS idx_roro_events_latlng     ON RORO_EVENTS_MASTER (latitude, longitude);
-- 文字列検索向け（必要に応じてFULLTEXTを使用可能: MySQL 5.6+）
-- ALTER TABLE RORO_EVENTS_MASTER ADD FULLTEXT INDEX ft_title_desc_address (title, description, address);
