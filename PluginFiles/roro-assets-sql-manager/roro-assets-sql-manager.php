<?php 
/**
 * Plugin Name: RORO Assets SQL Manager
 * Description: ROROプロジェクトのDBマイグレーションを管理（DDLのハードコーディングなし）。migrations/ のSQL・PHP、またはフィルタ登録から適用。DELIMITER対応の簡易パーサ、依存解決、dry-run、トランザクション、ログ保持、ロールバック（任意）に対応。
 * Version: 1.3.0
 * Author: RORO Dev Team
 * Text Domain: roro-assets-sql-manager
 */

if (!defined('ABSPATH')) exit;

/** ==============================
 *  設定定数（必要に応じて調整可）
 *  ============================== */
define('RORO_SQL_MANAGER_SLUG', 'roro-assets-sql-manager');
define('RORO_SQL_MANAGER_VER',  '1.3.0');
define('RORO_SQL_MANAGER_OPT',  'roro_sql_applied'); // 適用済みマイグレーションID配列を格納
define('RORO_SQL_MANAGER_LOG',  'roro_sql_log');     // 直近のログ（配列）を保存
define('RORO_SQL_MANAGER_LOG_MAX', 200);             // ログ保持上限（元は100）

/** 便利関数 */
function roro_sql_manager_dir() { return plugin_dir_path(__FILE__); }
function roro_sql_manager_url() { return plugin_dir_url(__FILE__); }
function roro_sql_manager_now() { return gmdate('Y-m-d H:i:s'); }

/** 有効化時：オプション初期化 */
register_activation_hook(__FILE__, function () {
  if (get_option(RORO_SQL_MANAGER_OPT) === false) {
    add_option(RORO_SQL_MANAGER_OPT, array(), false);
  }
  if (get_option(RORO_SQL_MANAGER_LOG) === false) {
    add_option(RORO_SQL_MANAGER_LOG, array(), false);
  }
});

/** ---------------------------------
 *  ログ保存（最新 RORO_SQL_MANAGER_LOG_MAX 件）
 *  level: INFO/ERROR/WARN など
 *  --------------------------------- */
function roro_sql_manager_log($level, $message, $context = array()) {
  $log = get_option(RORO_SQL_MANAGER_LOG, array());
  $log[] = array(
    'time' => roro_sql_manager_now(),
    'level'=> strtoupper($level),
    'msg'  => $message,
    'ctx'  => $context
  );
  if (count($log) > RORO_SQL_MANAGER_LOG_MAX) { 
    $log = array_slice($log, -RORO_SQL_MANAGER_LOG_MAX); 
  }
  update_option(RORO_SQL_MANAGER_LOG, $log, false);
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[RORO SQL] ' . strtoupper($level) . ': ' . $message . (empty($context) ? '' : ' ' . wp_json_encode($context)));
  }
}

/** =========================================================================================
 *  マイグレーション探索（migrations/配下 + フィルタ 'roro_sql_register_migrations'）
 *  -----------------------------------------------------------------------------------------
 *  マイグレーションの形式例：
 *  [
 *    'id'          => '20250824001_create_advice_table', // 一意なID（昇順適用推奨）
 *    'description' => 'Create advice table',
 *    'group'       => 'core',                            // 任意グループ
 *    'depends'     => ['20250823001_init'],              // 任意依存
 *    'up'          => (string|callable|array)            // SQL文字列 or callable or ['file'=>'/path/to.sql'] or ['sql'=>'...']
 *    'down'        => (省略可) 同上（ロールバック）
 *  ]
 *  ========================================================================================= */

/** migrations ディレクトリの再帰走査（.sql/.php） */
function roro_sql_manager_glob_recursive($dir, $pattern = '/\.(sql|php)$/i') {
  $res = array();
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if (preg_match($pattern, $file->getFilename())) {
      $res[] = $file->getPathname();
    }
  }
  return $res;
}

