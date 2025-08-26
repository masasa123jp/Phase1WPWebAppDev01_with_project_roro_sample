<?php
/**
 * Admin UI helpers for RORO magazine.  Provides meta boxes on the
 * issue edit screen allowing authors to define per language page
 * content and insert advertisement cards.  Uses static methods
 * exclusively since WordPress does not instantiate the class.
 */
class RORO_Mag_Admin {

    /**
     * Register meta boxes for the magazine issue post type.  Two
     * boxes are added:
     *  - Pages: a textarea per supported language where editors can
     *    separate pages with the token "---PAGE---".
     *  - Ad cards: a CSV input where each line defines a card
     *    (language, image URL, title, body, target URL, page after
     *    which to insert).  Values are sanitised before storage.
     */
    public static function add_boxes(): void {
        add_meta_box(
            'roro_mag_pages_box',
            __('Magazine Pages (Multilingual)', 'roro-magazine'),
            [ __CLASS__, 'render_pages_box' ],
            'roro_mag_issue',
            'normal',
            'default'
        );
        add_meta_box(
            'roro_mag_ads_box',
            __('Advertisement Cards', 'roro-magazine'),
            [ __CLASS__, 'render_ads_box' ],
            'roro_mag_issue',
            'normal',
            'default'
        );
    }

    /**
     * Render the multilingual pages meta box.  A nonce is output for
     * security and a textarea is shown for each supported language.
     */
    public static function render_pages_box(WP_Post $post): void {
        wp_nonce_field('roro_mag_save', '_roro_mag_nonce');
        $languages = ['ja', 'en', 'zh', 'ko'];
        echo '<p>' . esc_html__( 'Separate pages with the token "---PAGE---".', 'roro-magazine' ) . '</p>';
        echo '<style>.roro-mag-lang textarea{width:100%;min-height:120px;font-family:monospace}</style>';
        echo '<div class="roro-mag-lang">';
        foreach ($languages as $lg) {
            $meta_key = '_roro_mag_pages_' . $lg;
            $value    = (string) get_post_meta($post->ID, $meta_key, true);
            echo '<p><label><strong>' . esc_html(strtoupper($lg)) . '</strong></label><br />';
            echo '<textarea name="roro_mag_pages_' . esc_attr($lg) . '" placeholder="Page1 ---PAGE--- Page2">' . esc_textarea($value) . '</textarea></p>';
        }
        echo '</div>';
    }

    /**
     * Render the advertisement cards meta box.  Instructions are
     * displayed above a textarea that accepts one CSV definition per
     * line.  Each CSV row consists of: language code, image URL,
     * title, body text, target URL and insert_after page number.
     */
    public static function render_ads_box(WP_Post $post): void {
        $raw = (string) get_post_meta($post->ID, '_roro_mag_ads', true);
        $ads = json_decode($raw, true);
        if (!is_array($ads)) {
            $ads = [];
        }
        echo '<p>' . esc_html__( 'Define one ad per line as CSV: lang,image_url,title,body,url,insert_after_page', 'roro-magazine' ) . '</p>';
        echo '<textarea name="roro_mag_ads_csv" style="width:100%;min-height:120px;font-family:monospace;">';
        foreach ($ads as $ad) {
            $line = [
                $ad['lang']  ?? '',
                $ad['image'] ?? '',
                str_replace([",", "\n"], ['、', ' '], $ad['title'] ?? ''),
                str_replace([",", "\n"], ['、', ' '], $ad['body'] ?? ''),
                $ad['url']   ?? '',
                intval($ad['insert_after'] ?? 0),
            ];
            echo esc_html(implode(',', $line)) . "\n";
        }
        echo '</textarea>';
    }

    /**
     * Persist meta box values when an issue is saved.  Ensures
     * capability checks, nonce validation and sanitisation of user
     * input.  Page content is stored as post meta per language and
     * advertisement definitions are normalised into a JSON array.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object.
     */
    public static function save(int $post_id, WP_Post $post): void {
        // Only handle our CPT
        if ($post->post_type !== 'roro_mag_issue') {
            return;
        }
        // Verify nonce
        if (!isset($_POST['_roro_mag_nonce']) || !wp_verify_nonce((string) $_POST['_roro_mag_nonce'], 'roro_mag_save')) {
            return;
        }
        // Check user capability
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        // Persist pages per language
        foreach (['ja', 'en', 'zh', 'ko'] as $lg) {
            $field = 'roro_mag_pages_' . $lg;
            if (isset($_POST[$field])) {
                $val = wp_kses_post((string) $_POST[$field]);
                update_post_meta($post_id, '_roro_mag_pages_' . $lg, $val);
            }
        }
        // Persist advertisements
        if (isset($_POST['roro_mag_ads_csv'])) {
            $csv  = (string) $_POST['roro_mag_ads_csv'];
            $lines= preg_split('/\r?\n/', $csv);
            $ads  = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $cols = str_getcsv($line);
                // Ensure we have at least one column (language)
                if (empty($cols[0])) {
                    continue;
                }
                $ads[] = [
                    'lang'        => sanitize_text_field($cols[0] ?? ''),
                    'image'       => esc_url_raw($cols[1] ?? ''),
                    'title'       => sanitize_text_field($cols[2] ?? ''),
                    'body'        => wp_kses_post($cols[3] ?? ''),
                    'url'         => esc_url_raw($cols[4] ?? ''),
                    'insert_after'=> intval($cols[5] ?? 0),
                ];
            }
            update_post_meta($post_id, '_roro_mag_ads', wp_json_encode($ads));
        }
    }
}