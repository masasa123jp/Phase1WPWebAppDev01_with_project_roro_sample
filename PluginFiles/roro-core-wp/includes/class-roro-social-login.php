<?php
/**
 * RORO Social Login (Google / LINE)
 *
 * - admin-ajax.php を利用して OAuth 開始 / コールバックを処理
 * - 新規ユーザーは WP の subscriber として作成
 * - 既存ユーザーは usermeta に保存されたプロバイダーIDでマッチング
 *
 * @package roro-core-wp
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('RORO_Social_Login', false)):

final class RORO_Social_Login {

    private static ?self $instance = null;
    private const STATE_TTL = 10 * MINUTE_IN_SECONDS;

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        // ショートコード: [roro_social_login redirect="/"]
        add_shortcode('roro_social_login', [$this, 'shortcode_buttons']);

        // OAuth 開始（未ログイン）
        add_action('wp_ajax_nopriv_roro_social_login_start',    [$this, 'handle_start']);
        // OAuth コールバック（未ログイン）
        add_action('wp_ajax_nopriv_roro_social_login_callback', [$this, 'handle_callback']);
    }

    /** ショートコード描画（多言語） */
    public function shortcode_buttons(array $atts = []): string {
        $atts = shortcode_atts(['redirect' => home_url('/')], $atts, 'roro_social_login');
        $redirect = esc_url_raw((string)$atts['redirect']);
        $nonce = wp_create_nonce('roro_social_login_start');

        $google_url = add_query_arg([
            'action'   => 'roro_social_login_start',
            'provider' => 'google',
            'redirect' => rawurlencode($redirect),
            '_wpnonce' => $nonce,
        ], admin_url('admin-ajax.php'));

        $line_url = add_query_arg([
            'action'   => 'roro_social_login_start',
            'provider' => 'line',
            'redirect' => rawurlencode($redirect),
            '_wpnonce' => $nonce,
        ], admin_url('admin-ajax.php'));

        ob_start();
        ?>
        <div class="roro-social-login">
            <a class="roro-btn roro-btn-google" href="<?php echo esc_url($google_url); ?>">
                <?php echo esc_html__('Sign in with Google', 'roro-core-wp'); ?>
            </a>
            <a class="roro-btn roro-btn-line" href="<?php echo esc_url($line_url); ?>">
                <?php echo esc_html__('Sign in with LINE', 'roro-core-wp'); ?>
            </a>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    /** OAuth 開始 */
    public function handle_start(): void {
        if (!wp_verify_nonce((string)($_GET['_wpnonce'] ?? ''), 'roro_social_login_start')) {
            wp_die(esc_html__('Invalid nonce.', 'roro-core-wp'));
        }
        $provider = sanitize_key((string)($_GET['provider'] ?? ''));
        $redirect_to = esc_url_raw((string)($_GET['redirect'] ?? home_url('/')));

        // 復帰先を短時間クッキーに保存
        setcookie('roro_social_redirect_to', $redirect_to, [
            'expires'  => time() + self::STATE_TTL,
            'path'     => COOKIEPATH ?: '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $state = wp_generate_uuid4();
        set_transient('roro_social_state_' . $state, 1, self::STATE_TTL);

        if ($provider === 'google') {
            $url = $this->build_google_auth_url($state);
            wp_redirect($url);
            exit;
        } elseif ($provider === 'line') {
            $url = $this->build_line_auth_url($state);
            wp_redirect($url);
            exit;
        }

        wp_die(esc_html__('Unknown provider.', 'roro-core-wp'));
    }

    /** OAuth コールバック */
    public function handle_callback(): void {
        $provider = sanitize_key((string)($_GET['provider'] ?? ''));
        $state    = (string)($_GET['state'] ?? '');
        $code     = (string)($_GET['code']  ?? '');
        $error    = (string)($_GET['error'] ?? '');

        $redirect_to = isset($_COOKIE['roro_social_redirect_to'])
            ? esc_url_raw((string)$_COOKIE['roro_social_redirect_to'])
            : home_url('/');

        if ($error !== '') {
            $this->redirect_with_error($redirect_to, $error);
        }

        // state 検証
        if ($state === '' || !get_transient('roro_social_state_' . $state)) {
            $this->redirect_with_error($redirect_to, 'invalid_state');
        }
        delete_transient('roro_social_state_' . $state);

        if ($code === '') {
            $this->redirect_with_error($redirect_to, 'missing_code');
        }

        try {
            if ($provider === 'google') {
                $profile = $this->exchange_google($code);
                $user_id = $this->upsert_user('google', $profile);
            } elseif ($provider === 'line') {
                $profile = $this->exchange_line($code);
                $user_id = $this->upsert_user('line', $profile);
            } else {
                $this->redirect_with_error($redirect_to, 'unknown_provider');
                return;
            }

            if ($user_id > 0) {
                wp_set_auth_cookie($user_id, true);
                do_action('wp_login', (string)get_userdata($user_id)->user_login, get_userdata($user_id));
                wp_safe_redirect($redirect_to);
                exit;
            }
            $this->redirect_with_error($redirect_to, 'login_failed');

        } catch (\Throwable $e) {
            $this->redirect_with_error($redirect_to, 'exception');
        }
    }

    private function redirect_with_error(string $redirect_to, string $reason): void {
        $url = add_query_arg('roro_login_error', rawurlencode($reason), $redirect_to);
        wp_safe_redirect($url);
        exit;
    }

    /** 認可URL組み立て: Google */
    private function build_google_auth_url(string $state): string {
        $opt = get_option(\RORO_Admin_Settings::OPTION, \RORO_Admin_Settings::defaults());
        $client_id = (string)($opt['social_google_client_id'] ?? '');
        $redirect  = \RORO_Admin_Settings::google_redirect_uri();

        $params = [
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect,
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'        => 'consent',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** 認可URL組み立て: LINE */
    private function build_line_auth_url(string $state): string {
        $opt = get_option(\RORO_Admin_Settings::OPTION, \RORO_Admin_Settings::defaults());
        $client_id = (string)($opt['social_line_channel_id'] ?? '');
        $redirect  = \RORO_Admin_Settings::line_redirect_uri();

        $params = [
            'response_type' => 'code',
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect,
            'scope'         => 'profile openid email',
            'state'         => $state,
        ];
        return 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** トークン→プロフィール: Google */
    private function exchange_google(string $code): array {
        $opt = get_option(\RORO_Admin_Settings::OPTION, \RORO_Admin_Settings::defaults());
        $client_id     = (string)($opt['social_google_client_id'] ?? '');
        $client_secret = (string)($opt['social_google_client_secret'] ?? '');
        $redirect      = \RORO_Admin_Settings::google_redirect_uri();

        // アクセストークン取得
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect,
                'grant_type'    => 'authorization_code',
            ],
        ]);
        $json = json_decode((string)wp_remote_retrieve_body($resp), true);
        if (empty($json['access_token'])) {
            throw new \RuntimeException('google_token_failed');
        }

        // ユーザー情報
        $userinfo = wp_remote_get('https://openidconnect.googleapis.com/v1/userinfo', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $json['access_token']],
        ]);
        $u = json_decode((string)wp_remote_retrieve_body($userinfo), true);

        return [
            'provider' => 'google',
            'id'       => (string)($u['sub'] ?? ''),
            'email'    => (string)($u['email'] ?? ''),
            'name'     => (string)($u['name'] ?? ''),
            'given'    => (string)($u['given_name'] ?? ''),
            'family'   => (string)($u['family_name'] ?? ''),
            'picture'  => (string)($u['picture'] ?? ''),
            'raw'      => $u,
        ];
    }

    /** トークン→プロフィール: LINE */
    private function exchange_line(string $code): array {
        $opt = get_option(\RORO_Admin_Settings::OPTION, \RORO_Admin_Settings::defaults());
        $client_id     = (string)($opt['social_line_channel_id'] ?? '');
        $client_secret = (string)($opt['social_line_channel_secret'] ?? '');
        $redirect      = \RORO_Admin_Settings::line_redirect_uri();

        // アクセストークン取得
        $resp = wp_remote_post('https://api.line.me/oauth2/v2.1/token', [
            'timeout' => 15,
            'body'    => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect,
                'grant_type'    => 'authorization_code',
            ],
        ]);
        $json = json_decode((string)wp_remote_retrieve_body($resp), true);
        if (empty($json['access_token'])) {
            throw new \RuntimeException('line_token_failed');
        }

        // プロフィール
        $userinfo = wp_remote_get('https://api.line.me/v2/profile', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $json['access_token']],
        ]);
        $u = json_decode((string)wp_remote_retrieve_body($userinfo), true);

        // メールは id_token から取れる場合あり（ここでは簡易に未使用）
        return [
            'provider' => 'line',
            'id'       => (string)($u['userId'] ?? ''),
            'email'    => '', // 取得できないケースが多い。必要なら id_token を検証・復号して使用。
            'name'     => (string)($u['displayName'] ?? ''),
            'given'    => '',
            'family'   => '',
            'picture'  => (string)($u['pictureUrl'] ?? ''),
            'raw'      => $u,
        ];
    }

    /** ユーザー作成／取得 */
    private function upsert_user(string $provider, array $profile): int {
        $meta_key = 'roro_social_' . $provider . '_id';
        $pid = (string)($profile['id'] ?? '');
        if ($pid === '') {
            return 0;
        }

        // 既存検索
        $users = get_users([
            'meta_key'   => $meta_key,
            'meta_value' => $pid,
            'number'     => 1,
            'fields'     => 'ids',
        ]);
        if (!empty($users)) {
            return (int)$users[0];
        }

        // 新規作成
        $email = sanitize_email((string)($profile['email'] ?? ''));
        if ($email === '' || email_exists($email)) {
            // メールが無い/重複する場合は疑似メールを生成
            $email = $provider . '+' . $pid . '@roro.local';
        }

        $login = $provider . '_' . strtolower(wp_generate_password(6, false)) . '_' . substr(md5($pid), 0, 6);
        $pass  = wp_generate_password(20, true, true);

        $user_id = wp_insert_user([
            'user_login'   => $login,
            'user_pass'    => $pass,
            'user_email'   => $email,
            'display_name' => (string)($profile['name'] ?? $provider . ' user'),
            'first_name'   => (string)($profile['given'] ?? ''),
            'last_name'    => (string)($profile['family'] ?? ''),
            'role'         => 'subscriber',
        ]);
        if (is_wp_error($user_id)) {
            return 0;
        }

        update_user_meta($user_id, $meta_key, $pid);
        if (!empty($profile['picture'])) {
            update_user_meta($user_id, 'roro_social_picture', esc_url_raw((string)$profile['picture']));
        }
        update_user_meta($user_id, 'roro_social_provider', $provider);

        return (int)$user_id;
    }
}

endif;
