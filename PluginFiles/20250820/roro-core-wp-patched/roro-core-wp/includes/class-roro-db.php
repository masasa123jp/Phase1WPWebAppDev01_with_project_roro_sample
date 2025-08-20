<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Roro 用の SQL インポートロジックと簡易ロガー。
 *
 * WordPress 上で安全に SQL ファイルを分割し順次実行します。
 */

class Roro_DB_Logger {
    private $fp;
    public function __construct($path) {
        $this->fp = fopen($path, 'a');
    }
    public function __destruct() {
        if ($this->fp) {
            fclose($this->fp);
        }
    }
    public function write($level, $msg) {
        $line = sprintf('[%s] [%s] %s\n', date('Y-m-d H:i:s'), $level, $msg);
        if ($this->fp) {
            fwrite($this->fp, $line);
        }
        error_log('[RoroDB] ' . $line);
    }
    public function info($m) { $this->write('INFO', $m); }
    public function warn($m) { $this->write('WARN', $m); }
    public function error($m) { $this->write('ERROR', $m); }
}

class Roro_DB {
    /**
     * 指定された順序で /assets/sql のファイルをインポートする。
     * 不足しているファイルは無視し、残りを末尾に追加する。
     */
    public static function import_files_in_order(array $preferredOrder, Roro_DB_Logger $log, $use_transactions = true) {
        $files = self::list_sql_files();
        $map = [];
        foreach ($files as $f) {
            $map[basename($f)] = $f;
        }
        $queue = [];
        foreach ($preferredOrder as $name) {
            if (isset($map[$name])) {
                $queue[] = $map[$name];
            }
        }
        foreach ($files as $f) {
            if (!in_array($f, $queue, true)) {
                $queue[] = $f;
            }
        }
        self::import_files($queue, $log, false, $use_transactions);
    }

    /**
     * assets/sql 配下の .sql ファイルを列挙する。
     */
    public static function list_sql_files() {
        if (!is_dir(RORO_DB_SQL_DIR)) {
            return [];
        }
        $files = glob(RORO_DB_SQL_DIR . '*.sql');
        sort($files, SORT_NATURAL);
        return $files ?: [];
    }

