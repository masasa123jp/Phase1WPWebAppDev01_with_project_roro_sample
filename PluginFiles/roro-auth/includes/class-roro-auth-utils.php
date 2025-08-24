<?php
if (!defined('ABSPATH')) exit;

/**
 * RORO_Auth_Utils（修正版）
 *
 * - 設定管理（フォールバック有）
 * - i18n（フォールバック + 置換）
 * - フラッシュ（Cookie）
 * - OAuth（state/PKCE/認可URL/トークン交換）
 * - IDトークン検証（JWKS/RS256/クレーム）
 * - 2FA（TOTP/ワンタイムコード）
 * - 安全リダイレクト
 * - DBユーティリティ（テーブル存在/カラム列挙/リンク登録）
 *
 * ※ 互換性配慮：
 *   - PHP8未満でも動くように str_starts_with / str_contains は内部ヘルパに統一
 *   - roro-core を改造せずに動作
 */
class RORO_Auth_Utils {

    /* ========== 内部状態・定数 ========== */

    private static $messages = [];
    private static $lang = 'ja';

    private const FALLBACK_OPTION_KEY = 'roro_auth_settings';

    private const OTP_TX_PREFIX   = 'roro_auth_otp_';
    private const STATE_TX_PREFIX = 'roro_auth_state_';
    private const JWKS_TX_PREFIX  = 'roro_auth_jwks_';

    private const JWKS_CACHE_TTL  = 3600; // 1時間
    private const META_TOTP_SECRET  = 'roro_auth_totp_secret';
    private const META_2FA_ENABLED  = 'roro_twofactor_enabled';
    private const META_2FA_RECOVERY = 'roro_twofactor_recovery';
    private const OPT_FORCE_ALL     = 'roro_auth_force_all';

    private const OTP_TTL    = 300; // 5分
    private const OTP_DIGITS = 6;


    /* ========== 便利ヘルパ（PHP7互換） ========== */

    /** 先頭一致（PHP8: str_starts_with の代替） */
    private static function starts_with(string $haystack, string $needle): bool {
        if (function_exists('str_starts_with')) {
            return str_starts_with($haystack, $needle);
        }
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    /** 部分一致（PHP8: str_contains の代替） */
    private static function contains(string $haystack, string $needle): bool {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }
        return $needle === '' || strpos($haystack, $needle) !== false;
    }


    /* ========== 設定 ========== */

    public static function default_settings() {
        return [
            'enabled_providers' => [
                'google' => 0,
                'line'   => 0,
            ],
            // クライアント資格情報
            'google_client_id'     => '',
            'google_client_secret' => '',
            'line_channel_id'      => '',
            'line_channel_secret'  => '',

            // ルーティング/リダイレクト
            'redirect_uri_override'   => '',
            'allowed_redirect_hosts'  => [],

            // OAuth拡張
            'pkce_enabled' => 1,
            'jwt_verify'   => 1,

            // 2FA
            'twofactor_default_enabled' => 0,
        ];
    }

    public static function get_settings() {
        $option_key = defined('RORO_AUTH_OPTION_KEY') ? RORO_AUTH_OPTION_KEY : self::FALLBACK_OPTION_KEY;
        $saved = get_option($option_key);
        if (!is_array($saved)) $saved = [];
        if (isset($saved['enabled_providers']) && is_array($saved['enabled_providers'])) {
            foreach ($saved['enabled_providers'] as $k => $v) {
                $saved['enabled_providers'][$k] = (int)(bool)$v;
            }
        }
        return array_merge(self::default_settings(), $saved);
    }

    public static function save_settings(array $settings) {
        $option_key = defined('RORO_AUTH_OPTION_KEY') ? RORO_AUTH_OPTION_KEY : self::FALLBACK_OPTION_KEY;
        update_option($option_key, $settings, false);
    }


    /* ========== i18n ========== */

