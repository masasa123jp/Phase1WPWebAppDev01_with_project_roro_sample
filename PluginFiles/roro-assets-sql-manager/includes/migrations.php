<?php
/**
 * Migration discovery and execution logic for the RORO Assets SQL Manager.
 *
 * This module encapsulates the functions responsible for locating migration
 * definitions on disk, splitting SQL scripts into statements, executing those
 * statements safely with optional dry‑run support, and applying or rolling
 * back migrations in the correct order. By grouping related concerns into
 * one file we maintain a clear separation between the data layer and
 * presentation (admin UI and REST API) while preserving the original
 * function names for backwards compatibility.
 *
 * @package RoroAssetsSQLManager
 */

// Abort if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 *  Migration discovery
 *
 * Migrations are defined either as SQL files, PHP files returning an array of
 * migration definitions, or via the `roro_sql_register_migrations` filter.
 * The discovery functions collect all available migrations and sort them
 * lexicographically by ID to define the default application order.
 */

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

/**
 * Recursively search a directory for files matching the given pattern.
 *
 * @param string $dir     Directory to scan.
 * @param string $pattern Regular expression for file names (default .sql/.php).
 * @return array List of absolute file paths.
 */
function roro_sql_manager_glob_recursive($dir, $pattern = '/\.(sql|php)$/i') {
    $res = array();
    if (!is_dir($dir)) {
        return $res;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (preg_match($pattern, $file->getFilename())) {
            $res[] = $file->getPathname();
        }
    }
    return $res;
}

/**
 * Discover all migrations from the filesystem and external filters.
 *
 * Migrations are keyed by their ID. SQL files define an `up` step with no
 * corresponding `down` step. PHP files should return either a single
 * migration definition or an array of definitions. After loading files from
 * the `migrations` directory the result is passed through the
 * `roro_sql_register_migrations` filter to allow other plugins to contribute
 * migrations programmatically.
 *
 * @return array Associative array of migrations keyed by ID.
 */