/** すべてのマイグレーションを集約 */
function roro_sql_manager_discover_migrations() {
  $migrations = array();

  // 1) フォルダから読み込み（/migrations/*.sql or *.php） ※サブディレクトリも走査
  $dir = trailingslashit(roro_sql_manager_dir()) . 'migrations';
  if (is_dir($dir)) {
    $files = roro_sql_manager_glob_recursive($dir);
    foreach ($files as $file) {
      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      if ($ext === 'sql') {
        // .sql は単純SQL（upのみ）
        $id = basename($file, '.sql');
        $migrations[$id] = array(
          'id'          => $id,
          'description' => 'SQL file: ' . basename($file),
          'group'       => 'fs',
          'depends'     => array(),
          'up'          => array('file' => $file),
        );
      } elseif ($ext === 'php') {
        // .php は return 形式（単体 or 複数配列）
        $res = include $file; // 返値にマイグレーション定義（単体 or 配列）を期待
        if (is_array($res) && isset($res['id'])) {
          $migrations[$res['id']] = $res;
        } elseif (is_array($res)) {
          foreach ($res as $mig) {
            if (is_array($mig) && isset($mig['id'])) {
              $migrations[$mig['id']] = $mig;
            }
          }
        }
      }
    }
  }

  // 2) 外部プラグインからの登録（フィルタ）
  $migrations = apply_filters('roro_sql_register_migrations', $migrations);

  // IDで昇順ソート
  uasort($migrations, function ($a, $b) {
    return strcmp($a['id'], $b['id']);
  });

  return $migrations;
}

/** =========================================================================================
 *  SQL分割ユーティリティ
 *  -----------------------------------------------------------------------------------------
 *  - 文字列・コメント内の ';' を無視
 *  - 先頭等での `DELIMITER <token>` に対応（簡易）
 *  - デフォルト区切りは ';'
 *  ========================================================================================= */
function roro_sql_manager_split_sql($sql_raw) {
  $sql = str_replace("\r\n", "\n", (string)$sql_raw);
  $len = strlen($sql);
  $stmts = array();
  $buf = '';
  $in_str = false;
  $str_ch = '';
  $in_sl_comment = false; // -- ... \n or # ...
  $in_ml_comment = false; // /* ... */
  $delim = ';';           // 現在のデリミタ
  $line_start = 0;

  $is_delimiter_line = function($line) use (&$delim) {
    // 例: DELIMITER $$  / Delimiter //
    $trim = ltrim($line);
    if (stripos($trim, 'DELIMITER ') === 0) {
      $token = trim(substr($trim, 10));
      if ($token !== '') { $delim = $token; }
      return true;
    }
    return false;
  };

  $i = 0;
  while ($i < $len) {
    $ch = $sql[$i];
    $n2 = ($i+1 < $len) ? ($ch . $sql[$i+1]) : '';

    // 行頭判断
    if ($i === $line_start) {
      // 現在行を取得
      $eol = strpos($sql, "\n", $i);
      $line = ($eol === false) ? substr($sql, $i) : substr($sql, $i, $eol - $i);

      // DELIMITER 行？
      if (!$in_str && !$in_sl_comment && !$in_ml_comment && $is_delimiter_line($line)) {
        // その行は蓄積せずスキップ
        if ($eol === false) break;
        $i = $eol + 1;
        $line_start = $i;
        continue;
      }
    }

    // コメント終端
    if ($in_sl_comment) {
      $buf .= $ch;
      if ($ch === "\n") { $in_sl_comment = false; $line_start = $i + 1; }
      $i++;
      continue;
    }
    if ($in_ml_comment) {
      $buf .= $ch;
      if ($n2 === '*/') { $buf .= $sql[$i+1]; $i += 2; $in_ml_comment = false; continue; }
      $i++;
      continue;
    }

    // 文字列終端
    if ($in_str) {
      $buf .= $ch;
      if ($ch === $str_ch) {
        // エスケープ対応（\' \" ``）
        $escaped = ($i > 0 && $sql[$i-1] === '\\');
        if (!$escaped) { $in_str = false; $str_ch = ''; }
      }
      if ($ch === "\n") { $line_start = $i + 1; }
      $i++;
      continue;
    }

    // コメント開始？
    if ($n2 === '--' || $ch === '#') { $in_sl_comment = true; $buf .= $ch; $i++; continue; }
    if ($n2 === '/*') { $in_ml_comment = true; $buf .= $n2; $i += 2; continue; }

    // 文字列開始？
    if ($ch === "'" || $ch === '"' || $ch === '`') {
      $in_str = true; $str_ch = $ch; $buf .= $ch; $i++;
      continue;
    }

    // デリミタ一致？
    if ($delim !== '' && $delim[0] === $ch) {
      $dl = strlen($delim);
      if ($dl === 1 && $ch === ';') {
        // 単一文字デリミタ
        $stmts[] = trim($buf);
        $buf = '';
        $i++;
        continue;
      } else {
        // 複数文字デリミタ（例: $$, // など）
        if ($dl > 1 && substr($sql, $i, $dl) === $delim) {
          $stmts[] = trim($buf);
          $buf = '';
          $i += $dl;
          continue;
        }
      }
    }

    // 改行で行頭更新
    if ($ch === "\n") { $line_start = $i + 1; }

    $buf .= $ch;
    $i++;
  }

  if (trim($buf) !== '') $stmts[] = trim($buf);
  // 空要素除去
  $stmts = array_values(array_filter($stmts, function($s){ return $s !== ''; }));
  return $stmts;
}

