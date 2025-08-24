<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Mag_Service {

    public function register_cpts() {
        // Issue
        register_post_type('roro_mag_issue', [
            'labels' => [
                'name' => __('Magazine Issues','roro-magazine'),
                'singular_name' => __('Magazine Issue','roro-magazine'),
                'add_new_item' => __('Add New Issue','roro-magazine'),
                'edit_item' => __('Edit Issue','roro-magazine'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'menu_icon' => 'dashicons-book-alt',
            'supports' => ['title','editor','thumbnail'],
            'rewrite' => ['slug' => 'mag-issue'],
        ]);

        // Article
        register_post_type('roro_mag_article', [
            'labels' => [
                'name' => __('Magazine Articles','roro-magazine'),
                'singular_name' => __('Magazine Article','roro-magazine'),
                'add_new_item' => __('Add New Article','roro-magazine'),
                'edit_item' => __('Edit Article','roro-magazine'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => false,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title','editor','thumbnail','excerpt'],
            'rewrite' => ['slug' => 'mag-article'],
        ]);
    }

    public function detect_lang() {
        if (isset($_GET['roro_lang'])) {
            $l = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $l = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            if (strpos($locale, 'ja') === 0) $l = 'ja';
            elseif (strpos($locale, 'zh') === 0) $l = 'zh';
            elseif (strpos($locale, 'ko') === 0) $l = 'ko';
            else $l = 'en';
        }
        return in_array($l, ['ja','en','zh','ko'], true) ? $l : 'en';
    }

    public function load_lang($lang) {
        $file = RORO_MAG_PATH . "lang/{$lang}.php";
        if (file_exists($file)) { require $file; if(isset($roro_mag_messages)) return $roro_mag_messages; }
        require RORO_MAG_PATH . "lang/en.php";
        return $roro_mag_messages;
    }

    // ---- Admin helpers ----
    public function get_issues_for_dropdown() {
        $q = new WP_Query([
            'post_type' => 'roro_mag_issue',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        return $q->posts;
    }

    public function resolve_issue_id($value) {
        if (!$value || $value === 'latest') {
            $p = get_posts([ 'post_type' => 'roro_mag_issue', 'numberposts' => 1, 'orderby' => 'date', 'order' => 'DESC' ]);
            return $p ? $p[0]->ID : 0;
        }
        if (is_numeric($value)) return intval($value);
        // YYYY-MM
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            global $wpdb; $meta = $wpdb->prefix.'postmeta';
            $posts = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$meta} WHERE meta_key=%s AND meta_value=%s LIMIT 1",
                '_roro_mag_issue_key', $value
            ));
            return $posts ? intval($posts) : 0;
        }
        // slug
        $post = get_page_by_path(sanitize_title($value), OBJECT, 'roro_mag_issue');
        return $post ? $post->ID : 0;
    }

    // ---- Payload builders ----
    private function meta($post_id, $key, $default = '') {
        $v = get_post_meta($post_id, $key, true);
        return $v !== '' ? $v : $default;
    }

    public function issue_payload($post, $lang) {
        $id = is_object($post) ? $post->ID : intval($post);
        $title = $this->meta($id, "_roro_mag_title_{$lang}", get_the_title($id));
        $summary = $this->meta($id, "_roro_mag_summary_{$lang}", '');
        $issue_key = $this->meta($id, "_roro_mag_issue_key", '');
        $img = get_the_post_thumbnail_url($id, 'large');
        return [
            'id' => $id,
            'title' => $title,
            'summary' => $summary,
            'issue_key' => $issue_key,
            'cover' => $img,
            'permalink' => get_permalink($id)
        ];
    }

    public function article_payload($post, $lang) {
        $id = is_object($post) ? $post->ID : intval($post);
        $title = $this->meta($id, "_roro_mag_title_{$lang}", get_the_title($id));
        $content = $this->meta($id, "_roro_mag_content_{$lang}", '');
        $excerpt = $this->meta($id, "_roro_mag_excerpt_{$lang}", '');
        $issue_id = intval($this->meta($id, "_roro_mag_issue_id", 0));
        $order = intval($this->meta($id, "_roro_mag_order", 0));
        $img = get_the_post_thumbnail_url($id, 'large');
        if (!$content) { $content = apply_filters('the_content', get_post_field('post_content', $id)); }
        if (!$excerpt) { $excerpt = get_the_excerpt($id); }
        return [
            'id' => $id,
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'issue_id' => $issue_id,
            'order' => $order,
            'image' => $img,
            'permalink' => get_permalink($id)
        ];
    }

    public function list_issues($limit = 6, $offset = 0) {
        $q = new WP_Query([
            'post_type' => 'roro_mag_issue',
            'posts_per_page' => intval($limit),
            'offset' => intval($offset),
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        return $q->posts;
    }

    public function list_articles_by_issue($issue_id) {
        $q = new WP_Query([
            'post_type' => 'roro_mag_article',
            'posts_per_page' => -1,
            'meta_key' => '_roro_mag_issue_id',
            'meta_value' => intval($issue_id),
            'orderby' => 'meta_value_num title',
            'order' => 'ASC'
        ]);
        return $q->posts;
    }
}
