-- サンプル：小さなユーティリティテーブル
CREATE TABLE IF NOT EXISTS roro_demo_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO roro_demo_log (message) VALUES ('Hello RORO SQL Manager');
