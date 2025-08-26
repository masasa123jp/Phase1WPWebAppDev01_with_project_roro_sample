<?php
/**
 * Shortcode handlers for the MECE RORO Auth plugin.
 *
 * Provides accessible forms for login, sign‑up and (placeholder) profile
 * editing.  Each shortcode enqueues the appropriate scripts and
 * styles and passes translation messages and API endpoints to the
 * front‑end via wp_localize_script.  The actual submit logic lives
 * inside the JavaScript files under assets/js.
 */
class Roro_Auth_Shortcodes {
    /**
     * Register shortcodes.  Called during plugin bootstrap.
     *
     * @return void
     */
    public static function register(): void {
        add_shortcode('roro_login_form', [__CLASS__, 'render_login_form']);
        add_shortcode('roro_signup_form', [__CLASS__, 'render_signup_form']);
        add_shortcode('roro_profile', [__CLASS__, 'render_profile']);
    }

    /**
     * Enqueue common assets for authentication forms.
     *
     * @return void
     */
    private static function enqueue_auth_assets(): void {
        // Style sheet shared by login and sign‑up forms.
        wp_enqueue_style(
            'roro-auth-auth-css',
            RORO_AUTH_URL . 'assets/css/auth.css',
            [],
            RORO_AUTH_VER
        );
    }

    /**
     * Render the login form.
     *
     * @return string
     */
    public static function render_login_form(): string {
        self::enqueue_auth_assets();
        // Register and enqueue login script.
        wp_register_script(
            'roro-auth-login',
            RORO_AUTH_URL . 'assets/js/login.js',
            [],
            RORO_AUTH_VER,
            true
        );
        wp_enqueue_script('roro-auth-login');
        // Localise translation messages and API endpoints to the script.
        wp_localize_script('roro-auth-login', 'RORO_AUTH', [
            'rest' => [
                'login' => rest_url('roro/v1/auth/login'),
            ],
            'i18n' => Roro_Auth_I18n::messages_for_js(),
        ]);
        ob_start();
        ?>
        <div class="roro-auth-form roro-auth-login" id="roro-login-form-wrapper">
            <h2><?php echo esc_html(Roro_Auth_I18n::t('login_title')); ?></h2>
            <form id="roro-login-form">
                <div class="roro-auth-field">
                    <label for="roro_login_username"><?php echo esc_html(Roro_Auth_I18n::t('username')); ?></label>
                    <input type="text" id="roro_login_username" name="username" required aria-required="true" />
                </div>
                <div class="roro-auth-field">
                    <label for="roro_login_password"><?php echo esc_html(Roro_Auth_I18n::t('password')); ?></label>
                    <input type="password" id="roro_login_password" name="password" required aria-required="true" />
                </div>
                <div class="roro-auth-error" role="alert" style="display:none;"></div>
                <button type="submit" class="roro-auth-submit"><?php echo esc_html(Roro_Auth_I18n::t('login_button')); ?></button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the sign‑up form.
     *
     * @return string
     */
    public static function render_signup_form(): string {
        self::enqueue_auth_assets();
        wp_register_script(
            'roro-auth-signup',
            RORO_AUTH_URL . 'assets/js/signup.js',
            [],
            RORO_AUTH_VER,
            true
        );
        wp_enqueue_script('roro-auth-signup');
        wp_localize_script('roro-auth-signup', 'RORO_AUTH', [
            'rest' => [
                'register' => rest_url('roro/v1/auth/register'),
            ],
            'i18n' => Roro_Auth_I18n::messages_for_js(),
        ]);
        ob_start();
        ?>
        <div class="roro-auth-form roro-auth-signup" id="roro-signup-form-wrapper">
            <h2><?php echo esc_html(Roro_Auth_I18n::t('signup_title')); ?></h2>
            <form id="roro-signup-form">
                <div class="roro-auth-field">
                    <label for="roro_signup_username"><?php echo esc_html(Roro_Auth_I18n::t('username')); ?></label>
                    <input type="text" id="roro_signup_username" name="username" required aria-required="true" />
                </div>
                <div class="roro-auth-field">
                    <label for="roro_signup_email"><?php echo esc_html(Roro_Auth_I18n::t('email')); ?></label>
                    <input type="email" id="roro_signup_email" name="email" required aria-required="true" />
                </div>
                <div class="roro-auth-field">
                    <label for="roro_signup_password"><?php echo esc_html(Roro_Auth_I18n::t('password')); ?></label>
                    <input type="password" id="roro_signup_password" name="password" required aria-required="true" />
                </div>
                <div class="roro-auth-field">
                    <label for="roro_signup_display_name"><?php echo esc_html(Roro_Auth_I18n::t('display_name')); ?></label>
                    <input type="text" id="roro_signup_display_name" name="display_name" />
                </div>
                <div class="roro-auth-error" role="alert" style="display:none;"></div>
                <button type="submit" class="roro-auth-submit"><?php echo esc_html(Roro_Auth_I18n::t('signup_button')); ?></button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the profile placeholder.  This can be expanded in a future
     * release to include an editable form and list of pets.  For now
     * it simply displays a message when the user is not logged in or
     * a generic placeholder when logged in.
     *
     * @return string
     */
    public static function render_profile(): string {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html(Roro_Auth_I18n::t('signin_required')) . '</p>';
        }
        // Optionally enqueue profile assets here in the future.
        return '<p>' . esc_html(Roro_Auth_I18n::t('profile_placeholder')) . '</p>';
    }
}