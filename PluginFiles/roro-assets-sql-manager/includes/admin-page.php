<?php
/**
 * Administrative user interface for the RORO Assets SQL Manager.
 *
 * This module registers a management page under Tools → RORO DB Manager and
 * implements the HTML form, filtering and action handlers needed to apply
 * or roll back migrations. It also exposes a CSV download of the log and
 * provides convenience buttons for selecting migrations. By isolating the
 * admin UI into its own file we keep presentation logic separate from the
 * core migration functions and make it easier to modify or replace the
 * interface in future.
 *
 * @package RoroAssetsSQLManager
 */

// Protect against direct access.
if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 *  Menu registration
 */

/**
 * Add the RORO DB Manager page to the Tools menu.
 *
 * This uses the `manage_options` capability to restrict access to
 * administrators. The slug `roro-sql-manager` is retained for backwards
 * compatibility with the original implementation.
 */
function roro_sql_manager_admin_menu() {
    add_management_page(
        __('RORO DB Manager', 'roro-assets-sql-manager'),
        __('RORO DB Manager', 'roro-assets-sql-manager'),
        'manage_options',
        'roro-sql-manager',
        'roro_sql_manager_admin_page'
    );
}
add_action('admin_menu', 'roro_sql_manager_admin_menu');

/* -------------------------------------------------------------------------
 *  Page rendering
 */

/**
 * Render the admin page and handle form submissions.
 *
 * The page supports three primary actions triggered via POST: applying
 * migrations, rolling them back and clearing or downloading the log. All
 * actions require a nonce for CSRF protection. When a dry run is requested
 * the SQL is not executed and only the log is updated.
 */
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
                $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
            } else {
                $msg    = $dry ? 'DRY RUN（適用シミュレーション）が完了しました。' : '選択したマイグレーションを適用しました。';
                $notice = '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
            }
        } elseif ($action === 'rollback') {
            $res = roro_sql_manager_rollback($ids, $dry);
            if (is_wp_error($res)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html($res->get_error_message()) . '</p></div>';
            } else {
                $msg    = $dry ? 'DRY RUN（ロールバックシミュレーション）が完了しました。' : '選択したマイグレーションをロールバックしました。';
                $notice = '<div class="notice notice-success"><p>' . esc_html($msg) . '</p></div>';
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
    // Filtering options.
    $q_group = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : '';
    $q_text  = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $groups  = array_unique(array_map(function ($m) {
        return isset($m['group']) ? $m['group'] : '';
    }, $all));
    sort($groups);
    $view = array();
    foreach ($all as $id => $mig) {
        if ($q_group !== '' && (isset($mig['group']) ? $mig['group'] : '') !== $q_group) {
            continue;
        }
        if ($q_text !== '') {
            $hay = $id . ' ' . (isset($mig['description']) ? $mig['description'] : '');
            if (mb_stripos($hay, $q_text) === false) {
                continue;
            }
        }
        $view[$id] = $mig;
    }
    echo '<div class="wrap"><h1>RORO DB Manager</h1>';
    echo '<p>migrations/ 配下の .sql / .php と、<code>roro_sql_register_migrations</code> フィルタで登録されたマイグレーションを管理します（DDLのハードコーディングなし）。</p>';
    echo $notice;
    // Search & filter form.
    echo '<form method="get" style="margin:10px 0;">';
    echo '<input type="hidden" name="page" value="roro-sql-manager" />';
    echo '<label>グループ: <select name="group"><option value="">(すべて)</option>';
    foreach ($groups as $g) {
        $sel = selected($q_group, $g, false);
        echo '<option value="' . esc_attr($g) . '" ' . $sel . '>' . esc_html($g) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>検索: <input type="search" name="s" value="' . esc_attr($q_text) . '" /></label> ';
    submit_button('絞り込み', 'secondary', '', false);
    echo '</form>';
    // Apply/rollback form.
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
        $disabled = ($is_applied && empty($mig['down']) && $action !== 'rollback') ? 'disabled' : '';
        echo '<td><input type="checkbox" class="roro-sql-item" name="mig[]" value="' . esc_attr($id) . '" ' . $disabled . '></td>';
        echo '<td><code>' . esc_html($id) . '</code></td>';
        echo '<td>' . esc_html($desc) . '</td>';
        echo '<td>' . esc_html($group) . '</td>';
        echo '<td>' . ($is_applied ? '<span style="color:green">適用済</span>' : '<span>未適用</span>') . '</td>';
        echo '<td>' . esc_html(implode(', ', $deps)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><label><input type="checkbox" name="dry_run" value="1"> DRY RUN（SQLは実行せずログに出力）</label></p>';
    echo '<p style="display:flex;gap:8px;align-items:center;">';
    echo '<button type="submit" name="roro_action" value="apply" class="button button-primary">選択を適用</button>';
    echo '<button type="submit" name="roro_action" value="rollback" class="button">選択をロールバック</button>';
    echo '</p>';
    echo '</form>';
    // Log display & controls.
    echo '<h2>直近ログ</h2>';
    echo '<form method="post" style="margin-bottom:8px;">';
    wp_nonce_field('roro_sql_manager_action', '_roro_sql_nonce');
    echo '<button type="submit" name="roro_action" value="reset_log" class="button">ログをクリア</button> ';
    echo '<button type="submit" name="roro_action" value="download_csv" class="button">CSVダウンロード</button>';
    echo '</form>';
    echo '<div style="max-height:320px; overflow:auto; background:#fff; border:1px solid #ddd; padding:8px;">';
    foreach (array_reverse($logs) as $row) {
        $ctx = !empty($row['ctx']) ? ' ' . esc_html(wp_json_encode($row['ctx'])) : '';
        echo '<div><code>' . esc_html($row['time']) . '</code> [' . esc_html($row['level']) . '] ' . esc_html($row['msg']) . $ctx . '</div>';
    }
    echo '</div>';
    // JavaScript helpers for selecting checkboxes.
    echo '<script>function roroSqlSelect(state,pendingOnly){var it=document.querySelectorAll(".roro-sql-item");it.forEach(function(cb){if(cb.disabled)return;if(pendingOnly){var tr=cb.closest("tr");if(tr&&tr.querySelector("td:nth-child(5) span")&&tr.querySelector("td:nth-child(5) span").textContent==="未適用"){cb.checked=state;}else{cb.checked=false;}}else{cb.checked=state;}});}</script>';
    echo '</div>';
}

/* -------------------------------------------------------------------------
 *  Log export
 */

/**
 * Stream the log to the browser as a CSV file.
 *
 * This helper is invoked when the user clicks the CSV download button. It
 * outputs a simple CSV with four columns: time, level, message and a JSON
 * encoded context. The function respects user capabilities and returns
 * early if the current user lacks the `manage_options` capability.
 *
 * @return void
 */
function roro_sql_manager_download_log_csv() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $logs = get_option(RORO_SQL_MANAGER_LOG, array());
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="roro_sql_log.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('time', 'level', 'message', 'context_json'));
    foreach ($logs as $row) {
        fputcsv($out, array($row['time'], $row['level'], $row['msg'], json_encode($row['ctx'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }
    fclose($out);
}
