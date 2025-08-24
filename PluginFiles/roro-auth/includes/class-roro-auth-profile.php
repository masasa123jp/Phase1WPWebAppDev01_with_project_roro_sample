<?php
if (!defined('ABSPATH')) exit;

class RORO_Auth_Profile {
    private static $instance = null;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // ショートコード
        add_shortcode('roro_profile', [$this, 'shortcode_profile']);

        // アクション（admin-post, ノンAJAX）
        add_action('admin_post_roro_profile_update', [$this, 'handle_profile_update']);
        add_action('admin_post_roro_pet_create',     [$this, 'handle_pet_create']);
        add_action('admin_post_roro_pet_update',     [$this, 'handle_pet_update']);
        add_action('admin_post_roro_pet_delete',     [$this, 'handle_pet_delete']);
    }

    /*** ========== 共通ユーティリティ ========== ***/

    private function ensure_logged_in_then_redirect() {
        if (!is_user_logged_in()) {
            $redir = wp_login_url(add_query_arg([], $this->current_url()));
            wp_redirect($redir); exit;
        }
        if (!current_user_can('read')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'default'));
        }
    }

    private function current_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        return esc_url_raw($scheme . $host . $_SERVER['REQUEST_URI']);
    }

    private function get_customer_id_for_user($user_id) {
        // メタが無ければリンク作成（DB存在時）
        $meta = get_user_meta($user_id, 'roro_customer_id', true);
        if (!$meta) {
            $user = get_user_by('id', $user_id);
            RORO_Auth_Utils::ensure_customer_link($user_id, $user ? $user->user_email : '', $user ? $user->display_name : '');
            $meta = get_user_meta($user_id, 'roro_customer_id', true);
        }
        return $meta ? (int)$meta : 0;
    }

    private function table($name) {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    private function table_exists($name) {
        return RORO_Auth_Utils::table_exists($this->table($name));
    }

    private function get_columns($name) {
        return RORO_Auth_Utils::get_table_columns($this->table($name));
    }

    /*** ========== 画面 ========== ***/

    public function shortcode_profile($atts = []) {
        $this->ensure_logged_in_then_redirect();

        wp_enqueue_style('roro-profile', RORO_AUTH_PLUGIN_URL . 'assets/css/roro-profile.css', [], RORO_AUTH_VERSION);
        wp_enqueue_script('roro-profile', RORO_AUTH_PLUGIN_URL . 'assets/js/roro-profile.js', ['jquery'], RORO_AUTH_VERSION, true);
        wp_localize_script('roro-profile', 'RORO_PROFILE_LOC', [
            'i18n' => RORO_Auth_Utils::messages_js(),
            'confirm_delete' => RORO_Auth_Utils::t('pet_confirm_delete'),
        ]);

        $messages = RORO_Auth_Utils::messages();
        $flash = RORO_Auth_Utils::consume_flash();
        $user  = wp_get_current_user();

        // 現在言語
        $lang  = get_user_meta($user->ID, 'roro_auth_locale', true);
        if (!$lang) $lang = RORO_Auth_Utils::current_lang();

        // プロフィール画像URL（ユーザーメタ優先、なければ avatar メタ）
        $avatar_id = (int)get_user_meta($user->ID, 'roro_profile_avatar_id', true);
        $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
        if (!$avatar_url) $avatar_url = get_user_meta($user->ID, 'roro_auth_avatar', true);

        // 住所/連絡先（ユーザーメタ）
        $meta_keys = [
            'roro_phone','roro_postal','roro_region','roro_city','roro_address1','roro_address2','roro_country'
        ];
        $user_meta = [];
        foreach ($meta_keys as $k) $user_meta[$k] = get_user_meta($user->ID, $k, true);

        // ペット一覧（DB優先。なければメタ）
        $pets = $this->fetch_pets_for_user($user->ID);

        // 犬種マスタ
        $breed_options = $this->fetch_breed_master_grouped(); // ['dog'=>[['id'=>..,'name'=>..], ...], 'cat'=>...]

        ob_start();
        $template = RORO_AUTH_PLUGIN_DIR . 'templates/profile.php';
        include $template;
        return ob_get_clean();
    }

    /*** ========== ハンドラ ========== ***/

    public function handle_profile_update() {
        $this->ensure_logged_in_then_redirect();
        if (!isset($_POST['_roro_profile_nonce']) || !wp_verify_nonce($_POST['_roro_profile_nonce'], 'roro_profile_update')) {
            wp_die(esc_html__('Security check failed', 'default'));
        }
        $user_id = get_current_user_id();
        $i18n = 'RORO_AUTH_UTILS';

        // 入力値
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $email        = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $lang         = isset($_POST['lang']) ? sanitize_key($_POST['lang']) : 'ja';

        $phone        = isset($_POST['roro_phone']) ? sanitize_text_field($_POST['roro_phone']) : '';
        $postal       = isset($_POST['roro_postal']) ? sanitize_text_field($_POST['roro_postal']) : '';
        $region       = isset($_POST['roro_region']) ? sanitize_text_field($_POST['roro_region']) : '';
        $city         = isset($_POST['roro_city']) ? sanitize_text_field($_POST['roro_city']) : '';
        $address1     = isset($_POST['roro_address1']) ? sanitize_text_field($_POST['roro_address1']) : '';
        $address2     = isset($_POST['roro_address2']) ? sanitize_text_field($_POST['roro_address2']) : '';
        $country      = isset($_POST['roro_country']) ? sanitize_text_field($_POST['roro_country']) : '';

        // WPユーザー更新
        if ($display_name) wp_update_user(['ID'=>$user_id,'display_name'=>$display_name,'nickname'=>$display_name]);
        if ($email && is_email($email)) {
            $exists = get_user_by('email', $email);
            $me = wp_get_current_user();
            if (!$exists || $exists->ID == $me->ID) {
                wp_update_user(['ID'=>$user_id,'user_email'=>$email]);
            } else {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('error_email_exists'));
                wp_redirect($this->safe_redirect_back()); exit;
            }
        }
        update_user_meta($user_id, 'roro_auth_locale', $lang);
        update_user_meta($user_id, 'roro_phone', $phone);
        update_user_meta($user_id, 'roro_postal', $postal);
        update_user_meta($user_id, 'roro_region', $region);
        update_user_meta($user_id, 'roro_city', $city);
        update_user_meta($user_id, 'roro_address1', $address1);
        update_user_meta($user_id, 'roro_address2', $address2);
        update_user_meta($user_id, 'roro_country', $country);

        // 画像アップロード（任意）
        if (!empty($_FILES['avatar']) && !empty($_FILES['avatar']['name'])) {
            $attachment_id = $this->handle_upload($_FILES['avatar']);
            if (is_wp_error($attachment_id)) {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('upload_failed') . ' - ' . $attachment_id->get_error_message());
                wp_redirect($this->safe_redirect_back()); exit;
            }
            update_user_meta($user_id, 'roro_profile_avatar_id', (int)$attachment_id);
        }

        // RORO_CUSTOMER 連携（存在時のみ）
        $this->sync_customer_row($user_id, [
            'email'=>$email, 'full_name'=>$display_name, 'phone'=>$phone,
            'postal_code'=>$postal, 'region'=>$region, 'city'=>$city,
            'address1'=>$address1, 'address2'=>$address2, 'country'=>$country,
            'lang'=>$lang, 'avatar_url'=>$this->user_avatar_url($user_id),
        ]);

        RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('profile_saved'));
        wp_redirect($this->safe_redirect_back()); exit;
    }

    public function handle_pet_create() {
        $this->ensure_logged_in_then_redirect();
        if (!isset($_POST['_roro_pet_nonce']) || !wp_verify_nonce($_POST['_roro_pet_nonce'], 'roro_pet_create')) {
            wp_die(esc_html__('Security check failed', 'default'));
        }
        $user_id = get_current_user_id();
        $customer_id = $this->get_customer_id_for_user($user_id);

        $name    = isset($_POST['pet_name']) ? sanitize_text_field($_POST['pet_name']) : '';
        $species = isset($_POST['pet_species']) ? sanitize_key($_POST['pet_species']) : '';
        $breed   = isset($_POST['pet_breed']) ? sanitize_text_field($_POST['pet_breed']) : '';
        $breed_id= isset($_POST['pet_breed_id']) ? (int)$_POST['pet_breed_id'] : 0;
        $sex     = isset($_POST['pet_sex']) ? sanitize_key($_POST['pet_sex']) : '';
        $birth   = isset($_POST['pet_birthdate']) ? sanitize_text_field($_POST['pet_birthdate']) : '';
        $weight  = isset($_POST['pet_weight']) ? floatval($_POST['pet_weight']) : 0;

        $picture_url = '';
        if (!empty($_FILES['pet_avatar']) && !empty($_FILES['pet_avatar']['name'])) {
            $attachment_id = $this->handle_upload($_FILES['pet_avatar']);
            if (!is_wp_error($attachment_id)) {
                $picture_url = wp_get_attachment_image_url($attachment_id, 'medium');
            }
        }

        // DB優先（なければユーザーメタに追記）
        if ($this->table_exists('RORO_PET')) {
            global $wpdb;
            $table = $this->table('RORO_PET');
            $cols  = $this->get_columns('RORO_PET');
            $data  = []; $fmt = [];

            $candidates = [
                'wp_user_id'   => $user_id,
                'customer_id'  => $customer_id ?: null,
                'name'         => $name,
                'species'      => $species,
                'breed_id'     => $breed_id ?: null,
                'breed_name'   => $breed ?: null,
                'sex'          => $sex ?: null,
                'birthdate'    => $birth ?: null,
                'weight'       => $weight ?: null,
                'picture_url'  => $picture_url ?: null,
                'is_primary'   => 0,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ];
            foreach ($candidates as $k=>$v) {
                if (in_array($k, $cols, true) && $v !== null) { $data[$k]=$v; $fmt[] = is_int($v) ? '%d' : (is_float($v) ? '%f' : '%s'); }
            }
            $ok = $wpdb->insert($table, $data, $fmt);
            if ($ok !== false) {
                // 代表ペット未設定ならセット
                if ($this->table_exists('RORO_CUSTOMER') && $customer_id) {
                    $ccols = $this->get_columns('RORO_CUSTOMER');
                    if (in_array('default_pet_id', $ccols, true)) {
                        $pet_id = (int)$wpdb->insert_id;
                        $has_default = (int)$wpdb->get_var($wpdb->prepare("SELECT default_pet_id FROM {$this->table('RORO_CUSTOMER')} WHERE id=%d", $customer_id));
                        if (!$has_default) {
                            $wpdb->update($this->table('RORO_CUSTOMER'), ['default_pet_id'=>$pet_id,'updated_at'=>current_time('mysql')], ['id'=>$customer_id], ['%d','%s'], ['%d']);
                        }
                    }
                }
                RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('pet_saved'));
            } else {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('pet_save_failed'));
            }
        } else {
            // メタ（配列）に保存
            $pets = get_user_meta($user_id, 'roro_pets', true);
            if (!is_array($pets)) $pets = [];
            $pets[] = [
                'id'         => 'meta_' . time(),
                'name'       => $name,
                'species'    => $species,
                'breed_name' => $breed,
                'sex'        => $sex,
                'birthdate'  => $birth,
                'weight'     => $weight,
                'picture_url'=> $picture_url,
            ];
            update_user_meta($user_id, 'roro_pets', $pets);
            RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('pet_saved'));
        }

        wp_redirect($this->safe_redirect_back()); exit;
    }

    public function handle_pet_update() {
        $this->ensure_logged_in_then_redirect();
        if (!isset($_POST['_roro_pet_nonce']) || !wp_verify_nonce($_POST['_roro_pet_nonce'], 'roro_pet_update')) {
            wp_die(esc_html__('Security check failed', 'default'));
        }
        $user_id = get_current_user_id();

        $pid     = isset($_POST['pet_id']) ? sanitize_text_field($_POST['pet_id']) : '';
        $name    = isset($_POST['pet_name']) ? sanitize_text_field($_POST['pet_name']) : '';
        $species = isset($_POST['pet_species']) ? sanitize_key($_POST['pet_species']) : '';
        $breed   = isset($_POST['pet_breed']) ? sanitize_text_field($_POST['pet_breed']) : '';
        $breed_id= isset($_POST['pet_breed_id']) ? (int)$_POST['pet_breed_id'] : 0;
        $sex     = isset($_POST['pet_sex']) ? sanitize_key($_POST['pet_sex']) : '';
        $birth   = isset($_POST['pet_birthdate']) ? sanitize_text_field($_POST['pet_birthdate']) : '';
        $weight  = isset($_POST['pet_weight']) ? floatval($_POST['pet_weight']) : 0;

        $picture_url = '';
        if (!empty($_FILES['pet_avatar']) && !empty($_FILES['pet_avatar']['name'])) {
            $attachment_id = $this->handle_upload($_FILES['pet_avatar']);
            if (!is_wp_error($attachment_id)) {
                $picture_url = wp_get_attachment_image_url($attachment_id, 'medium');
            }
        }

        if ($this->table_exists('RORO_PET') && is_numeric($pid)) {
            global $wpdb;
            $table = $this->table('RORO_PET');
            $cols  = $this->get_columns('RORO_PET');

            // ID列名の推定
            $id_col = in_array('id', $cols, true) ? 'id' : (in_array('pet_id', $cols, true) ? 'pet_id' : null);
            if (!$id_col) { RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('pet_save_failed')); wp_redirect($this->safe_redirect_back()); exit; }

            $data  = []; $fmt = [];
            $candidates = [
                'name'=>$name,'species'=>$species,
                'breed_id'=>$breed_id ?: null,'breed_name'=>$breed ?: null,
                'sex'=>$sex ?: null,'birthdate'=>$birth ?: null,'weight'=>$weight ?: null,
                'updated_at'=> current_time('mysql'),
            ];
            if ($picture_url) $candidates['picture_url'] = $picture_url;
            foreach ($candidates as $k=>$v) {
                if (in_array($k, $cols, true) && $v !== null) { $data[$k]=$v; $fmt[] = is_int($v) ? '%d' : (is_float($v) ? '%f' : '%s'); }
            }
            $where = [$id_col => (int)$pid];
            $where_fmt = ['%d'];
            $ok = $wpdb->update($table, $data, $where, $fmt, $where_fmt);
            if ($ok !== false) {
                RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('pet_saved'));
            } else {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('pet_save_failed'));
            }
        } else {
            // メタ更新
            $pets = get_user_meta($user_id, 'roro_pets', true);
            if (!is_array($pets)) $pets = [];
            foreach ($pets as &$p) {
                if (!empty($p['id']) && $p['id'] == $pid) {
                    $p['name']=$name; $p['species']=$species; $p['breed_name']=$breed;
                    $p['sex']=$sex; $p['birthdate']=$birth; $p['weight']=$weight;
                    if ($picture_url) $p['picture_url']=$picture_url;
                    break;
                }
            }
            update_user_meta($user_id, 'roro_pets', $pets);
            RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('pet_saved'));
        }

        wp_redirect($this->safe_redirect_back()); exit;
    }

    public function handle_pet_delete() {
        $this->ensure_logged_in_then_redirect();
        if (!isset($_POST['_roro_pet_nonce']) || !wp_verify_nonce($_POST['_roro_pet_nonce'], 'roro_pet_delete')) {
            wp_die(esc_html__('Security check failed', 'default'));
        }
        $user_id = get_current_user_id();
        $pid     = isset($_POST['pet_id']) ? sanitize_text_field($_POST['pet_id']) : '';

        if ($this->table_exists('RORO_PET') && is_numeric($pid)) {
            global $wpdb;
            $table = $this->table('RORO_PET');
            $cols  = $this->get_columns('RORO_PET');
            $id_col = in_array('id', $cols, true) ? 'id' : (in_array('pet_id', $cols, true) ? 'pet_id' : null);
            if (!$id_col) { RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('pet_delete_failed')); wp_redirect($this->safe_redirect_back()); exit; }
            $ok = $wpdb->delete($table, [$id_col=>(int)$pid], ['%d']);
            if ($ok !== false) {
                RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('pet_deleted'));
            } else {
                RORO_Auth_Utils::flash('error', RORO_Auth_Utils::t('pet_delete_failed'));
            }
        } else {
            $pets = get_user_meta($user_id, 'roro_pets', true);
            if (!is_array($pets)) $pets = [];
            $new = [];
            foreach ($pets as $p) if ($p['id'] != $pid) $new[] = $p;
            update_user_meta($user_id, 'roro_pets', $new);
            RORO_Auth_Utils::flash('success', RORO_Auth_Utils::t('pet_deleted'));
        }

        wp_redirect($this->safe_redirect_back()); exit;
    }

    /*** ========== データ取得系 ========== ***/

    private function fetch_pets_for_user($user_id) {
        if ($this->table_exists('RORO_PET')) {
            global $wpdb;
            $table = $this->table('RORO_PET');
            $cols  = $this->get_columns('RORO_PET');
            // 優先的に wp_user_id で絞り込み、無ければ customer_id
            $where = '1=1'; $args = [];
            if (in_array('wp_user_id', $cols, true)) {
                $where = 'wp_user_id = %d'; $args[] = $user_id;
            } elseif (in_array('customer_id', $cols, true)) {
                $cid = $this->get_customer_id_for_user($user_id);
                $where = 'customer_id = %d'; $args[] = $cid ?: 0;
            }
            $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY ";
            $sql .= in_array('updated_at',$cols,true) ? 'updated_at DESC' : (in_array('id',$cols,true) ? 'id DESC' : '1');
            $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
            // id列名揃え
            foreach ($rows as &$r) {
                if (!isset($r['id']) && isset($r['pet_id'])) $r['id'] = $r['pet_id'];
            }
            return $rows ?: [];
        } else {
            $pets = get_user_meta($user_id, 'roro_pets', true);
            return is_array($pets) ? $pets : [];
        }
    }

    private function fetch_breed_master_grouped() {
        $result = ['dog'=>[],'cat'=>[],'other'=>[]];
        if ($this->table_exists('RORO_BREED_MASTER')) {
            global $wpdb;
            $table = $this->table('RORO_BREED_MASTER');
            $cols  = $this->get_columns('RORO_BREED_MASTER');
            // 想定列: id, species, breed_name, locale など
            $select = [];
            $select[] = in_array('id',$cols,true) ? 'id' : '0 AS id';
            $select[] = in_array('species',$cols,true) ? 'species' : "'' AS species";
            $select[] = in_array('breed_name',$cols,true) ? 'breed_name' : "'' AS breed_name";
            $sql = "SELECT ".implode(',', $select)." FROM {$table} LIMIT 1000";
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($rows) {
                foreach ($rows as $r) {
                    $sp = strtolower($r['species'] ?: 'other');
                    if (!isset($result[$sp])) $sp = 'other';
                    $result[$sp][] = ['id'=>(int)$r['id'],'name'=>$r['breed_name']];
                }
            }
        } else {
            // フォールバックの簡易候補
            $result['dog'] = [
                ['id'=>101,'name'=>'Shiba Inu'],
                ['id'=>102,'name'=>'Toy Poodle'],
                ['id'=>103,'name'=>'Golden Retriever'],
            ];
            $result['cat'] = [
                ['id'=>201,'name'=>'Scottish Fold'],
                ['id'=>202,'name'=>'American Shorthair'],
                ['id'=>203,'name'=>'Munchkin'],
            ];
        }
        return $result;
    }

    private function user_avatar_url($user_id) {
        $aid = (int)get_user_meta($user_id, 'roro_profile_avatar_id', true);
        if ($aid) {
            $u = wp_get_attachment_image_url($aid, 'thumbnail'); if ($u) return $u;
        }
        $u = get_user_meta($user_id, 'roro_auth_avatar', true);
        return $u ?: '';
    }

    private function safe_redirect_back() {
        $back = isset($_POST['_roro_redirect']) ? esc_url_raw($_POST['_roro_redirect']) : home_url('/');
        return $back ?: home_url('/');
    }

    /*** ========== 同期/アップロード ========== ***/

    private function sync_customer_row($user_id, $map) {
        // テーブル無ければ何もしない
        if (!$this->table_exists('RORO_CUSTOMER')) return;
        global $wpdb;
        $table = $this->table('RORO_CUSTOMER');
        $cols  = $this->get_columns('RORO_CUSTOMER');

        // 既存行の特定（email or wp_user_id）
        $id = 0;
        if (in_array('wp_user_id', $cols, true)) {
            $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE wp_user_id = %d LIMIT 1", $user_id));
        }
        if (!$id && in_array('email', $cols, true) && !empty($map['email'])) {
            $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE email = %s LIMIT 1", $map['email']));
        }

        // 更新データ構築
        $data = []; $fmt = [];
        $candidates = [
            'wp_user_id'   => $user_id,
            'email'        => $map['email'] ?? '',
            'full_name'    => $map['full_name'] ?? '',
            'display_name' => $map['full_name'] ?? '',
            'phone'        => $map['phone'] ?? '',
            'postal_code'  => $map['postal_code'] ?? '',
            'region'       => $map['region'] ?? '',
            'city'         => $map['city'] ?? '',
            'address1'     => $map['address1'] ?? '',
            'address2'     => $map['address2'] ?? '',
            'country'      => $map['country'] ?? '',
            'lang'         => $map['lang'] ?? '',
            'avatar_url'   => $map['avatar_url'] ?? '',
            'updated_at'   => current_time('mysql'),
        ];
        foreach ($candidates as $k=>$v) {
            if (in_array($k, $cols, true)) { $data[$k]=$v; $fmt[] = is_int($v) ? '%d' : '%s'; }
        }

        if ($id) {
            $wpdb->update($table, $data, ['id'=>$id], $fmt, ['%d']);
            update_user_meta($user_id, 'roro_customer_id', $id);
        } else {
            $data['created_at'] = current_time('mysql');
            if (!in_array('created_at', $cols, true)) unset($data['created_at']);
            $fmt_ins = $fmt; if (isset($data['created_at'])) $fmt_ins[] = '%s';
            $ins = $wpdb->insert($table, $data, $fmt_ins);
            if ($ins !== false) {
                $id = (int)$wpdb->insert_id;
                update_user_meta($user_id, 'roro_customer_id', $id);
            }
        }
    }

    private function handle_upload($file) {
        if (empty($file['name'])) return new WP_Error('no_file', 'No file');
        // 簡易タイプチェック
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            return new WP_Error('invalid_type', RORO_Auth_Utils::t('upload_invalid_type'));
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $overrides = ['test_form'=>false];
        $moved = wp_handle_upload($file, $overrides);
        if (isset($moved['error'])) return new WP_Error('upload_failed', $moved['error']);
        $attachment = [
            'post_mime_type' => $moved['type'],
            'post_title'     => sanitize_file_name($moved['file']),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $moved['file']);
        if (is_wp_error($attach_id)) return $attach_id;
        $attach_data = wp_generate_attachment_metadata($attach_id, $moved['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        return $attach_id;
    }
}
