<?php
/**
 * REST API controller for the MECE RORO Auth plugin.
 *
 * Exposes a handful of endpoints under the roro/v1 namespace to handle
 * authentication helper actions, breed lookup and pet management.
 * All responses are returned as associative arrays which WP REST will
 * convert to JSON.
 */
class Roro_Auth_REST {
    /**
     * Register our REST routes.  Hook this via rest_api_init.
     *
     * @return void
     */
    public function register_routes(): void {
        add_action('rest_api_init', function () {
            $namespace = 'roro/v1';
            // User login endpoint.
            register_rest_route($namespace, '/auth/login', [
                'methods'  => 'POST',
                'callback' => [$this, 'handle_login'],
                'permission_callback' => '__return_true',
                'args' => [],
            ]);

            // User registration endpoint.
            register_rest_route($namespace, '/auth/register', [
                'methods'  => 'POST',
                'callback' => [$this, 'handle_register'],
                'permission_callback' => '__return_true',
                'args' => [],
            ]);

            // Breed master list.  Publicly accessible.
            register_rest_route($namespace, '/breeds', [
                'methods'  => 'GET',
                'callback' => [$this, 'get_breeds'],
                'permission_callback' => '__return_true',
                'args' => [
                    'locale' => [
                        'type' => 'string',
                        'required' => false,
                    ],
                ],
            ]);

            // Pets CRUD (requires sign‑in).
            register_rest_route($namespace, '/pets', [
                [
                    'methods'  => 'GET',
                    'callback' => [$this, 'get_pets'],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods'  => 'POST',
                    'callback' => [$this, 'save_pets'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            // Representative pet selection.
            register_rest_route($namespace, '/pets/representative', [
                'methods'  => 'POST',
                'callback' => [$this, 'set_representative_pet'],
                'permission_callback' => '__return_true',
            ]);

            // Connected social accounts.  Requires sign‑in.
            register_rest_route($namespace, '/accounts', [
                'methods'  => 'GET',
                'callback' => [$this, 'get_accounts'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    /**
     * Return the breed master list for the requested locale.
     *
     * Data files live under assets/data and are named breeds-{lang}.json.
     * If the file does not exist the English file is used as a fallback.
     *
     * @param WP_REST_Request $req
     * @return array<int,string>
     */
    public function get_breeds(WP_REST_Request $req): array {
        $locale = sanitize_text_field($req->get_param('locale') ?: get_locale());
        $lang   = substr($locale, 0, 2);
        $map = [
            'ja' => 'breeds-ja.json',
            'en' => 'breeds-en.json',
            'zh' => 'breeds-zh.json',
            'ko' => 'breeds-ko.json',
        ];
        $file = RORO_AUTH_DIR . 'assets/data/' . ($map[$lang] ?? 'breeds-en.json');
        if (!file_exists($file)) {
            $file = RORO_AUTH_DIR . 'assets/data/breeds-en.json';
        }
        $json = file_get_contents($file);
        $list = json_decode($json, true);
        return is_array($list) ? $list : [];
    }

    /**
     * Fetch the pets for the current user.
     *
     * Requires that the user is logged in.  If not, returns a 401 error.
     *
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public function get_pets(): mixed {
        if (!is_user_logged_in()) {
            return new WP_Error('forbidden', Roro_Auth_I18n::t('signin_required'), ['status' => 401]);
        }
        return Roro_Auth_Pets::get_pets(get_current_user_id());
    }

    /**
     * Save the pets for the current user.
     *
     * The request body should be JSON with a `pets` property containing an
     * array of pet definitions.  A valid nonce (via the X-WP-Nonce
     * header) is required because this is a write operation.  The user
     * must be logged in.
     *
     * @param WP_REST_Request $req
     * @return array|WP_Error
     */
    public function save_pets(WP_REST_Request $req) {
        if (!is_user_logged_in()) {
            return new WP_Error('forbidden', Roro_Auth_I18n::t('signin_required'), ['status' => 401]);
        }
        // Verify nonce for CSRF.  The wp_rest nonce should be sent via header.
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('forbidden', Roro_Auth_I18n::t('invalid_nonce'), ['status' => 403]);
        }
        $params = $req->get_json_params();
        $pets   = is_array($params['pets'] ?? null) ? $params['pets'] : [];
        Roro_Auth_Pets::save_pets(get_current_user_id(), $pets);
        return [
            'success' => true,
            'message' => Roro_Auth_I18n::t('pets_saved'),
        ];
    }

    /**
     * Set the representative pet for the current user.
     *
     * Expects a `pet_id` parameter in the request body.  Requires login
     * and a valid nonce.  Returns the new representative id on success.
     *
     * @param WP_REST_Request $req
     * @return array|WP_Error
     */
    public function set_representative_pet(WP_REST_Request $req) {
        if (!is_user_logged_in()) {
            return new WP_Error('forbidden', Roro_Auth_I18n::t('signin_required'), ['status' => 401]);
        }
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('forbidden', Roro_Auth_I18n::t('invalid_nonce'), ['status' => 403]);
        }
        $pet_id = sanitize_text_field($req->get_param('pet_id') ?? '');
        if ($pet_id === '') {
            return new WP_Error('bad_request', Roro_Auth_I18n::t('error_required'), ['status' => 400]);
        }
        try {
            Roro_Auth_Pets::set_representative(get_current_user_id(), $pet_id);
        } catch (Throwable $e) {
            return new WP_Error('bad_request', $e->getMessage(), ['status' => 400]);
        }
        return [
            'success'   => true,
            'rep_pet_id'=> $pet_id,
        ];
    }

    /**
     * Return the list of connected social accounts for the current user.
     *
     * If the user is not logged in a 401 error is returned.  Each provider
     * entry is keyed by provider name (e.g. `google`, `line`) and the value
     * is the provider specific identifier.  A missing provider key implies
     * the account is not connected.
     *
     * @return array<string,string>|WP_Error
     */
    public function get_accounts() {
        if (!is_user_logged_in()) {
            return new WP_Error('forbidden', Roro_Auth_I18n::t('signin_required'), ['status' => 401]);
        }
        $uid = get_current_user_id();
        $accounts = (array) get_user_meta($uid, 'roro_auth_accounts', true);
        return $accounts;
    }

    /**
     * Handle user login.
     *
     * Expects a JSON body containing `username` and `password`.  On success the
     * current user and auth cookies are set and a success message is
     * returned.  On failure a WP_Error with an appropriate status code
     * is returned.  We deliberately do not expose error specifics for
     * security reasons.
     *
     * @param WP_REST_Request $req
     * @return array|WP_Error
     */
    public function handle_login(WP_REST_Request $req) {
        $params = $req->get_json_params();
        if (!is_array($params)) {
            return new WP_Error('invalid_request', Roro_Auth_I18n::t('error_required'), ['status' => 400]);
        }
        $username = sanitize_user($params['username'] ?? '');
        $password = $params['password'] ?? '';
        if ($username === '' || $password === '') {
            return new WP_Error('missing_credentials', Roro_Auth_I18n::t('error_required'), ['status' => 400]);
        }
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error('authentication_failed', Roro_Auth_I18n::t('error_login_failed'), ['status' => 401]);
        }
        // Successful authentication.  Establish the current user and auth cookies.
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        return [
            'success' => true,
            'message' => Roro_Auth_I18n::t('login_success'),
            'user'    => [
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
            ],
        ];
    }

    /**
     * Handle user registration.
     *
     * Expects a JSON body containing `username`, `email` and `password`.  An
     * optional `display_name` can also be provided.  Basic validation is
     * performed on the email address and password length.  If the
     * registration succeeds the user is also logged in.  Duplicate
     * usernames or email addresses result in an error.
     *
     * @param WP_REST_Request $req
     * @return array|WP_Error
     */
    public function handle_register(WP_REST_Request $req) {
        $params = $req->get_json_params();
        if (!is_array($params)) {
            return new WP_Error('invalid_request', Roro_Auth_I18n::t('error_required'), ['status' => 400]);
        }
        $username = sanitize_user($params['username'] ?? '');
        $email    = sanitize_email($params['email'] ?? '');
        $password = (string) ($params['password'] ?? '');
        $display  = sanitize_text_field($params['display_name'] ?? '');
        // Validate required fields.
        if ($username === '' || $email === '' || $password === '') {
            return new WP_Error('missing_required', Roro_Auth_I18n::t('error_required'), ['status' => 400]);
        }
        if (!is_email($email)) {
            return new WP_Error('invalid_email', Roro_Auth_I18n::t('error_invalid_email'), ['status' => 400]);
        }
        if (strlen($password) < 8) {
            return new WP_Error('weak_password', Roro_Auth_I18n::t('error_password_policy'), ['status' => 400]);
        }
        if (username_exists($username)) {
            return new WP_Error('username_exists', Roro_Auth_I18n::t('error_username_exists'), ['status' => 409]);
        }
        if (email_exists($email)) {
            return new WP_Error('email_exists', Roro_Auth_I18n::t('error_email_exists'), ['status' => 409]);
        }
        // Create user.
        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display === '' ? $username : $display,
            'role'         => get_option('default_role', 'subscriber'),
        ]);
        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed', Roro_Auth_I18n::t('signup_failed'), ['status' => 500]);
        }
        // Automatically log the new user in.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        return [
            'success' => true,
            'message' => Roro_Auth_I18n::t('signup_success'),
            'user'    => [
                'id'           => $user_id,
                'display_name' => $display === '' ? $username : $display,
                'email'        => $email,
            ],
        ];
    }
}