    /** 現在の言語コード（ja|en|zh|ko） */
    public static function current_lang() {
        if (isset($_GET['roro_lang'])) { // デバッグ/強制切替用
            $code = sanitize_key($_GET['roro_lang']);
            if (!in_array($code, ['ja','en','zh','ko'], true)) {
                $code = 'en';
            }
        } else {
            $locale = (string) get_locale();
            if (self::starts_with($locale, 'ja'))       $code = 'ja';
            elseif (self::starts_with($locale, 'ko'))   $code = 'ko';
            elseif (self::starts_with($locale, 'zh'))   $code = 'zh';
            else                                        $code = 'en';
        }
        self::$lang = $code;
        return $code;
    }

    /** 言語ファイルのベースディレクトリ（存在優先順で返す） */
    private static function base_dir(): string {
        if (defined('RORO_AUTH_PLUGIN_DIR')) return rtrim((string)RORO_AUTH_PLUGIN_DIR, '/\\') . '/';
        if (defined('RORO_AUTH_DIR'))        return rtrim((string)RORO_AUTH_DIR, '/\\') . '/';
        if (defined('RORO_CORE_WP_DIR'))     return rtrim((string)RORO_CORE_WP_DIR, '/\\') . '/';
        return trailingslashit(dirname(__DIR__)); // includes/ の一つ上
    }

    /** 言語メッセージ読込（英語→対象言語の順でマージ） */
    public static function load_messages() {
        $lang = self::current_lang();
        $base = self::base_dir() . 'lang/';

        $files = [
            'en' => $base . 'en.php',
            'ja' => $base . 'ja.php',
            'zh' => $base . 'zh.php',
            'ko' => $base . 'ko.php',
        ];

        $loaded = [];
        if (file_exists($files['en'])) {
            $roro_auth_messages = [];
            include $files['en'];
            if (is_array($roro_auth_messages)) $loaded = $roro_auth_messages;
        }
        if (isset($files[$lang]) && file_exists($files[$lang])) {
            $roro_auth_messages = [];
            include $files[$lang];
            if (is_array($roro_auth_messages)) {
                $loaded = array_merge($loaded, $roro_auth_messages);
            }
        }
        self::$messages = $loaded;
    }

    public static function messages()   { return self::$messages; }
    public static function messages_js(){ return self::$messages; }

    /** {name} のような置換に対応した翻訳取得 */
    public static function t($key, array $repl = []) {
        $msg = self::$messages[$key] ?? $key;
        if ($repl) {
            foreach ($repl as $k => $v) {
                $msg = str_replace('{' . $k . '}', (string)$v, $msg);
            }
        }
        return $msg;
    }


    /* ========== フラッシュ（Cookie） ========== */

    public static function flash($type, $message) {
        $payload = ['type' => $type, 'message' => $message, 'ts' => time()];
        setcookie(
            'roro_auth_flash',
            wp_json_encode($payload),
            time() + 60,
            COOKIEPATH ? COOKIEPATH : '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true // HttpOnly
        );
    }

