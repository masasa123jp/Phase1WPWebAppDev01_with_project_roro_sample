<?php
declare(strict_types=1);

namespace Roro;

defined('ABSPATH') || exit;

/**
 * RORO REST エンドポイント群
 */
final class Roro_REST
{
    /** 認証必須の permission_callback（ログイン + Nonce） */
    public static function require_auth(\WP_REST_Request $request): bool
    {
        if (!\is_user_logged_in()) return false;
        $nonce = (string) $request->get_header('X-WP-Nonce');
        return roro_verify_rest_nonce($nonce);
    }

    /** ルート登録 */
    public static function register_routes(): void
    {
        $ns = 'roro/v1';

        // 公開: サインアップ
        \register_rest_route($ns, '/auth/signup', [
            'methods'  => 'POST',
            'callback' => [self::class, 'signup'],
            'permission_callback' => '__return_true',
            'args' => [
                'email'    => ['required' => true],
                'username' => ['required' => true],
                'password' => ['required' => true],
            ],
        ]);

        // 公開: ログイン
        \register_rest_route($ns, '/auth/login', [
            'methods'  => 'POST',
            'callback' => [self::class, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'login'    => ['required' => true],
                'password' => ['required' => true],
            ],
        ]);

        // 認証: ログアウト
        \register_rest_route($ns, '/auth/logout', [
            'methods'  => 'POST',
            'callback' => [self::class, 'logout'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        // 認証: 自分の情報
        \register_rest_route($ns, '/user/me', [
            'methods'  => 'GET',
            'callback' => [self::class, 'me'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        // 認証: 自分の情報更新（display_name のみ例示）
        \register_rest_route($ns, '/user/me', [
            'methods'  => 'POST',
            'callback' => [self::class, 'update_me'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);

        // 認証: お気に入り
        \register_rest_route($ns, '/favorites', [
            'methods'  => 'GET',
            'callback' => [self::class, 'favorites_list'],
            'permission_callback' => [self::class, 'require_auth'],
        ]);
        \register_rest_route($ns, '/favorites/add', [
            'methods'  => 'POST',
            'callback' => [self::class, 'favorites_add'],
            'permission_callback' => [self::class, 'require_auth'],
            'args' => [
                'target_type' => ['required' => true],
                'source_id'   => ['required' => false],
                'label'       => ['required' => false],
                'lat'         => ['required' => false],
                'lng'         => ['required' => false],
            ],
        ]);
        \register_rest_route($ns, '/favorites/remove', [
            'methods'  => 'POST',
            'callback' => [self::class, 'favorites_remove'],
            'permission_callback' => [self::class, 'require_auth'],
            'args' => [
                'favorite_id' => ['required' => true],
            ],
        ]);
    }

    /** POST /auth/signup */
    public static function signup(\WP_REST_Request $request)
    {
        $email    = \sanitize_email((string)$request->get_param('email'));
        $username = \sanitize_user((string)$request->get_param('username'));
        $password = (string)$request->get_param('password');

        if (!$email || !\is_email($email)) {
            return \rest_ensure_response(['ok' => false, 'message' => 'Invalid email'])->set_status(400);
        }
        if (\email_exists($email)) {
            return \rest_ensure_response(['ok' => false, 'message' => 'Email already exists'])->set_status(409);
        }
        if (\username_exists($username)) {
            return \rest_ensure_response(['ok' => false, 'message' => 'Username already exists'])->set_status(409);
        }
        if ($username === '' || $password === '') {
            return \rest_ensure_response(['ok' => false, 'message' => 'Missing username or password'])->set_status(400);
        }

        $user_id = \wp_create_user($username, $password, $email);
        if (\is_wp_error($user_id)) {
            return \rest_ensure_response(['ok' => false, 'message' => $user_id->get_error_message()])->set_status(500);
        }
        return \rest_ensure_response(['ok' => true, 'user_id' => (int)$user_id]);
    }

    /** POST /auth/login */
    public static function login(\WP_REST_Request $request)
    {
        $login    = \sanitize_text_field((string)$request->get_param('login'));
        $password = (string)$request->get_param('password');

        $creds = [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => true,
        ];
        $user = \wp_signon($creds, false);
        if (\is_wp_error($user)) {
            return \rest_ensure_response(['ok' => false, 'message' => $user->get_error_message()])->set_status(401);
        }
        return \rest_ensure_response(['ok' => true, 'user_id' => (int)$user->ID]);
    }

    /** POST /auth/logout */
    public static function logout(\WP_REST_Request $request)
    {
        \wp_logout();
        return \rest_ensure_response(['ok' => true]);
    }

    /** GET /user/me */
    public static function me(\WP_REST_Request $request)
    {
        $uid = \get_current_user_id();
        $u   = \get_userdata($uid);
        if (!$u) {
            return \rest_ensure_response(['ok' => false, 'message' => 'User not found'])->set_status(404);
        }
        return \rest_ensure_response([
            'ok' => true,
            'user' => [
                'ID'           => (int)$u->ID,
                'user_login'   => (string)$u->user_login,
                'user_email'   => (string)$u->user_email,
                'display_name' => (string)$u->display_name,
                'roles'        => (array)$u->roles,
            ]
        ]);
    }

    /** POST /user/me */
    public static function update_me(\WP_REST_Request $request)
    {
        $uid     = \get_current_user_id();
        $display = \sanitize_text_field((string)$request->get_param('display_name'));

        if ($display !== '') {
            \wp_update_user(['ID' => $uid, 'display_name' => $display]);
        }
        return \rest_ensure_response(['ok' => true]);
    }

    /** GET /favorites */
    public static function favorites_list(\WP_REST_Request $request)
    {
        $uid  = \get_current_user_id();
        $rows = Roro_DB::favoritesListByWpUserId((int)$uid);
        return \rest_ensure_response(['ok' => true, 'items' => $rows]);
    }

    /** POST /favorites/add */
    public static function favorites_add(\WP_REST_Request $request)
    {
        $uid    = \get_current_user_id();
        $params = $request->get_json_params() ?: $request->get_body_params();

        $target_type = \sanitize_text_field((string)($params['target_type'] ?? ''));
        $source_id   = isset($params['source_id']) ? \sanitize_text_field((string)$params['source_id']) : null;
        $label       = isset($params['label'])     ? \sanitize_text_field((string)$params['label'])     : null;
        $lat         = isset($params['lat'])       ? (float)$params['lat'] : null;
        $lng         = isset($params['lng'])       ? (float)$params['lng'] : null;

        if ($target_type === '') {
            return \rest_ensure_response(['ok' => false, 'message' => 'target_type required'])->set_status(400);
        }

        $id = Roro_DB::favoritesAdd((int)$uid, $target_type, $source_id, $label, $lat, $lng);
        return \rest_ensure_response(['ok' => true, 'favorite_id' => $id]);
    }

    /** POST /favorites/remove */
    public static function favorites_remove(\WP_REST_Request $request)
    {
        $uid = \get_current_user_id();
        $fid = (int)$request->get_param('favorite_id');
        if ($fid <= 0) {
            return \rest_ensure_response(['ok' => false, 'message' => 'favorite_id required'])->set_status(400);
        }
        $ok = Roro_DB::favoritesRemove((int)$uid, $fid);
        return \rest_ensure_response(['ok' => (bool)$ok]);
    }
}