function roro_sql_manager_discover_migrations() {
    $migrations = array();
    // 1) Scan the migrations directory inside the plugin.
    $dir = trailingslashit(roro_sql_manager_dir()) . 'migrations';
    if (is_dir($dir)) {
        $files = roro_sql_manager_glob_recursive($dir);
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'sql') {
                $id = basename($file, '.sql');
                $migrations[$id] = array(
                    'id'          => $id,
                    'description' => 'SQL file: ' . basename($file),
                    'group'       => 'fs',
                    'depends'     => array(),
                    'up'          => array('file' => $file),
                );
            } elseif ($ext === 'php') {
                $res = include $file;
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
    // 2) Allow external registration via filter.
    $migrations = apply_filters('roro_sql_register_migrations', $migrations);
    // Sort migrations by ID so they apply in ascending order by default.
    uasort($migrations, function ($a, $b) {
        return strcmp($a['id'], $b['id']);
    });
    return $migrations;
}

/* -------------------------------------------------------------------------
 *  SQL parsing and execution
 *
 * SQL scripts may contain comments, string literals and custom delimiters for
 * stored routines. The splitting function parses a raw SQL string into
 * individual statements respecting these features. The execution function
 * wraps the sequence in a transaction and supports dry‑run mode.
 */

/**
 * Split a raw SQL string into individual statements.
 *
 * The parser honours string literals, single and multi‑line comments and
 * `DELIMITER` directives. When a custom delimiter is encountered the
 * subsequent statements are collected until the delimiter appears again.
 * Empty statements and whitespace are removed from the result.
 *
 * @param string $sql_raw Raw SQL to split.
 * @return array List of executable statements.
 */
function roro_sql_manager_split_sql($sql_raw) {
    $sql = str_replace("\r\n", "\n", (string) $sql_raw);
    $len = strlen($sql);
    $stmts = array();
    $buf = '';
    $in_str = false;
    $str_ch = '';
    $in_sl_comment = false; // -- or # comments
    $in_ml_comment = false; // /* ... */ comments
    $delim = ';';           // current delimiter
    $line_start = 0;
    $is_delimiter_line = function ($line) use (&$delim) {
        $trim = ltrim($line);
        if (stripos($trim, 'DELIMITER ') === 0) {
            $token = trim(substr($trim, 10));
            if ($token !== '') {
                $delim = $token;
            }
            return true;
        }
        return false;
    };
    $i = 0;
    while ($i < $len) {
        $ch  = $sql[$i];
        $n2  = ($i + 1 < $len) ? ($ch . $sql[$i + 1]) : '';
        // Detect line start to look for DELIMITER commands.
        if ($i === $line_start) {
            $eol  = strpos($sql, "\n", $i);
            $line = ($eol === false) ? substr($sql, $i) : substr($sql, $i, $eol - $i);
            if (!$in_str && !$in_sl_comment && !$in_ml_comment && $is_delimiter_line($line)) {
                if ($eol === false) {
                    break;
                }
                $i = $eol + 1;
                $line_start = $i;
                continue;
            }
        }
        // End of single line comment?
        if ($in_sl_comment) {
            $buf .= $ch;
            if ($ch === "\n") {
                $in_sl_comment = false;
                $line_start    = $i + 1;
            }
            $i++;
            continue;
        }
        // End of multi line comment?
        if ($in_ml_comment) {
            $buf .= $ch;
            if ($n2 === '*/') {
                $buf .= $sql[$i + 1];
                $i    += 2;
                $in_ml_comment = false;
                continue;
            }
            $i++;
            continue;
        }
        // Inside string literal?
        if ($in_str) {
            $buf .= $ch;
            if ($ch === $str_ch) {
                $escaped = ($i > 0 && $sql[$i - 1] === '\\');
                if (!$escaped) {
                    $in_str = false;
                    $str_ch = '';
                }
            }
            if ($ch === "\n") {
                $line_start = $i + 1;
            }
            $i++;
            continue;
        }
        // Comment start?
        if ($n2 === '--' || $ch === '#') {
            $in_sl_comment = true;
            $buf .= $ch;
            $i++;
            continue;
        }
        if ($n2 === '/*') {
            $in_ml_comment = true;
            $buf .= $n2;
            $i += 2;
            continue;
        }
        // String literal start?
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $in_str = true;
            $str_ch = $ch;
            $buf   .= $ch;
            $i++;
            continue;
        }
        // Check for delimiter match.
        if ($delim !== '' && $delim[0] === $ch) {
            $dl = strlen($delim);
            if ($dl === 1 && $ch === ';') {
                $stmts[] = trim($buf);
                $buf     = '';
                $i++;
                continue;
            } else {
                if ($dl > 1 && substr($sql, $i, $dl) === $delim) {
                    $stmts[] = trim($buf);
                    $buf     = '';
                    $i      += $dl;
                    continue;
                }
            }
        }
        if ($ch === "\n") {
            $line_start = $i + 1;
        }
        $buf .= $ch;
        $i++;
    }
    if (trim($buf) !== '') {
        $stmts[] = trim($buf);
    }
    // Remove any empty statements from the list.
    $stmts = array_values(array_filter($stmts, function ($s) {
        return $s !== '';
    }));
    return $stmts;
}

/**
 * Execute a block of SQL statements with optional dry‑run and transaction.
 *
 * Each SQL statement is run in sequence. If an error is encountered the
 * transaction is rolled back and a WP_Error object is returned. When
 * `$dry_run` is true the statements are written to the log instead of being
 * executed. The database connection is referenced via the `$wpdb` global.
 *
 * @param string $sql     Raw SQL containing one or more statements.
 * @param bool   $dry_run Whether to simulate execution without altering the DB.
 * @return true|WP_Error  True on success or WP_Error on failure.
 */
function roro_sql_manager_execute_sql($sql, $dry_run = false) {
    global $wpdb;
    $stmts = roro_sql_manager_split_sql($sql);
    if (empty($stmts)) {
        return true;
    }
    if (!$dry_run) {
        $wpdb->query('START TRANSACTION');
    }
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
    if (!$dry_run) {
        $wpdb->query('COMMIT');
    }
    return true;
}

