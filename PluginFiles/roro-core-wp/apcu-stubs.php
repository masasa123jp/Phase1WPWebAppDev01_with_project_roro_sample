<?php
// IDE / 静的解析用スタブ。実行には読み込まないこと。
// 参照渡しにより $success に代入が起きる旨を型で伝える。
if (!function_exists('apcu_fetch')) {
    /**
     * @param string|array $key
     * @param bool|null $success
     * @return mixed
     */
    function apcu_fetch($key, ?bool &$success = null) {}
}
if (!function_exists('apcu_add')) {
    /**
     * @param string|array $key
     * @param mixed $var
     * @param int $ttl
     * @return bool|array
     */
    function apcu_add($key, $var = null, int $ttl = 0) {}
}
if (!function_exists('apcu_exists')) {
    /** @param string|array $keys */
    function apcu_exists($keys) {}
}
if (!function_exists('apcu_enabled')) {
    function apcu_enabled(): bool { return false; }
}
