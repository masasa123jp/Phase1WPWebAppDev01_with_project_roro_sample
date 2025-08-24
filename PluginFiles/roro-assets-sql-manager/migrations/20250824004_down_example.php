<?php
// down専用のサンプル（運用では「apply時の削除」は通常不要なので参考用）
return array(
  array(
    'id'          => '20250824004_down_example',
    'description' => 'Example: drop stored procedure',
    'group'       => 'ops',
    'down'        => "
      DROP PROCEDURE IF EXISTS roro_upsert_advice;
    ",
  ),
);