/* -------------------------------------------------------------------------
 *  Migration application and rollback
 *
 * These functions determine which migrations need to be run, resolve
 * dependencies, and perform the actual up or down step as appropriate. They
 * also update the list of applied migrations stored in the options table.
 */

/**
 * Return the list of applied migration IDs.
 *
 * @return array
 */
function roro_sql_manager_get_applied() {
    $applied = get_option(RORO_SQL_MANAGER_OPT, array());
    return is_array($applied) ? $applied : array();
}

/**
 * Update the list of applied migration IDs.
 *
 * @param array $applied List of migration IDs that have been applied.
 * @return void
 */
function roro_sql_manager_set_applied($applied) {
    update_option(RORO_SQL_MANAGER_OPT, array_values(array_unique($applied)), false);
}

/**
 * Execute a single migration step.
 *
 * A step can be a raw SQL string, a callable, or an array describing a file
 * or inline SQL. When `$dry_run` is true SQL statements are logged instead
 * of executed and callables are skipped entirely.
 *
 * @param string|callable|array $step     Step definition from a migration.
 * @param string               $direction Either 'up' or 'down' for logging.
 * @param bool                 $dry_run  Whether to simulate execution.
 * @param string               $id_for_log Migration ID for context.
 * @return true|WP_Error
 */