/** 実行ユーティリティ（dry-run/トランザクション/エラー時ロールバック） */
function roro_sql_manager_execute_sql($sql, $dry_run = false) {
  global $wpdb;
  $stmts = roro_sql_manager_split_sql($sql);
  if (empty($stmts)) return true;

  if (!$dry_run) { $wpdb->query('START TRANSACTION'); }

  foreach ($stmts as $q) {
    if ($dry_run) {
      roro_sql_manager_log('INFO', 'DRY RUN: ' . $q);
      continue;
    }
    $res = $wpdb->query($q);
    if ($res === false) {
      $err = $wpdb->last_error;
      roro_sql_manager_log('ERROR', 'SQL failed', array('sql' => $q, 'error' => $err));
      $wpdb->query('ROLLBACK');
      return new WP_Error('db_error', $err);
    }
  }

  if (!$dry_run) { $wpdb->query('COMMIT'); }
  return true;
}

/** =========================================================================================
 *  適用・ロールバック（依存解決・循環検出）
 *  ========================================================================================= */
function roro_sql_manager_get_applied() {
  $applied = get_option(RORO_SQL_MANAGER_OPT, array());
  return is_array($applied) ? $applied : array();
}
function roro_sql_manager_set_applied($applied) {
  update_option(RORO_SQL_MANAGER_OPT, array_values(array_unique($applied)), false);
}

/** マイグレーション step 実行（up/down） */
function roro_sql_manager_run_step($step, $direction = 'up', $dry_run = false, $id_for_log = '') {
  $label = strtoupper($direction);
  if (is_string($step)) {
    roro_sql_manager_log('INFO', "$label SQL (string)", array('id'=>$id_for_log));
    return roro_sql_manager_execute_sql($step, $dry_run);
  } elseif (is_callable($step)) {
    if ($dry_run) {
      roro_sql_manager_log('INFO', "DRY RUN: $label callable skipped", array('id'=>$id_for_log));
      return true;
    }
    $ret = call_user_func($step);
    if ($ret === false) return new WP_Error('callback_failed', "Migration $direction callable returned false.");
    return true;
  } elseif (is_array($step)) {
    if (isset($step['file'])) {
      $file = $step['file'];
      if (!file_exists($file) || !is_readable($file)) {
        return new WP_Error('file_not_found', 'Migration file not readable: ' . $file);
      }
      $sql = file_get_contents($file);
      return roro_sql_manager_execute_sql($sql, $dry_run);
    } elseif (isset($step['sql'])) {
      return roro_sql_manager_execute_sql($step['sql'], $dry_run);
    }
  }
  return new WP_Error('invalid_migration', 'Invalid migration step definition: ' . $label);
}

