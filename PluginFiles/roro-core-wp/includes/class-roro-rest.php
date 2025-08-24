<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class RORO_REST {
    private const NS = 'roro/v1';

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        // 認証系
        register_rest_route(self::NS, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => true],
            ],
        ]);
        register_rest_route(self::NS, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'logout'],
            'permission_callback' => [self::class, 'require_rest_nonce'],
        ]);
        register_rest_route(self::NS, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'register_user'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'    => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => true, 'minLength' => 8],
                'name'     => ['type' => 'string', 'required' => false],
            ],
        ]);

        // プロフィール
        register_rest_route(self::NS, '/profile', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_profile'],
            'permission_callback' => [self::class, 'require_login'],
        ]);
        register_rest_route(self::NS, '/profile', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'update_profile'],
            'permission_callback' => [self::class, 'require_login'],
            'args'                => [
                'display_name' => ['type' => 'string', 'required' => false],
                'first_name'   => ['type' => 'string', 'required' => false],
                'last_name'    => ['type' => 'string', 'required' => false],
                'locale'       => ['type' => 'string', 'required' => false],
            ],
        ]);

        // お気に入り
        register_rest_route(self::NS, '/favorites', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'favorites_list'],
            'permission_callback' => [self::class, 'require_login'],
        ]);
        register_rest_route(self::NS, '/favorites', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'favorites_add'],
            'permission_callback' => [self::class, 'require_login'],
            'args'                => [
                'id'    => ['type' => 'integer', 'required' => true], // roro_event の post ID
                'title' => ['type' => 'string', 'required' => false],
            ],
        ]);
        register_rest_route(self::NS, '/favorites/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [self::class, 'favorites_remove'],
            'permission_callback' => [self::class, 'require_login'],
        ]);

        // イベント（CPT ラッパー：検索/都道府県絞り込み）
        register_rest_route(self::NS, '/events', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'events_query'],
            'permission_callback' => '__return_true',
            'args'                => [
                'q'          => ['type' => 'string', 'required' => false],
                'pref'       => ['type' => 'string', 'required' => false],
                'per_page'   => ['type' => 'integer', 'required' => false, 'default' => 50],
                'page'       => ['type' => 'integer', 'required' => false, 'default' => 1],
            ],
        ]);

        // AI プロキシ（将来拡張用のフック。現状はダミー応答）
        register_rest_route(self::NS, '/ai/proxy', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'ai_proxy'],
            'permission_callback' => [self::class, 'require_login'], // 個別要件に応じて公開範囲変更
            'args'                => [
                'message' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    // -------------------------- permission --------------------------------
    public static function require_login(): bool {
        return is_user_logged_in();
    }
    public static function require_rest_nonce(\WP_REST_Request $req): bool {
        $nonce = $req->get_header('x_wp_nonce') ?: $req->get_header('X-WP-Nonce');
        return (bool) $nonce && wp_verify_nonce($nonce, 'wp_rest');
    }

    // -------------------------- auth --------------------------------------
    public static function login(\WP_REST_Request $req): \WP_REST_Response {
        $creds = [
            'user_login'    => sanitize_text_field((string)$req['username']),
            'user_password' => (string)$req['password'],
            'remember'      => true,
        ];
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $user->get_error_message(),
            ], 401);
        }
        return new \WP_REST_Response([
            'success'  => true,
            'user'     => [
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
            ],
        ], 200);
    }

    public static function logout(\WP_REST_Request $req): \WP_REST_Response {
        wp_logout();
        return new \WP_REST_Response(['success' => true], 200);
    }

    public static function register_user(\WP_REST_Request $req): \WP_REST_Response {
        $email    = sanitize_email((string)$req['email']);
        $password = (string)$req['password'];
        $name     = sanitize_text_field((string)($req['name'] ?? ''));

        if (!is_email($email)) {
            return new \WP_REST_Response(['success'=>false,'message'=>__('Invalid email.', 'roro-core-wp')], 400);
        }
        if (email_exists($email)) {
            return new \WP_REST_Response(['success'=>false,'message'=>__('Email already registered.', 'roro-core-wp')], 409);
        }
        $username = sanitize_user(current(explode('@', $email)));
        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false, false);
        }
        $uid = wp_create_user($username, $password, $email);
        if (is_wp_error($uid)) {
            return new \WP_REST_Response(['success'=>false,'message'=>$uid->get_error_message()], 400);
        }
        if ($name !== '') {
            wp_update_user(['ID' => $uid, 'display_name' => $name]);
        }
        return new \WP_REST_Response(['success'=>true,'user_id'=>(int)$uid], 201);
    }

    // -------------------------- profile -----------------------------------
    public static function get_profile(\WP_REST_Request $req): \WP_REST_Response {
        $u = wp_get_current_user();
        return new \WP_REST_Response([
            'id'           => $u->ID,
            'display_name' => $u->display_name,
            'first_name'   => get_user_meta($u->ID, 'first_name', true),
            'last_name'    => get_user_meta($u->ID, 'last_name', true),
            'locale'       => get_user_locale($u),
            'email'        => $u->user_email,
        ], 200);
    }

    public static function update_profile(\WP_REST_Request $req): \WP_REST_Response {
        $u = wp_get_current_user();
        $payload = [];

        if (isset($req['display_name'])) $payload['display_name'] = sanitize_text_field((string)$req['display_name']);
        if ($payload) {
            $payload['ID'] = $u->ID;
            wp_update_user($payload);
        }
        if (isset($req['first_name'])) update_user_meta($u->ID, 'first_name', sanitize_text_field((string)$req['first_name']));
        if (isset($req['last_name']))  update_user_meta($u->ID, 'last_name',  sanitize_text_field((string)$req['last_name']));
        if (isset($req['locale']))     update_user_meta($u->ID, 'locale',      sanitize_text_field((string)$req['locale']));

        return new \WP_REST_Response(['success'=>true], 200);
    }

    // -------------------------- favorites ---------------------------------
    private static function get_user_favorites(int $user_id): array {
        $raw = get_user_meta($user_id, 'roro_favorites', true);
        $arr = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        return is_array($arr) ? $arr : [];
    }
    private static function save_user_favorites(int $user_id, array $items): void {
        update_user_meta($user_id, 'roro_favorites', wp_json_encode(array_values($items)));
    }

    public static function favorites_list(\WP_REST_Request $req): \WP_REST_Response {
        $uid = get_current_user_id();
        return new \WP_REST_Response(self::get_user_favorites((int)$uid), 200);
    }
    public static function favorites_add(\WP_REST_Request $req): \WP_REST_Response {
        $uid = (int) get_current_user_id();
        $items = self::get_user_favorites($uid);
        $id = (int) $req['id'];
        $title = sanitize_text_field((string)($req['title'] ?? ''));

        foreach ($items as $it) {
            if ((int)$it['id'] === $id) {
                return new \WP_REST_Response(['success'=>true,'message'=>'already'], 200);
            }
        }
        $items[] = ['id'=>$id, 'title'=>$title];
        self::save_user_favorites($uid, $items);
        return new \WP_REST_Response(['success'=>true], 201);
    }
    public static function favorites_remove(\WP_REST_Request $req): \WP_REST_Response {
        $uid = (int) get_current_user_id();
        $id  = (int) $req['id'];
        $items = array_values(array_filter(self::get_user_favorites($uid), static fn($it) => (int)$it['id'] !== $id));
        self::save_user_favorites($uid, $items);
        return new \WP_REST_Response(['success'=>true], 200);
    }

    // -------------------------- events ------------------------------------
    public static function events_query(\WP_REST_Request $req): \WP_REST_Response {
        $q    = sanitize_text_field((string)($req['q'] ?? ''));
        $pref = sanitize_text_field((string)($req['pref'] ?? ''));
        $per  = max(1, min(100, (int)($req['per_page'] ?? 50)));
        $page = max(1, (int)($req['page'] ?? 1));

        $args = [
            'post_type'      => 'roro_event',
            'post_status'    => 'publish',
            's'              => $q,
            'posts_per_page' => $per,
            'paged'          => $page,
            'fields'         => 'ids',
        ];
        if ($pref !== '') {
            $args['tax_query'] = [[
                'taxonomy' => 'roro_pref',
                'field'    => 'slug',
                'terms'    => $pref,
            ]];
        }
        $ids = get_posts($args);
        $rows = [];
        foreach ($ids as $pid) {
            $rows[] = [
                'id'      => (int)$pid,
                'title'   => get_the_title($pid),
                'date'    => get_post_meta($pid, 'event_date', true),
                'lat'     => (float) get_post_meta($pid, 'lat', true),
                'lng'     => (float) get_post_meta($pid, 'lng', true),
                'address' => (string) get_post_meta($pid, 'address', true),
                'link'    => get_permalink($pid),
            ];
        }
        return new \WP_REST_Response([
            'items' => $rows,
            'page'  => $page,
            'per'   => $per,
            'total' => (int) $GLOBALS['wp_query']->found_posts,
        ], 200);
    }

    // -------------------------- AI proxy (ダミー) --------------------------
    public static function ai_proxy(\WP_REST_Request $req): \WP_REST_Response {
        $msg = sanitize_text_field((string)$req['message']);
        // 将来: 設定からプロバイダを選択して wp_remote_post で外部推論API呼び出し
        $reply = sprintf(__('You said: %s', 'roro-core-wp'), $msg);
        return new \WP_REST_Response([
            'success' => true,
            'reply'   => $reply,
        ], 200);
    }
}