function roro_sql_manager_run_step($step, $direction = 'up', $dry_run = false, $id_for_log = '') {
    $label = strtoupper($direction);
    if (is_string($step)) {
        roro_sql_manager_log('INFO', "$label SQL (string)", array('id' => $id_for_log));
        return roro_sql_manager_execute_sql($step, $dry_run);
    } elseif (is_callable($step)) {
        if ($dry_run) {
            roro_sql_manager_log('INFO', "DRY RUN: $label callable skipped", array('id' => $id_for_log));
            return true;
        }
        $ret = call_user_func($step);
        if ($ret === false) {
            return new WP_Error('callback_failed', "Migration $direction callable returned false.");
        }
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

/**
 * Resolve dependencies and detect cycles among a set of migrations.
 *
 * A depth first search is used to determine an application order given a
 * target set of migrations and the full set of available migrations. When
 * circular dependencies or missing migrations are encountered a WP_Error
 * describing the problem is returned.
 *
 * @param array $targets Subset of migrations to order.
 * @param array $all     All discovered migrations.
 * @return array|WP_Error Ordered list of IDs or WP_Error on failure.
 */
function roro_sql_manager_order_with_deps($targets, $all) {
    $ordered = array();
    $vis     = array(); // 0:unvisited, 1:visiting, 2:visited
    $stack   = array();
    $err     = null;
    $visit = function ($id) use (&$visit, &$vis, &$ordered, &$stack, &$all, &$err) {
        if ($err) {
            return;
        }
        if (!isset($all[$id])) {
            $err = new WP_Error('missing_dependency', 'Missing migration: ' . $id);
            return;
        }
        if (isset($vis[$id]) && $vis[$id] === 2) {
            return;
        }
        if (isset($vis[$id]) && $vis[$id] === 1) {
            $stack[] = $id;
            $err = new WP_Error('circular_dependency', 'Circular dependency detected: ' . implode(' -> ', $stack));
            return;
        }
        $vis[$id] = 1;
        $stack[]  = $id;
        $deps = isset($all[$id]['depends']) && is_array($all[$id]['depends']) ? $all[$id]['depends'] : array();
        foreach ($deps as $d) {
            $visit($d);
            if ($err) {
                return;
            }
        }
        array_pop($stack);
        $vis[$id] = 2;
        $ordered[] = $id;
    };
    foreach (array_keys($targets) as $id) {
        $visit($id);
        if ($err) {
            return $err;
        }
    }
    // Remove duplicates while preserving order.
    $ordered = array_values(array_unique($ordered));
    return $ordered;
}

/**
 * Apply pending migrations.
 *
 * When called without a list of IDs it will apply all migrations that
 * haven’t been applied yet. When IDs are specified only those migrations
 * will be considered. Dependency resolution ensures prerequisites are
 * executed first. After a successful application the list of applied
 * migrations is updated. If `$dry_run` is true the database remains
 * unchanged and the actions are only logged.
 *
 * @param array $selected_ids Optional list of migration IDs to apply.
 * @param bool  $dry_run      Whether to simulate execution.
 * @return true|WP_Error
 */
function roro_sql_manager_apply($selected_ids = array(), $dry_run = false) {
    $all     = roro_sql_manager_discover_migrations();
    $applied = roro_sql_manager_get_applied();
    // Filter to migrations that are either selected or not yet applied.
    $targets = array();
    foreach ($all as $id => $mig) {
        if (!empty($selected_ids) && !in_array($id, $selected_ids, true)) {
            continue;
        }
        if (in_array($id, $applied, true)) {
            continue;
        }
        $targets[$id] = $mig;
    }
    if (empty($targets)) {
        roro_sql_manager_log('INFO', 'No pending migrations to apply.');
        return true;
    }
    $ordered = roro_sql_manager_order_with_deps($targets, $all);
    if (is_wp_error($ordered)) {
        return $ordered;
    }
    do_action('roro_sql_before_apply', array_keys($targets));
    foreach ($ordered as $id) {
        if (!isset($targets[$id])) {
            // Only dependencies, skip.
            continue;
        }
        $mig   = $targets[$id];
        $label = isset($mig['description']) ? $mig['description'] : $id;
        roro_sql_manager_log('INFO', 'Applying ' . $id . ' - ' . $label);
        $up = isset($mig['up']) ? $mig['up'] : null;
        if (!$up) {
            return new WP_Error('invalid_migration', 'Missing up() for ' . $id);
        }
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
 * Roll back applied migrations in reverse order.
 *
 * Only migrations included in `$selected_ids` that have a defined `down` step
 * will be executed. Rollback occurs in reverse order of the selection,
 * ignoring any dependencies. Missing `down` steps are treated as warnings
 * rather than errors. Upon successful rollback the migration is removed
 * from the applied list. As with apply, `$dry_run` simulates the actions
 * without altering the database.
 *
 * @param array $selected_ids List of migration IDs to roll back.
 * @param bool  $dry_run      Whether to simulate execution.
 * @return true|WP_Error
 */
function roro_sql_manager_rollback($selected_ids = array(), $dry_run = false) {
    if (empty($selected_ids)) {
        return new WP_Error('no_selection', 'ロールバック対象が選択されていません。');
    }
    $all     = roro_sql_manager_discover_migrations();
    $applied = roro_sql_manager_get_applied();
    $targets = array();
    foreach ($selected_ids as $id) {
        if (!in_array($id, $applied, true)) {
            continue;
        }
        if (!isset($all[$id])) {
            continue;
        }
        $targets[$id] = $all[$id];
    }
    if (empty($targets)) {
        roro_sql_manager_log('INFO', 'No applicable migrations to rollback.');
        return true;
    }
    $ordered = array_values(array_reverse(array_keys($targets)));
    do_action('roro_sql_before_rollback', $ordered);
    foreach ($ordered as $id) {
        $mig   = $targets[$id];
        $label = isset($mig['description']) ? $mig['description'] : $id;
        roro_sql_manager_log('INFO', 'Rollback ' . $id . ' - ' . $label);
        if (empty($mig['down'])) {
            roro_sql_manager_log('WARN', 'No down() defined. Skipped.', array('id' => $id));
            continue;
        }
        $result = roro_sql_manager_run_step($mig['down'], 'down', $dry_run, $id);
        if (is_wp_error($result)) {
            roro_sql_manager_log('ERROR', 'Rollback failed ' . $id, array('error' => $result->get_error_message()));
            return $result;
        }
        if (!$dry_run) {
            $applied = array_values(array_filter($applied, function ($x) use ($id) {
                return $x !== $id;
            }));
            roro_sql_manager_set_applied($applied);
            roro_sql_manager_log('INFO', 'Rolled back ' . $id);
        }
    }
    do_action('roro_sql_after_rollback', $ordered, $dry_run);
    return true;
}
