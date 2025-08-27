<?php
/**
 * RORO Integrated DB
 * - 管理画面「DB Setup」サブメニューを追加
 * - DDL/SEED SQL を安全に実行
 * - wp_ → $wpdb->prefix に置換
 * - CREATE TABLE は dbDelta() を利用
 * - 文字列分割・正規化ユーティリティを完備
 */
declare(strict_types=1);

namespace {
defined('ABSPATH') || exit;

final class RORO_DB {
    private const NONCE_ACTION = 'roro_db_setup';
    private const NONCE_NAME   = '_roro_db_nonce';
    private const PAGE_SLUG    = 'roro-db-setup';

    public static function init(): void {
        if (!is_admin()) return;
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_post_roro_db_ddl',  [self::class, 'handle_ddl']);
        add_action('admin_post_roro_db_seed', [self::class, 'handle_seed']);
    }

    /** 管理メニュー追加 */
    public static function add_menu(): void {
        add_submenu_page(
            'roro-core-wp',
            'RORO DB Setup',
            'DB Setup',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page'],
        );
    }

    /** ページ描画 */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission.');
        }

        echo '<div class="wrap"><h1>RORO DB Setup</h1>';
        self::render_status_box();

        echo '<h2>Run DDL (dbDelta for CREATE TABLE)</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<input type="hidden" name="action" value="roro_db_ddl" />';
        echo '<p><label>SQL file(s) directory (schema): <input type="text" name="dir" value="wp-content/uploads/roro/schema" size="60"></label></p>';
        submit_button('Run DDL');
        echo '</form>';

        echo '<hr/>';

        echo '<h2>Run SEED (INSERT etc.)</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<input type="hidden" name="action" value="roro_db_seed" />';
        echo '<p><label>SQL file(s) directory (seed): <input type="text" name="dir" value="wp-content/uploads/roro/seed" size="60"></label></p>';
        submit_button('Run SEED');
        echo '</form>';

        echo '</div>';
    }

    /** 現況表示 */
    private static function render_status_box(): void {
        global $wpdb;
        echo '<div class="notice notice-info" style="padding:12px;margin-top:10px;">';
        echo '<p><strong>DB:</strong> ' . esc_html($wpdb->dbname ?? '') . '</p>';
        echo '<p><strong>Prefix:</strong> ' . esc_html($wpdb->prefix) . '</p>';
        echo '</div>';
    }

    /** DDL 実行（dbDelta対応） */
    public static function handle_ddl(): void {
        if (!current_user_can('manage_options')) wp_die('No permission.');
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) wp_die('Invalid nonce.');

        $dir = sanitize_text_field((string)($_POST['dir'] ?? ''));
        if ($dir === '') wp_die('Directory required.');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $files = self::collect_sql_files($dir);
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) continue;

            $sql = self::normalize_prefix($sql);
            $stmts = self::split_sql_statements($sql);

            foreach ($stmts as $stmt) {
                if (preg_match('/^\s*CREATE\s+TABLE/i', $stmt)) {
                    dbDelta($stmt);
                } else {
                    global $wpdb;
                    $wpdb->query($stmt);
                }
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /** SEED 実行（INSERT 等） */
    public static function handle_seed(): void {
        if (!current_user_can('manage_options')) wp_die('No permission.');
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) wp_die('Invalid nonce.');

        $dir = sanitize_text_field((string)($_POST['dir'] ?? ''));
        if ($dir === '') wp_die('Directory required.');

        $files = self::collect_sql_files($dir);
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) continue;

            $sql = self::normalize_prefix($sql);
            $stmts = self::split_sql_statements($sql);

            global $wpdb;
            foreach ($stmts as $stmt) {
                $wpdb->query($stmt);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /** ディレクトリ配下の .sql を再帰収集 */
    private static function collect_sql_files(string $dir): array {
        $abs = self::to_abs_path($dir);
        if (!is_dir($abs)) return [];
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($rii as $f) {
            if ($f instanceof \SplFileInfo && $f->isFile() && strtolower($f->getExtension()) === 'sql') {
                $files[] = $f->getPathname();
            }
        }
        sort($files, SORT_NATURAL);
        return $files;
    }

    /** wp_ → $wpdb->prefix 置換 */
    private static function normalize_prefix(string $sql): string {
        global $wpdb;
        $prefix = $wpdb->prefix;
        // コメントを少し雑に排除（-- ～ 行末, /* */）
        $sql = preg_replace('~--.*$~m', '', $sql);
        $sql = preg_replace('~/\*.*?\*/~s', '', $sql);

        // プレフィックス置換
        $sql = str_replace(['`wp_', ' wp_'], ['`'.$prefix, ' '.$prefix], $sql);
        return $sql;
    }

    /** 複数ステートメントに分割（; 改行で区切る簡易実装） */
    private static function split_sql_statements(string $sql): array {
        $parts = preg_split('/;[\r\n]+/u', $sql);
        $parts = array_map('trim', (array)$parts);
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }

    /** 相対→絶対パス */
    private static function to_abs_path(string $path): string {
        if (preg_match('~^https?://~i', $path)) return $path;
        if ($path[0] === '/' || preg_match('~^[A-Za-z]:[\\\\/]~', $path)) return $path;
        return trailingslashit(ABSPATH) . ltrim($path, '/');
    }
}
}
