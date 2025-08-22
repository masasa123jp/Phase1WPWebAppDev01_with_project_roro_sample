<?php
declare(strict_types=1);

namespace Roro;

defined('ABSPATH') || exit;

/** ログイン判定（ラッパ） */
function roro_is_logged_in(): bool
{
    return \is_user_logged_in();
}

/** 現在のWPユーザーID */
function roro_current_user_id(): int
{
    return (int) (\is_user_logged_in() ? \get_current_user_id() : 0);
}

/** REST: Nonce 検証（ヘッダ X-WP-Nonce を想定） */
function roro_verify_rest_nonce(?string $nonce): bool
{
    if (!$nonce) return false;
    return (bool) \wp_verify_nonce($nonce, 'wp_rest');
}

/** REST: 成功レスポンス（標準化） */
function roro_rest_ok(array $payload = [], int $status = 200): \WP_REST_Response
{
    $res = \rest_ensure_response(array_merge(['ok' => true], $payload));
    $res->set_status($status);
    return $res;
}

/** REST: 失敗レスポンス（標準化） */
function roro_rest_ng(string $message, int $status = 400, array $extra = []): \WP_REST_Response
{
    $res = \rest_ensure_response(array_merge(['ok' => false, 'message' => $message], $extra));
    $res->set_status($status);
    return $res;
}
