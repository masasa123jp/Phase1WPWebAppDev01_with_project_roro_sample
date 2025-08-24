<?php
/**
 * Plugin Name: RORO Assets SQL Manager
 * Description: ROROプロジェクトのDBマイグレーションを管理（DDLのハードコーディングなし）。migrations/ のSQL・PHP、またはフィルタ登録から適用。
 * Version: 1.2.0
 * Author: RORO Dev Team
 * Text Domain: roro-assets-sql-manager
 */

if (!defined('ABSPATH')) exit;

define('RORO_SQL_MANAGER_SLUG', 'roro-assets-sql-manager');
define('RORO_SQL_MANAGER_VER',  '1.2.0');
define('RORO_SQL_MANAGER_OPT',  'roro_sql_applied'); // 適用済みマイグレーションID配列を格納
define('RORO_SQL_MANAGER_LOG',  'roro_sql_log');     // 直近のログ（配列）を保存

// 便利関数
function roro_sql_manager_dir() { return plugin_dir_path(__FILE__); }
function roro_sql_manager_url() { return plugin_dir_url(__FILE__); }
function roro_sql_manager_now() { return gmdate('Y-m-d H:i:s'); }

// 有効化時：オプション初期化
register_activation_hook(__FILE__, function () {
  if (get_option(RORO_SQL_MANAGER_OPT) === false) {
    add_option(RORO_SQL_MANAGER_OPT, array(), false);
  }
  if (get_option(RORO_SQL_MANAGER_LOG) === false) {
    add_option(RORO_SQL_MANAGER_LOG, array(), false);
  }
});

// ログ保存（最新100件）
function roro_sql_manager_log($level, $message, $context = array()) {
  $log = get_option(RORO_SQL_MANAGER_LOG, array());
  $log[] = array(
    'time' => roro_sql_manager_now(),
    'level'=> strtoupper($level),
    'msg'  => $message,
    'ctx'  => $context
  );
  if (count($log) > 100) { $log = array_slice($log, -100); }
  update_option(RORO_SQL_MANAGER_LOG, $log, false);
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[RORO SQL] ' . strtoupper($level) . ': ' . $message);
  }
}

// --- マイグレーション探索（migrations/配下 + フィルタ 'roro_sql_register_migrations'） -----------------

/**
 * マイグレーションの基本形：
 * [
 *   'id'          => '20250824001_create_advice_table', // 一意なID（昇順適用を推奨）
 *   'description' => 'Create advice table',
 *   'group'       => 'core',                            // 任意グループ
 *   'depends'     => ['20250823001_init'],              // 任意依存
 *   'up'          => (string|callable) SQLまたはコールバック、もしくは ['file' => '/path/to.sql']
 *   'down'        => (省略可) ロールバック用
 * ]
 */