/** 依存関係解決（DFS） + 循環検出 */
function roro_sql_manager_order_with_deps($targets, $all) {
  $ordered = array();
  $vis = array(); // 0:未訪問, 1:訪問中, 2:訪問済
  $stack = array();
  $err  = null;

  $visit = function($id) use (&$visit, &$vis, &$ordered, &$stack, &$all, &$err) {
    if ($err) return; // 既にエラー
    if (!isset($all[$id])) { 
      $err = new WP_Error('missing_dependency', 'Missing migration: '.$id); 
      return; 
    }
    if (isset($vis[$id]) && $vis[$id] === 2) return;
    if (isset($vis[$id]) && $vis[$id] === 1) {
      $stack[] = $id;
      $err = new WP_Error('circular_dependency', 'Circular dependency detected: '.implode(' -> ', $stack));
      return;
    }
    $vis[$id] = 1; // 訪問中
    $stack[]  = $id;
    $deps = isset($all[$id]['depends']) && is_array($all[$id]['depends']) ? $all[$id]['depends'] : array();
    foreach ($deps as $d) { $visit($d); if ($err) return; }
    array_pop($stack);
    $vis[$id] = 2;
    $ordered[] = $id;
  };

  foreach (array_keys($targets) as $id) { $visit($id); if ($err) return $err; }
  // 重複除去して順序保持
  $ordered = array_values(array_unique($ordered));
  return $ordered;
}

/**
 * 適用処理：$selected_ids が空なら未適用すべてを適用
 */
function roro_sql_manager_apply($selected_ids = array(), $dry_run = false) {
  $all     = roro_sql_manager_discover_migrations();
  $applied = roro_sql_manager_get_applied();

  // 未適用のみ対象
  $targets = array();
  foreach ($all as $id => $mig) {
    if (!empty($selected_ids) && !in_array($id, $selected_ids, true)) continue;
    if (in_array($id, $applied, true)) continue;
    $targets[$id] = $mig;
  }

  if (empty($targets)) { 
    roro_sql_manager_log('INFO', 'No pending migrations to apply.');
    return true; 
  }

  // 依存解決
  $ordered = roro_sql_manager_order_with_deps($targets, $all);
  if (is_wp_error($ordered)) return $ordered;

  do_action('roro_sql_before_apply', array_keys($targets));
  foreach ($ordered as $id) {
    if (!isset($targets[$id])) continue; // 依存だけのケース
    $mig = $targets[$id];

    $label = isset($mig['description']) ? $mig['description'] : $id;
    roro_sql_manager_log('INFO', 'Applying ' . $id . ' - ' . $label);

    $up = isset($mig['up']) ? $mig['up'] : null;
    if (!$up) return new WP_Error('invalid_migration', 'Missing up() for ' . $id);

    $result = roro_sql_manager_run_step($up, 'up', $dry_run, $id);
    if (is_wp_error($result)) {
      roro_sql_manager_log('ERROR', 'Apply failed ' . $id, array('error' => $result->get_error_message()));
      return $result;
    }

    if (!$dry_run) {
      $applied[] = $id;
      roro_sql_manager_set_applied($applied);
      roro_sql_manager_log('INFO', 'Applied ' . $id);
    }
  }
  do_action('roro_sql_after_apply', array_keys($targets), $dry_run);

  return true;
}

/**
 * ロールバック処理：選択IDを**適用逆順**で down 実行
 *  - down を持たないマイグレーションはスキップ（エラーではなく WARN）
 *  - 依存関係は簡易に「選択集合の逆順」で処理（高度な逆依存解決は今後拡張）
 */
