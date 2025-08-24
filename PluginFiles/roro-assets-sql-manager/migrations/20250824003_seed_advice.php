<?php
// 複数のマイグレーション定義を返す例（up/down）
// - up: サンプル・アドバイスの投入
// - down: サンプル・アドバイスの削除
return array(
  array(
    'id'          => '20250824003_seed_advice_up',
    'description' => 'Seed: ONE_POINT_ADVICE (ja/en/zh/ko)',
    'group'       => 'seed',
    'depends'     => array('20250824001_init_core'),
    'up'          => function () {
      $sql = "
        INSERT INTO RORO_ONE_POINT_ADVICE_MASTER (category, advice_text, lang, weight, active) VALUES
          ('walk', '朝の散歩は短めに、水分補給を忘れずに。', 'ja', 120, 1),
          ('walk', 'Keep the morning walk short; don’t forget hydration.', 'en', 120, 1),
          ('walk', '早晨散步时间不宜过长，别忘了补水。', 'zh', 120, 1),
          ('walk', '아침 산책은 짧게, 수분 보충 잊지 마세요.', 'ko', 120, 1),
          ('heat', '真夏の散歩は地面の温度に注意（肉球火傷）。', 'ja', 110, 1),
          ('heat', 'In mid-summer, watch the ground temperature (paw burn risk).', 'en', 110, 1);
      ";
      return roro_sql_manager_execute_sql($sql, false) === true;
    },
    'down'        => function () {
      $sql = "
        DELETE FROM RORO_ONE_POINT_ADVICE_MASTER 
        WHERE category IN ('walk','heat') 
          AND advice_text IN (
            '朝の散歩は短めに、水分補給を忘れずに。','Keep the morning walk short; don’t forget hydration.',
            '早晨散步时间不宜过长，别忘了补水。','아침 산책은 짧게, 수분 보충 잊지 마세요.',
            '真夏の散歩は地面の温度に注意（肉球火傷）。',
            'In mid-summer, watch the ground temperature (paw burn risk).'
          );
      ";
      return roro_sql_manager_execute_sql($sql, false) === true;
    },
  ),
);