    public static function consume_flash() {
        if (!empty($_COOKIE['roro_auth_flash'])) {
            $json = stripslashes($_COOKIE['roro_auth_flash']);
            setcookie(
                'roro_auth_flash',
                '',
                time() - 3600,
                COOKIEPATH ? COOKIEPATH : '/',
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }
        return null;
    }


    /* ========== OAuth（state/PKCE/URL/トークン） ========== */

    public static function generate_state($provider, $redirect_to) {
        $state = wp_generate_password(24, false, false);
        set_transient(self::STATE_TX_PREFIX . $provider . '_' . $state, [
            'redirect_to' => $redirect_to,
            'created'     => time(),
        ], 15 * MINUTE_IN_SECONDS);
        return $state;
    }

    public static function consume_state($provider, $state) {
        if (!$state) return false;
        $key = self::STATE_TX_PREFIX . $provider . '_' . $state;
        $val = get_transient($key);
        delete_transient($key);
        return $val ?: false;
    }

    /** PKCEペア生成（S256） */
    public static function generate_pkce_pair(): array {
        $verifier  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return ['verifier' => $verifier, 'challenge' => $challenge, 'method' => 'S256'];
    }

    /** プロバイダ既定のリダイレクトURI（?roro_auth=callback&provider=...） */
    public static function redirect_uri($provider) {
        $settings = self::get_settings();
        if (!empty($settings['redirect_uri_override'])) {
            return $settings['redirect_uri_override'];
        }
        return add_query_arg([
            'roro_auth' => 'callback',
            'provider'  => $provider,
        ], home_url('/'));
    }

    /** 認可URL生成（必要に応じてPKCE付与） */
    public static function build_authorize_url($provider, $client_id, $state, $scope = 'openid email profile', array $extra = []) {
        $settings = self::get_settings();
        $pkce = (!empty($settings['pkce_enabled'])) ? self::generate_pkce_pair() : null;

        $auth_url = ($provider === 'google')
            ? 'https://accounts.google.com/o/oauth2/v2/auth'
            : 'https://access.line.me/oauth2/v2.1/authorize';

        $params = [
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => self::redirect_uri($provider),
            'scope'         => $scope,
            'state'         => $state,
        ];

        // LINEは openid を必ず含める（不足時は付与）
        if ($provider === 'line' && !self::contains($scope, 'openid')) {
            $params['scope'] = trim($scope . ' openid');
        }

        if ($pkce) {
            $params['code_challenge']        = $pkce['challenge'];
            $params['code_challenge_method'] = $pkce['method'];
            set_transient(self::STATE_TX_PREFIX . $provider . '_' . $state . '_pkce', [
                'verifier' => $pkce['verifier'],
                'ts'       => time(),
            ], 15 * MINUTE_IN_SECONDS);
        }

        foreach ($extra as $k => $v) {
            $params[$k] = $v;
        }

        return $auth_url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** 保存しておいたPKCEベリファイアの取得 */
    public static function get_pkce_verifier($provider, $state): ?string {
        $tx = get_transient(self::STATE_TX_PREFIX . $provider . '_' . $state . '_pkce');
        if (is_array($tx) && !empty($tx['verifier'])) return $tx['verifier'];
        return null;
    }

    /** トークン交換（共通） */
    public static function exchange_code_for_token($provider, $code, $client_id, $client_secret = '', $redirect_uri = '') {
        $token_url = ($provider === 'google')
            ? 'https://oauth2.googleapis.com/token'
            : 'https://api.line.me/oauth2/v2.1/token';

        if (!$redirect_uri) $redirect_uri = self::redirect_uri($provider);

        $state = isset($_GET['state']) ? sanitize_text_field((string)$_GET['state']) : '';

        $args = [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
            ],
            'timeout' => 20,
        ];

        // PKCE優先
        if ($verifier = self::get_pkce_verifier($provider, $state)) {
            $args['body']['code_verifier'] = $verifier;
        } else {
            // クライアントシークレット方式
            if (!empty($client_secret)) {
                $args['body']['client_secret'] = $client_secret;
            }
        }

        $res = wp_remote_post($token_url, $args);
        if (is_wp_error($res)) {
            self::log('token_exchange_error', ['error' => $res->get_error_message()]);
            return ['error' => 'token_exchange_failed'];
        }
        $json = json_decode(wp_remote_retrieve_body($res), true);
        return is_array($json) ? $json : ['error' => 'invalid_token_response'];
    }


    /* ========== IDトークン検証（JWT/JWKS） ========== */

    private static function b64url_decode($data) {
        $data = strtr($data, '-_', '+/');
        $pad  = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode($data);
    }

    public static function jwt_decode_payload($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return null;
        $payload = self::b64url_decode($parts[1]);
        $json = json_decode($payload, true);
        return is_array($json) ? $json : null;
    }

    private static function jwks_endpoint($provider): string {
        return ($provider === 'google')
            ? 'https://www.googleapis.com/oauth2/v3/certs'
            : 'https://api.line.me/oauth2/v2.1/certs';
    }

    private static function get_jwks($provider) {
        $tx_key = self::JWKS_TX_PREFIX . $provider;
        $cached = get_transient($tx_key);
        if (is_array($cached)) return $cached;

        $url = self::jwks_endpoint($provider);
        $res = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($res)) {
            self::log('jwks_fetch_error', ['error' => $res->get_error_message()]);
            return null;
        }
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (isset($json['keys']) && is_array($json['keys'])) {
            set_transient($tx_key, $json['keys'], self::JWKS_CACHE_TTL);
            return $json['keys'];
        }
        return null;
    }