function roro_sql_manager_rollback($selected_ids = array(), $dry_run = false) {
  if (empty($selected_ids)) {
    return new WP_Error('no_selection', 'ロールバック対象が選択されていません。');
  }
  $all     = roro_sql_manager_discover_migrations();
  $applied = roro_sql_manager_get_applied();

  // 適用済みのみ対象
  $targets = array();
  foreach ($selected_ids as $id) {
    if (!in_array($id, $applied, true)) continue;
    if (!isset($all[$id])) continue;
    $targets[$id] = $all[$id];
  }
  if (empty($targets)) {
    roro_sql_manager_log('INFO', 'No applicable migrations to rollback.');
    return true;
  }

  // 逆順（新しいものから戻す）
  $ordered = array_values(array_reverse(array_keys($targets)));

  do_action('roro_sql_before_rollback', $ordered);
  foreach ($ordered as $id) {
    $mig = $targets[$id];
    $label = isset($mig['description']) ? $mig['description'] : $id;
    roro_sql_manager_log('INFO', 'Rollback ' . $id . ' - ' . $label);

    if (empty($mig['down'])) {
      roro_sql_manager_log('WARN', 'No down() defined. Skipped.', array('id'=>$id));
      // down が無い場合はスキップ（適用状態はそのまま保持）
      continue;
    }

    $result = roro_sql_manager_run_step($mig['down'], 'down', $dry_run, $id);
    if (is_wp_error($result)) {
      roro_sql_manager_log('ERROR', 'Rollback failed ' . $id, array('error' => $result->get_error_message()));
      return $result;
    }

    if (!$dry_run) {
      // 適用リストから除外
      $applied = array_values(array_filter($applied, function($x) use ($id){ return $x !== $id; }));
      roro_sql_manager_set_applied($applied);
      roro_sql_manager_log('INFO', 'Rolled back ' . $id);
    }
  }
  do_action('roro_sql_after_rollback', $ordered, $dry_run);

  return true;
}

/** =========================================================================================
 *  管理画面（ツール > RORO DB Manager）
 *  ========================================================================================= */
add_action('admin_menu', function () {
  add_management_page(
    __('RORO DB Manager', 'roro-assets-sql-manager'),
    __('RORO DB Manager', 'roro-assets-sql-manager'),
    'manage_options',
    'roro-sql-manager',
    'roro_sql_manager_admin_page'
  );
});