function roro_sql_manager_discover_migrations() {
  $migrations = array();

  // 1) フォルダから読み込み（/migrations/*.sql or *.php）
  $dir = trailingslashit(roro_sql_manager_dir()) . 'migrations';
  if (is_dir($dir)) {
    // .sql は単純SQL（upのみ）
    foreach (glob($dir . '/*.sql') as $file) {
      $id = basename($file, '.sql');
      $migrations[$id] = array(
        'id'          => $id,
        'description' => 'SQL file: ' . basename($file),
        'group'       => 'fs',
        'depends'     => array(),
        'up'          => array('file' => $file),
      );
    }
    // .php は return 形式 or include 実行で配列（複数定義も可）
    foreach (glob($dir . '/*.php') as $file) {
      $res = include $file; // 返値にマイグレーション定義（単体 or 配列）を期待
      if (is_array($res) && isset($res['id'])) {
        $migrations[$res['id']] = $res;
      } elseif (is_array($res)) {
        // 複数配列想定
        foreach ($res as $mig) {
          if (is_array($mig) && isset($mig['id'])) {
            $migrations[$mig['id']] = $mig;
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

// --- SQL実行ユーティリティ -------------------------------------------------------------

// セミコロンで適当に分割せず、簡易パーサでステートメント分割（文字列/コメント内の;は無視）
function roro_sql_manager_split_sql($sql) {
  $len = strlen($sql);
  $stmts = array();
  $buf = '';
  $in_str = false;
  $str_ch = '';
  $in_sl_comment = false; // -- ... \n
  $in_ml_comment = false; // /* ... */
  for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $n2 = ($i+1 < $len) ? ($ch . $sql[$i+1]) : '';

    // コメント終端
    if ($in_sl_comment) {
      $buf .= $ch;
      if ($ch === "\n") { $in_sl_comment = false; }
      continue;
    }
    if ($in_ml_comment) {
      $buf .= $ch;
      if ($n2 === '*/') { $buf .= $sql[++$i]; $in_ml_comment = false; }
      continue;
    }

    // 文字列リテラル終端
    if ($in_str) {
      $buf .= $ch;
      if ($ch === $str_ch) {
        // エスケープ対応（\' \" ``）
        $escaped = ($i > 0 && $sql[$i-1] === '\\');
        if (!$escaped) { $in_str = false; $str_ch = ''; }
      }
      continue;
    }

    // コメント開始？
    if ($n2 === '--' || $n2 === '# ') { $in_sl_comment = true; $buf .= $n2; $i++; continue; }
    if ($n2 === '/*') { $in_ml_comment = true; $buf .= $n2; $i++; continue; }

    // 文字列開始？
    if ($ch === "'" || $ch === '"' || $ch === '`') {
      $in_str = true; $str_ch = $ch; $buf .= $ch; continue;
    }

    // ステートメント切り出し
    if ($ch === ';') {
      $stmts[] = trim($buf);
      $buf = '';
      continue;
    }

    $buf .= $ch;
  }
  if (trim($buf) !== '') $stmts[] = trim($buf);
  return $stmts;
}

function roro_sql_manager_execute_sql($sql, $dry_run = false) {
  global $wpdb;
  $stmts = roro_sql_manager_split_sql($sql);
  if (empty($stmts)) return true;

  // InnoDBならトランザクション
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

// --- マイグレーション適用 --------------------------------------------------------------

function roro_sql_manager_get_applied() {
  $applied = get_option(RORO_SQL_MANAGER_OPT, array());
  return is_array($applied) ? $applied : array();
}

function roro_sql_manager_set_applied($applied) {
  update_option(RORO_SQL_MANAGER_OPT, array_values(array_unique($applied)), false);
}

/**
 * 適用処理：$selected_ids が空なら未適用すべてを適用
 */
function roro_sql_manager_apply($selected_ids = array(), $dry_run = false) {
  $all = roro_sql_manager_discover_migrations();
  $applied = roro_sql_manager_get_applied();

  // 未適用のみ対象
  $targets = array();
  foreach ($all as $id => $mig) {
    if (!empty($selected_ids) && !in_array($id, $selected_ids, true)) continue;
    if (in_array($id, $applied, true)) continue;
    $targets[$id] = $mig;
  }

  // 依存解決（非常に簡易：dependsにあるものを先に）
  $ordered = array();
  $visited = array();
  $visit = function($id) use (&$visit, &$visited, &$ordered, $all) {
    if (isset($visited[$id])) return;
    $visited[$id] = true;
    $deps = isset($all[$id]['depends']) && is_array($all[$id]['depends']) ? $all[$id]['depends'] : array();
    foreach ($deps as $d) {
      if (isset($all[$d])) { $visit($d); }
    }
    $ordered[] = $id;
  };
  foreach (array_keys($targets) as $id) { $visit($id); }

  foreach ($ordered as $id) {
    if (!isset($targets[$id])) continue; // 依存だけで自分が未選択
    $mig = $targets[$id];

    $label = isset($mig['description']) ? $mig['description'] : $id;
    roro_sql_manager_log('INFO', 'Applying ' . $id . ' - ' . $label);

    $up = isset($mig['up']) ? $mig['up'] : null;
    $result = true;

    if (is_string($up)) {
      $result = roro_sql_manager_execute_sql($up, $dry_run);
    } elseif (is_callable($up)) {
      if ($dry_run) {
        roro_sql_manager_log('INFO', 'DRY RUN: callable up() skipped', array('id' => $id));
      } else {
        $result = call_user_func($up);
        if ($result === false) $result = new WP_Error('callback_failed', 'Migration callable returned false.');
      }
    } elseif (is_array($up) && isset($up['file'])) {
      $file = $up['file'];
      if (!file_exists($file) || !is_readable($file)) {
        $result = new WP_Error('file_not_found', 'Migration file not readable: ' . $file);
      } else {
        $sql = file_get_contents($file);
        $result = roro_sql_manager_execute_sql($sql, $dry_run);
      }
    } else {
      $result = new WP_Error('invalid_migration', 'Invalid migration up() definition for ' . $id);
    }

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

  return true;
}

// --- 管理画面（ツール > RORO DB） ------------------------------------------------------

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
        $msg = $dry ? 'DRY RUN 完了' : '適用完了';
        $notice = '<div class="notice notice-success"><p>'.esc_html($msg).'</p></div>';
      }
    } elseif ($action === 'reset_log') {
      update_option(RORO_SQL_MANAGER_LOG, array(), false);
      $notice = '<div class="notice notice-success"><p>ログをクリアしました。</p></div>';
    }
  }

  $all     = roro_sql_manager_discover_migrations();
  $applied = roro_sql_manager_get_applied();
  $logs    = get_option(RORO_SQL_MANAGER_LOG, array());

  echo '<div class="wrap"><h1>RORO DB Manager</h1>';
  echo '<p>migrations/ 配下の .sql / .php と、<code>roro_sql_register_migrations</code> フィルタで登録されたマイグレーションを管理します（DDLのハードコーディングなし）。</p>';
  echo $notice;

  echo '<form method="post">';
  wp_nonce_field('roro_sql_manager_action', '_roro_sql_nonce');

  echo '<h2>マイグレーション一覧</h2>';
  echo '<table class="widefat striped"><thead><tr><th>選択</th><th>ID</th><th>説明</th><th>グループ</th><th>状態</th></tr></thead><tbody>';
  foreach ($all as $id => $mig) {
    $desc  = isset($mig['description']) ? $mig['description'] : '';
    $group = isset($mig['group']) ? $mig['group'] : '';
    $is_applied = in_array($id, $applied, true);
    echo '<tr>';
    echo '<td><input type="checkbox" name="mig[]" value="'.esc_attr($id).'" '.($is_applied ? 'disabled' : '').'></td>';
    echo '<td><code>'.esc_html($id).'</code></td>';
    echo '<td>'.esc_html($desc).'</td>';
    echo '<td>'.esc_html($group).'</td>';
    echo '<td>'.($is_applied ? '<span style="color:green">適用済</span>' : '<span>未適用</span>').'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';

  echo '<p><label><input type="checkbox" name="dry_run" value="1"> DRY RUN（SQLを実行せずログに出力）</label></p>';

  echo '<p>';
  submit_button('選択を適用', 'primary', 'roro_action_apply', false);
  echo '<input type="hidden" name="roro_action" value="apply">';
  echo '</p>';
  echo '</form>';

  echo '<h2>直近ログ</h2>';
  echo '<form method="post" style="margin-bottom:1em;">';
  wp_nonce_field('roro_sql_manager_action', '_roro_sql_nonce');
  echo '<input type="hidden" name="roro_action" value="reset_log">';
  submit_button('ログをクリア', 'secondary', 'reset_log', false);
  echo '</form>';
  echo '<div style="max-height:260px; overflow:auto; background:#fff; border:1px solid #ddd; padding:8px;">';
  foreach (array_reverse($logs) as $row) {
    echo '<div><code>'.esc_html($row['time']).'</code> ['.esc_html($row['level']).'] '.esc_html($row['msg']).'</div>';
  }
  echo '</div>';

  echo '</div>';
}

// --- REST: /wp-json/roro/v1/db/migrations -----------------------------------------------

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
});