    /**
     * 指定されたファイル群を順に実行する。
     *
     * @param string[] $paths 実行するSQLファイルの絶対パス
     * @param Roro_DB_Logger $log ログ出力用
     * @param bool $dry_run ドライランの場合は実行をスキップ
     * @param bool $use_transactions トランザクション使用フラグ
     */
    public static function import_files(array $paths, Roro_DB_Logger $log, $dry_run = false, $use_transactions = true) {
        global $wpdb;
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $log->warn("Not found: $path");
                continue;
            }
            $log->info("=== Import start: " . basename($path) . " ===");
            $sql = file_get_contents($path);
            if ($sql === false) {
                $log->error("Read failed: $path");
                continue;
            }
            $sql = self::normalize_sql($sql, $wpdb);
            $stmts = self::split_sql_statements($sql);
            $log->info(sprintf('Statements: %d', count($stmts)));
            if ($dry_run) {
                $log->info('Dry-run mode: skipped execution');
                continue;
            }
            if ($use_transactions) {
                $wpdb->query('START TRANSACTION');
            }
            $ok = true;
            $i = 0;
            foreach ($stmts as $s) {
                $i++;
                $trim = trim($s);
                if ($trim === '') {
                    continue;
                }
                $r = $wpdb->query($trim);
                if ($r === false) {
                    $ok = false;
                    $log->error("Failed at #$i: " . substr($trim, 0, 200) . ' ...');
                    $log->error('wpdb->last_error: ' . $wpdb->last_error);
                    break;
                }
            }
            if ($use_transactions) {
                if ($ok) {
                    $wpdb->query('COMMIT');
                    $log->info('COMMIT');
                } else {
                    $wpdb->query('ROLLBACK');
                    $log->warn('ROLLBACK');
                }
            }
            $log->info("=== Import end: " . basename($path) . " ===");
        }
    }

    /**
     * SQL ファイルに対する前処理。
     * - UTF-8 BOM を除去
     * - コメントを除去
     * - WordPress テーブル接頭辞への置換
     */
    public static function normalize_sql($sql, $wpdb) {
        // BOM 除去
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
            $sql = substr($sql, 3);
        }
        // 行頭コメント -- or #
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*#.*$/m', '', $sql);
        // ブロックコメント /* */
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
        // テーブルプレフィックス置換
        $sql = str_replace(['{{prefix}}', '__PREFIX__'], $wpdb->prefix, $sql);
        return $sql;
    }

    /**
     * SQLを安全に分割します。基本のセミコロン区切りに加えて、
     * DELIMITER ディレクティブが存在する場合には指定の区切り文字で分割します。
     *
     * トリガやストアドプロシージャなどDELIMITERを使うDDLに対応するため、
     * 行単位で DELIMITER を検出し、現在のデリミタを切り替えて分割します。
     *
     * @param string $sql 元のSQLテキスト
     * @return string[] 分割された各ステートメント
     */
    public static function split_sql_statements($sql) {
        $stmts = [];
        // デリミタを処理しやすいように改行単位で処理
        $lines = preg_split("/(\r\n|\n|\r)/", $sql);
        $buf = '';
        $delimiter = ';';
        $dl = strlen($delimiter);
        foreach ($lines as $line) {
            $trim = ltrim($line);
            // DELIMITER 指示行の検出（行頭）
            if (stripos($trim, 'DELIMITER ') === 0) {
                // バッファに残っているステートメントを現在のデリミタで分割
                if (trim($buf) !== '') {
                    $stmts = array_merge($stmts, self::split_sql_by_delimiter($buf, $delimiter));
                    $buf = '';
                }
                // 新しいデリミタに更新
                $parts = preg_split('/\s+/', $trim, 2);
                $delimiter = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : ';';
                $dl = strlen($delimiter);
                continue; // DELIMITER行自体は出力しない
            }
            $buf .= $line . "\n";
        }
        // 残ったバッファを最終分割
        if (trim($buf) !== '') {
            $stmts = array_merge($stmts, self::split_sql_by_delimiter($buf, $delimiter));
        }
        // 空行や空白のみの要素を除外し、トリムして返却
        $stmts = array_map('trim', $stmts);
        return array_values(array_filter($stmts, function($s) { return $s !== ''; }));
    }

    /**
     * 与えられたSQL文字列を指定されたデリミタで分割します。
     * クォート内のデリミタは無視されます。
     *
     * @param string $sql SQL文字列
     * @param string $delimiter 区切り文字
     * @return string[] 分割されたSQL
     */
    private static function split_sql_by_delimiter($sql, $delimiter) {
        $out = [];
        $len = strlen($sql);
        $buf = '';
        $inSingle = false;
        $inDouble = false;
        $dl = strlen($delimiter);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            // シングル/ダブルクォートのトグル（エスケープ処理含む）
            if ($ch === "'" && !$inDouble) {
                $backslashes = 0;
                for ($j = $i - 1; $j >= 0 && $sql[$j] === '\\'; $j--) {
                    $backslashes++;
                }
                if ($backslashes % 2 === 0) {
                    $inSingle = !$inSingle;
                }
            } elseif ($ch === '"' && !$inSingle) {
                $backslashes = 0;
                for ($j = $i - 1; $j >= 0 && $sql[$j] === '\\'; $j--) {
                    $backslashes++;
                }
                if ($backslashes % 2 === 0) {
                    $inDouble = !$inDouble;
                }
            }
            // デリミタ検出（クォート外のみ）
            if (!$inSingle && !$inDouble && $delimiter !== '' && substr($sql, $i, $dl) === $delimiter) {
                $out[] = $buf;
                $buf = '';
                $i += $dl - 1; // デリミタ文字分をスキップ
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = $buf;
        }
        return $out;
    }

    /**
     * 新しいロガーを生成する。
     */
    public static function make_logger() {
        $ts = date('Ymd-His');
        $path = RORO_DB_LOG_DIR . "db_import_{$ts}.log";
        return new Roro_DB_Logger($path);
    }
}