function roro_sql_manager_admin_page() {
  if (!current_user_can('manage_options')) {
    wp_die(__('権限がありません。', 'roro-assets-sql-manager'));
  }

  $notice = '';
  $action = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('roro_sql_manager_action', '_roro_sql_nonce');

    $action = isset($_POST['roro_action']) ? sanitize_key($_POST['roro_action']) : '';
    $ids    = isset($_POST['mig']) && is_array($_POST['mig']) ? array_map('sanitize_text_field', $_POST['mig']) : array();
    $dry    = !empty($_POST['dry_run']);

    if ($action === 'apply') {
      $res = roro_sql_manager_apply($ids, $dry);
      if (is_wp_error($res)) {
        $notice = '<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
      } else {
        $msg = $dry ? 'DRY RUN（適用シミュレーション）が完了しました。' : '選択したマイグレーションを適用しました。';
        $notice = '<div class="notice notice-success"><p>'.esc_html($msg).'</p></div>';
      }
    } elseif ($action === 'rollback') {
      $res = roro_sql_manager_rollback($ids, $dry);
      if (is_wp_error($res)) {
        $notice = '<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
      } else {
        $msg = $dry ? 'DRY RUN（ロールバックシミュレーション）が完了しました。' : '選択したマイグレーションをロールバックしました。';
        $notice = '<div class="notice notice-success"><p>'.esc_html($msg).'</p></div>';
      }
    } elseif ($action === 'reset_log') {
      update_option(RORO_SQL_MANAGER_LOG, array(), false);
      $notice = '<div class="notice notice-success"><p>ログをクリアしました。</p></div>';
    } elseif ($action === 'download_csv') {
      roro_sql_manager_download_log_csv();
      exit;
    }
  }

  $all     = roro_sql_manager_discover_migrations();
  $applied = roro_sql_manager_get_applied();
  $logs    = get_option(RORO_SQL_MANAGER_LOG, array());

  // 絞り込み（グループ・テキスト）
  $q_group = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : '';
  $q_text  = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

  $groups = array_unique(array_map(function($m){ return isset($m['group'])?$m['group']:''; }, $all));
  sort($groups);

  // 表示用データフィルタ
  $view = array();
  foreach ($all as $id => $mig) {
    if ($q_group !== '' && (isset($mig['group']) ? $mig['group'] : '') !== $q_group) continue;
    if ($q_text !== '') {
      $hay = $id . ' ' . (isset($mig['description'])?$mig['description']:'');
      if (mb_stripos($hay, $q_text) === false) continue;
    }
    $view[$id] = $mig;
  }

  echo '<div class="wrap"><h1>RORO DB Manager</h1>';
  echo '<p>migrations/ 配下の .sql / .php と、<code>roro_sql_register_migrations</code> フィルタで登録されたマイグレーションを管理します（DDLのハードコーディングなし）。</p>';
  echo $notice;

  // 検索・絞り込み
  echo '<form method="get" style="margin:10px 0;">';
  echo '<input type="hidden" name="page" value="roro-sql-manager" />';
  echo '<label>グループ: <select name="group"><option value="">(すべて)</option>';
  foreach ($groups as $g) {
    $sel = selected($q_group, $g, false);
    echo '<option value="'.esc_attr($g).'" '.$sel.'>'.esc_html($g).'</option>';
  }
  echo '</select></label> ';
  echo '<label>検索: <input type="search" name="s" value="'.esc_attr($q_text).'" /></label> ';
  submit_button('絞り込み', 'secondary', '', false);
  echo '</form>';

  echo '<form method="post">';
  wp_nonce_field('roro_sql_manager_action', '_roro_sql_nonce');

  echo '<h2>マイグレーション一覧</h2>';
  echo '<p>';
  echo '<button type="button" class="button" onclick="roroSqlSelect(true,false)">全選択</button> ';
  echo '<button type="button" class="button" onclick="roroSqlSelect(false,false)">全解除</button> ';
  echo '<button type="button" class="button" onclick="roroSqlSelect(true,true)">未適用のみ選択</button>';
  echo '</p>';

  echo '<table class="widefat striped"><thead><tr>';
  echo '<th>選択</th><th>ID</th><th>説明</th><th>グループ</th><th>状態</th><th>依存</th>';
  echo '</tr></thead><tbody>';
  foreach ($view as $id => $mig) {
    $desc  = isset($mig['description']) ? $mig['description'] : '';
    $group = isset($mig['group']) ? $mig['group'] : '';
    $is_applied = in_array($id, $applied, true);
    $deps = isset($mig['depends']) && is_array($mig['depends']) ? $mig['depends'] : array();
    echo '<tr>';
    echo '<td><input type="checkbox" class="roro-sql-item" name="mig[]" value="'.esc_attr($id).'" '.($is_applied && empty($mig['down']) && $action!=='rollback' ? 'disabled' : '').'></td>';
    echo '<td><code>'.esc_html($id).'</code></td>';
    echo '<td>'.esc_html($desc).'</td>';
    echo '<td>'.esc_html($group).'</td>';
    echo '<td>'.($is_applied ? '<span style="color:green">適用済</span>' : '<span>未適用</span>').'</td>';
    echo '<td>'.esc_html(implode(', ', $deps)).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';

  echo '<p><label><input type="checkbox" name="dry_run" value="1"> DRY RUN（SQLは実行せずログに出力）</label></p>';

  echo '<p style="display:flex;gap:8px;align-items:center;">';
  echo '<button type="submit" name="roro_action" value="apply" class="button button-primary">選択を適用</button>';
  echo '<button type="submit" name="roro_action" value="rollback" class="button">選択をロールバック</button>';
  echo '</p>';

  echo '</form>';

  // ログ表示＆操作
  echo '<h2>直近ログ</h2>';
  echo '<form method="post" style="margin-bottom:8px;">';
  wp_nonce_field('roro_sql_manager_action', '_roro_sql_nonce');
  echo '<button type="submit" name="roro_action" value="reset_log" class="button">ログをクリア</button> ';
  echo '<button type="submit" name="roro_action" value="download_csv" class="button">CSVダウンロード</button>';
  echo '</form>';

  echo '<div style="max-height:320px; overflow:auto; background:#fff; border:1px solid #ddd; padding:8px;">';
  foreach (array_reverse($logs) as $row) {
    $ctx = !empty($row['ctx']) ? ' ' . esc_html(wp_json_encode($row['ctx'])) : '';
    echo '<div><code>'.esc_html($row['time']).'</code> ['.esc_html($row['level']).'] '.esc_html($row['msg']).$ctx.'</div>';
  }
  echo '</div>';

  // JS（チェックボックス簡易操作）
  echo '<script>
    function roroSqlSelect(state, pendingOnly){
      var it = document.querySelectorAll(".roro-sql-item");
      it.forEach(function(cb){
        if (cb.disabled) return;
        if (pendingOnly){
          // 行内の「状態」を見て「未適用」のみ選択
          var tr = cb.closest("tr");
          if (tr && tr.querySelector("td:nth-child(5) span") && tr.querySelector("td:nth-child(5) span").textContent === "未適用"){
            cb.checked = state;
          } else {
            cb.checked = false;
          }
        } else {
          cb.checked = state;
        }
      });
    }
  </script>';

  echo '</div>';
}