    private static function der_encode_length($length): string {
        if ($length <= 0x7F) return chr($length);
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function jwk_to_pem(array $jwk): ?string {
        if (empty($jwk['n']) || empty($jwk['e'])) return null;
        $modulus  = self::b64url_decode($jwk['n']);
        $exponent = self::b64url_decode($jwk['e']);

        $mod = "\x02" . self::der_encode_length(strlen(ltrim($modulus, "\x00"))) . ltrim($modulus, "\x00");
        $exp = "\x02" . self::der_encode_length(strlen($exponent)) . $exponent;
        $rsakey = "\x30" . self::der_encode_length(strlen($mod . $exp)) . $mod . $exp;

        $alg = "\x30\x0D\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00"; // rsaEncryption + NULL
        $spk = "\x03" . self::der_encode_length(strlen("\x00" . $rsakey)) . "\x00" . $rsakey;
        $der = "\x30" . self::der_encode_length(strlen($alg . $spk)) . $alg . $spk;

        return "-----BEGIN PUBLIC KEY-----\n" .
               chunk_split(base64_encode($der), 64, "\n") .
               "-----END PUBLIC KEY-----\n";
    }

    public static function verify_id_token($provider, $id_token, $client_id): array {
        $settings = self::get_settings();
        if (empty($settings['jwt_verify'])) {
            return ['ok' => true, 'payload' => self::jwt_decode_payload($id_token), 'notice' => 'jwt_verify_disabled'];
        }

        $parts = explode('.', $id_token);
        if (count($parts) !== 3) return ['ok' => false, 'error' => 'jwt_format'];

        [$h_b64, $p_b64, $s_b64] = $parts;
        $header  = json_decode(self::b64url_decode($h_b64), true);
        $payload = json_decode(self::b64url_decode($p_b64), true);
        $sig     = self::b64url_decode($s_b64);

        if (!is_array($header) || !is_array($payload)) return ['ok' => false, 'error' => 'jwt_decode'];
        if (($header['alg'] ?? '') !== 'RS256')      return ['ok' => false, 'error' => 'alg_not_supported'];

        // 署名検証
        if (!function_exists('openssl_verify')) {
            self::log('jwt_verify_skipped', ['reason' => 'openssl_missing']);
        } else {
            $kid = $header['kid'] ?? '';
            $keys = self::get_jwks($provider);
            if (!$keys) return ['ok' => false, 'error' => 'jwks_unavailable'];

            $pem = null; $verified = false;
            foreach ($keys as $jwk) {
                if ($kid && ($jwk['kid'] ?? '') !== $kid) continue;
                $pem = self::jwk_to_pem($jwk);
                if ($pem && openssl_verify("$h_b64.$p_b64", $sig, $pem, OPENSSL_ALGO_SHA256) === 1) {
                    $verified = true; break;
                }
            }
            // kid指定で失敗した場合は全鍵で救済試行
            if (!$verified && $kid) {
                foreach ($keys as $jwk) {
                    $pem = self::jwk_to_pem($jwk);
                    if ($pem && openssl_verify("$h_b64.$p_b64", $sig, $pem, OPENSSL_ALGO_SHA256) === 1) {
                        $verified = true; break;
                    }
                }
            }
            if (!$verified) return ['ok' => false, 'error' => 'jwt_signature'];
        }

        // iss/aud/exp/iat の検証
        $iss_ok = false;
        if ($provider === 'google') {
            $iss_ok = in_array($payload['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true);
        } else { // line
            $iss_ok = in_array($payload['iss'] ?? '', ['https://access.line.me'], true);
        }
        if (!$iss_ok) return ['ok' => false, 'error' => 'iss'];

        if (($payload['aud'] ?? '') !== $client_id) return ['ok' => false, 'error' => 'aud'];

        $now = time();
        if (($payload['exp'] ?? 0) < $now)         return ['ok' => false, 'error' => 'exp'];
        if (($payload['iat'] ?? 0) > $now + 300)   return ['ok' => false, 'error' => 'iat'];

        return ['ok' => true, 'payload' => $payload];
    }


    /* ========== 2FA（TOTP/ワンタイム） ========== */

    public static function is_twofactor_enabled($user_id): bool {
        $force_all = (int) get_option(self::OPT_FORCE_ALL, 0);
        if ($force_all) return true;
        return (bool) get_user_meta($user_id, self::META_2FA_ENABLED, true);
    }

    public static function generate_code(int $digits = self::OTP_DIGITS): string {
        $digits = max(4, min(10, $digits));
        $max = 10 ** $digits - 1;
        return str_pad((string)random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }

    public static function issue_one_time_code($user_id, $send_mail = false): string {
        $code = self::generate_code();
        set_transient(self::OTP_TX_PREFIX . $user_id, $code, self::OTP_TTL);
        if ($send_mail) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                $subj = '【RORO】ログイン用認証コード';
                $body = "以下の認証コードをご入力ください（有効期限5分）\n\n{$code}\n";
                wp_mail($user->user_email, $subj, $body, ['Content-Type: text/plain; charset=UTF-8']);
            }
        }
        return $code;
    }

    public static function verify_one_time_code($user_id, $code): bool {
        $stored = get_transient(self::OTP_TX_PREFIX . $user_id);
        if (!$stored) return false;
        $ok = hash_equals((string)$stored, (string)$code);
        if ($ok) delete_transient(self::OTP_TX_PREFIX . $user_id);
        return $ok;
    }

    private static function base32_decode($b32) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
        $bits = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $v = strpos($alphabet, $b32[$i]);
            if ($v === false) continue;
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $data = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) break;
            $data .= chr(bindec($byte));
        }
        return $data;
    }

    public static function totp($secret_b32, $time = null, $digits = 6, $period = 30): string {
        $time = $time ?? time();
        $counter = pack('N*', 0) . pack('N*', floor($time / $period));
        $key = self::base32_decode($secret_b32);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $code = $truncated % (10 ** $digits);
        return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
    }

    public static function verify_totp($secret_b32, $code, $digits = 6, $period = 30): bool {
        $now = time();
        foreach ([0, -1, +1] as $w) {
            if (hash_equals(self::totp($secret_b32, $now + ($w * $period), $digits, $period), (string)$code)) {
                return true;
            }
        }
        return false;
    }

    /** TOTP優先 → ダメならワンタイムコード */
    public static function verify_code($user_id, $code): bool {
        $secret = get_user_meta($user_id, self::META_TOTP_SECRET, true);
        if ($secret && self::verify_totp($secret, $code)) {
            return true;
        }
        return self::verify_one_time_code($user_id, $code);
    }


    /* ========== 安全リダイレクト ========== */

    public static function safe_redirect($to, $default = ''): string {
        $default = $default ?: home_url('/');
        $allowed_hosts = (array)(self::get_settings()['allowed_redirect_hosts'] ?? []);
        $validated = wp_validate_redirect($to, $default);
        $host = wp_parse_url($validated, PHP_URL_HOST);
        if (!$host) return $default;
        if ($host === wp_parse_url(home_url(), PHP_URL_HOST) || in_array($host, $allowed_hosts, true)) {
            return $validated;
        }
        return $default;
    }


    /* ========== DBユーティリティ ========== */

    public static function table_exists($table) {
        global $wpdb;
        $like = $wpdb->esc_like($table);
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
        return (strtolower((string)$found) === strtolower((string)$table));
    }

    public static function get_table_columns($table) {
        global $wpdb;
        $cols = [];
        $rows = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
        if ($rows) foreach ($rows as $r) $cols[] = $r['Field'];
        return $cols;
    }

    private static function guess_primary_key($table): string {
        global $wpdb;
        $rows = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
        if ($rows) {
            foreach ($rows as $r) {
                if (($r['Key'] ?? '') === 'PRI') {
                    return (string)$r['Field'];
                }
            }
        }
        return 'id';
    }

    /**
     * RORO_CUSTOMER / RORO_USER_LINK_WP へ安全に登録（存在するカラムのみ）
     */
    public static function ensure_customer_link($wp_user_id, $email, $name) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $customer_table = $prefix . 'RORO_CUSTOMER';
        $link_table     = $prefix . 'RORO_USER_LINK_WP';

        // 既にメタがあればスキップ
        $existing = get_user_meta($wp_user_id, 'roro_customer_id', true);
        if ($existing) return true;

        if (self::table_exists($customer_table)) {
            $cols = self::get_table_columns($customer_table);
            $pk   = self::guess_primary_key($customer_table);

            // 既存検索（email / wp_user_id）
            $customer_id = 0;
            if (in_array('email', $cols, true) && $email) {
                $customer_id = (int)$wpdb->get_var($wpdb->prepare("SELECT {$pk} FROM {$customer_table} WHERE email = %s LIMIT 1", $email));
            }
            if (!$customer_id && in_array('wp_user_id', $cols, true)) {
                $customer_id = (int)$wpdb->get_var($wpdb->prepare("SELECT {$pk} FROM {$customer_table} WHERE wp_user_id = %d LIMIT 1", $wp_user_id));
            }

            if (!$customer_id) {
                $data = []; $fmt = [];
                $map = [
                    'wp_user_id'   => (int)$wp_user_id,
                    'email'        => (string)$email,
                    'full_name'    => $name ?: '',
                    'display_name' => $name ?: '',
                    'lang'         => self::current_lang(),
                    'created_at'   => current_time('mysql'),
                    'updated_at'   => current_time('mysql'),
                    'status'       => 'active',
                ];
                foreach ($map as $k => $v) {
                    if (in_array($k, $cols, true)) {
                        $data[$k] = $v;
                        $fmt[] = is_int($v) ? '%d' : '%s';
                    }
                }
                if (!empty($data)) {
                    $ins = $wpdb->insert($customer_table, $data, $fmt);
                    if ($ins !== false) {
                        $customer_id = (int)$wpdb->insert_id;
                    }
                }
            }

            if ($customer_id) {
                update_user_meta($wp_user_id, 'roro_customer_id', $customer_id);

                if (self::table_exists($link_table)) {
                    $link_cols = self::get_table_columns($link_table);
                    $lmap = [
                        'wp_user_id'   => (int)$wp_user_id,
                        'customer_id'  => (int)$customer_id,
                        'linked_at'    => current_time('mysql'),
                        'status'       => 'linked',
                    ];
                    $ldata = []; $lfmt = [];
                    foreach ($lmap as $k => $v) {
                        if (in_array($k, $link_cols, true)) {
                            $ldata[$k] = $v;
                            $lfmt[] = is_int($v) ? '%d' : '%s';
                        }
                    }
                    if (!empty($ldata)) {
                        $exists = false;
                        if (in_array('wp_user_id', $link_cols, true) && in_array('customer_id', $link_cols, true)) {
                            $exists = (bool)$wpdb->get_var($wpdb->prepare(
                                "SELECT 1 FROM {$link_table} WHERE wp_user_id=%d AND customer_id=%d LIMIT 1",
                                $wp_user_id, $customer_id
                            ));
                        }
                        if (!$exists) $wpdb->insert($link_table, $ldata, $lfmt);
                    }
                }
            }
        }
        return true;
    }


    /* ========== ログ ========== */

    public static function log($tag, array $ctx = []) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[roro-auth][' . $tag . '] ' . wp_json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