/** ログCSVダウンロード */
function roro_sql_manager_download_log_csv(){
  if (!current_user_can('manage_options')) return;
  $logs = get_option(RORO_SQL_MANAGER_LOG, array());
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="roro_sql_log.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, array('time','level','message','context_json'));
  foreach ($logs as $row) {
    fputcsv($out, array($row['time'], $row['level'], $row['msg'], json_encode($row['ctx'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
  }
  fclose($out);
}

/** =========================================================================================
 *  REST: /wp-json/roro/v1/db/...
 *  - GET /migrations : 一覧・適用済み・ログ末尾
 *  - POST /apply     : IDs,dry_run で適用
 *  - POST /rollback  : IDs,dry_run でロールバック
 *  ※ すべて manage_options 権限が必要
 *  ========================================================================================= */
add_action('rest_api_init', function () {
  register_rest_route('roro/v1', '/db/migrations', array(
    'methods'  => 'GET',
    'callback' => function (WP_REST_Request $req) {
      if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
      }
      return array(
        'applied'  => roro_sql_manager_get_applied(),
        'all'      => array_values(roro_sql_manager_discover_migrations()),
        'log_tail' => get_option(RORO_SQL_MANAGER_LOG, array())
      );
    },
    'permission_callback' => '__return_true',
  ));

  register_rest_route('roro/v1', '/db/apply', array(
    'methods'  => 'POST',
    'callback' => function (WP_REST_Request $req) {
      if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
      }
      $ids = $req->get_param('ids');
      $dry = (bool)$req->get_param('dry_run');
      $ids = is_array($ids) ? array_map('sanitize_text_field', $ids) : array();
      $res = roro_sql_manager_apply($ids, $dry);
      if (is_wp_error($res)) {
        return new WP_REST_Response(array('ok'=>false,'error'=>$res->get_error_message()), 400);
      }
      return new WP_REST_Response(array('ok'=>true), 200);
    },
    'permission_callback' => '__return_true',
  ));

  register_rest_route('roro/v1', '/db/rollback', array(
    'methods'  => 'POST',
    'callback' => function (WP_REST_Request $req) {
      if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
      }
      $ids = $req->get_param('ids');
      $dry = (bool)$req->get_param('dry_run');
      $ids = is_array($ids) ? array_map('sanitize_text_field', $ids) : array();
      $res = roro_sql_manager_rollback($ids, $dry);
      if (is_wp_error($res)) {
        return new WP_REST_Response(array('ok'=>false,'error'=>$res->get_error_message()), 400);
      }
      return new WP_REST_Response(array('ok'=>true), 200);
    },
    'permission_callback' => '__return_true',
  ));
});
