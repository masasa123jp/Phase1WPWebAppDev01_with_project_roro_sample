<?php
/**
 * Core service layer for the RORO magazine.  This class encapsulates
 * business logic that is shared across the admin, REST and shortcode
 * components: registering custom post types, detecting the current
 * language, loading localised message strings, resolving issue
 * identifiers, composing magazine decks and building payloads for
 * output.  Keeping these concerns in one place encourages
 * reusability and avoids duplication of logic across the plugin.
 */
class RORO_Mag_Service {

    /**
     * Register custom post types for magazine issues and articles.
     *
     * - roro_mag_issue: represents a single magazine issue.  Each
     *   issue can have a title, description, cover image and per
     *   language page content and ad cards managed via meta boxes.
     * - roro_mag_article: optional granular articles within an
     *   issue.  Articles can include an excerpt, full content and
     *   thumbnail image.  While the page turning viewer uses pages
     *   defined in the issue meta, the article CPT allows visitors
     *   to read stand alone pieces via a dedicated shortcode.
     */
    public function register_cpts(): void {
        // Magazine issue post type
        register_post_type('roro_mag_issue', [
            'labels' => [
                'name'          => __('Magazine Issues', 'roro-magazine'),
                'singular_name' => __('Magazine Issue', 'roro-magazine'),
                'add_new_item'  => __('Add New Issue', 'roro-magazine'),
                'edit_item'     => __('Edit Issue', 'roro-magazine'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'has_archive'  => false,
            'menu_icon'    => 'dashicons-book-alt',
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'rewrite'      => ['slug' => 'mag-issue'],
        ]);

        // Article post type
        register_post_type('roro_mag_article', [
            'labels' => [
                'name'          => __('Magazine Articles', 'roro-magazine'),
                'singular_name' => __('Magazine Article', 'roro-magazine'),
                'add_new_item'  => __('Add New Article', 'roro-magazine'),
                'edit_item'     => __('Edit Article', 'roro-magazine'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'has_archive'  => false,
            'menu_icon'    => 'dashicons-media-document',
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'rewrite'      => ['slug' => 'mag-article'],
        ]);
    }

    /**
     * Determine the preferred language for the current request.  The
     * order of preference is:
     *
     * 1. A `roro_lang` query parameter, allowing explicit
     *    per‑request overrides.
     * 2. A `roro_lang` cookie, persisting a user selection across
     *    pages.
     * 3. The WordPress locale, mapped to one of the supported
     *    languages.
     *
     * If the resolved language is not recognised then English is used
     * as a sensible default.  Supported language codes should map to
     * existing files in the `lang` directory.
     */
    public function detect_lang(): string {
        $lang = null;
        if (isset($_GET['roro_lang'])) {
            $lang = sanitize_text_field((string) $_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $lang = sanitize_text_field((string) $_COOKIE['roro_lang']);
        } else {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            if (strpos($locale, 'ja') === 0) {
                $lang = 'ja';
            } elseif (strpos($locale, 'zh') === 0) {
                $lang = 'zh';
            } elseif (strpos($locale, 'ko') === 0) {
                $lang = 'ko';
            } else {
                $lang = 'en';
            }
        }
        // Whitelist supported languages
        return in_array($lang, ['ja', 'en', 'zh', 'ko'], true) ? $lang : 'en';
    }

    /**
     * Load the message dictionary for a given language.  Each file in
     * the `lang` directory returns an array `$roro_mag_messages` of
     * keys to translated phrases.  If the requested language does
     * not have a corresponding file then English is used as a
     * fallback.  Message files are small and inexpensive to load.
     *
     * @param string $lang Two letter language code (ja, en, zh, ko).
     * @return array<string,string> The translation map.
     */
    public function load_messages(string $lang): array {
        $dir  = RORO_MAG_PLUGIN_DIR . 'lang/';
        $file = $dir . $lang . '.php';
        if (!file_exists($file)) {
            $file = $dir . 'en.php';
        }
        /** @psalm-suppress UnresolvableInclude */
        require $file;
        return isset($roro_mag_messages) && is_array($roro_mag_messages) ? $roro_mag_messages : [];
    }

    /**
     * Resolve an issue reference to a numeric post ID.  Issue values
     * may be one of the following:
     *
     * - An integer ID.
     * - The string `latest` to fetch the most recent published issue.
     * - A year‑month string in the format YYYY‑MM matched against
     *   the `_roro_mag_issue_key` post meta.
     * - A slug string used to look up an issue by its post_name.
     *
     * Returns zero if no matching issue can be found.
     *
     * @param mixed $value
     */
    public function resolve_issue_id($value): int {
        if (!$value || $value === 'latest') {
            $posts = get_posts([
                'post_type'      => 'roro_mag_issue',
                'numberposts'    => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            ]);
            return $posts ? intval($posts[0]->ID) : 0;
        }
        if (is_numeric($value)) {
            return intval($value);
        }
        // YYYY-MM pattern
        if (preg_match('/^\d{4}-\d{2}$/', (string) $value)) {
            global $wpdb;
            $meta_table = $wpdb->prefix . 'postmeta';
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$meta_table} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                '_roro_mag_issue_key',
                $value
            ));
            return $post_id ? intval($post_id) : 0;
        }
        // Slug lookup
        $post = get_page_by_path(sanitize_title((string) $value), OBJECT, 'roro_mag_issue');
        return $post ? intval($post->ID) : 0;
    }

    /**
     * Retrieve an array of page strings for an issue in a given
     * language.  Pages are stored in a single post meta field
     * `_roro_mag_pages_{lang}` with triple dash separators `---PAGE---`.
     * If the requested language is not available the Japanese
     * version is used as a fallback.  Leading and trailing blank
     * lines are trimmed and empty pages removed.
     *
     * @param int    $issue_id
     * @param string $lang
     * @return string[] Each element contains raw HTML for a page.
     */
    public function get_issue_pages(int $issue_id, string $lang): array {
        $meta_key = '_roro_mag_pages_' . $lang;
        $raw = (string) get_post_meta($issue_id, $meta_key, true);
        // Fallback to Japanese if nothing set in requested language
        if ($raw === '' && $lang !== 'ja') {
            $raw = (string) get_post_meta($issue_id, '_roro_mag_pages_ja', true);
        }
        if ($raw === '') {
            return [];
        }
        $pages = preg_split('/---PAGE---/u', $raw);
        if (!is_array($pages)) {
            return [];
        }
        $out = [];
        foreach ($pages as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }
        return $out;
    }

    /**
     * Retrieve the array of advertisement definitions for a given
     * issue.  Ads are stored as JSON under the `_roro_mag_ads`
     * post meta key.  Each ad is expected to have at minimum a
     * `lang` code, an optional image URL, title, body copy, URL and
     * an integer `insert_after` specifying after which page (1‑based)
     * the card should be inserted.  Invalid entries are silently
     * ignored.
     *
     * @param int $issue_id
     * @return array<int,array<string,mixed>>
     */
    public function get_issue_ads(int $issue_id): array {
        $raw = (string) get_post_meta($issue_id, '_roro_mag_ads', true);
        $ads = json_decode($raw, true);
        return is_array($ads) ? $ads : [];
    }

    /**
     * Compose the deck of slides for a magazine issue.  A deck is an
     * ordered sequence of associative arrays with `type` and `html`
     * keys.  For each page defined on the issue the deck will
     * contain a page slide followed by any adverts matching the
     * current language that should be inserted after that page.  The
     * resulting array is used directly by the front end JavaScript
     * powering the page turning viewer.  The HTML is passed through
     * `wpautop` so that newlines become paragraphs.
     *
     * @param int    $issue_id
     * @param string $lang
     * @return array<int,array<string,mixed>>
     */
    public function build_issue_deck(int $issue_id, string $lang): array {
        $pages = $this->get_issue_pages($issue_id, $lang);
        $ads   = $this->get_issue_ads($issue_id);
        $deck  = [];
        foreach ($pages as $index => $page) {
            // Normalise page HTML into paragraphs
            $deck[] = [
                'type' => 'page',
                'html' => wpautop($page),
            ];
            $page_number = $index + 1;
            // Insert adverts that target this page number
            foreach ($ads as $ad) {
                if (empty($ad['lang']) || $ad['lang'] !== $lang) {
                    continue;
                }
                if (intval($ad['insert_after'] ?? 0) !== $page_number) {
                    continue;
                }
                $deck[] = [
                    'type' => 'ad',
                    'html' => $this->render_ad_card($ad),
                ];
            }
        }
        return $deck;
    }

    /**
     * Render a single advertisement card into HTML.  Ad cards
     * resemble mini articles: they include a label, optional
     * featured image, headline, body copy and a call to action.  All
     * dynamic values are escaped appropriately.  The label and call
     * to action text are translated via WordPress to allow for
     * multi‑language support.
     *
     * @param array<string,mixed> $ad
     * @return string HTML snippet safe to output directly.
     */
    public function render_ad_card(array $ad): string {
        $img = isset($ad['image']) ? esc_url_raw((string) $ad['image']) : '';
        $title = isset($ad['title']) ? sanitize_text_field((string) $ad['title']) : '';
        $body  = isset($ad['body']) ? wp_kses_post((string) $ad['body']) : '';
        $url   = isset($ad['url']) ? esc_url_raw((string) $ad['url']) : '';
        // Use our message dictionary when available.  Fall back to
        // WordPress translations if the keys are missing.  The ad
        // definition includes a `lang` attribute which we use to
        // load the appropriate language file.  This allows the
        // Sponsored and Learn more labels to be customised via
        // lang/*.php without requiring .mo files.
        $messages   = [];
        if (!empty($ad['lang'])) {
            $messages = $this->load_messages((string) $ad['lang']);
        }
        $label      = isset($messages['sponsored']) ? esc_html($messages['sponsored']) : esc_html__( 'Sponsored', 'roro-magazine' );
        $button_txt = isset($messages['learn_more']) ? esc_html($messages['learn_more']) : esc_html__( 'Learn more', 'roro-magazine' );
        $html = '<div class="roro-mag-adcard">';
        $html .= '<div class="roro-mag-adlabel">' . $label . '</div>';
        if ($img) {
            $html .= '<img src="' . esc_url($img) . '" alt="' . esc_attr($title) . '" style="max-width:100%;height:auto;display:block;margin:0 auto 8px;" />';
        }
        if ($title) {
            $html .= '<h3 style="margin:.2rem 0 .4rem 0;">' . esc_html($title) . '</h3>';
        }
        if ($body) {
            $html .= '<div class="roro-mag-adbody">' . $body . '</div>';
        }
        if ($url) {
            $html .= '<p><a class="button" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $button_txt . '</a></p>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Build a simplified payload representing a magazine issue.  The
     * payload is consumed by front end templates and the REST API.  It
     * contains the ID, title (optionally language specific), summary,
     * issue key (YYYY‑MM), cover image and permalink.
     *
     * @param WP_Post|int $post Issue post object or ID.
     * @param string      $lang Current language code.
     * @return array<string,mixed>
     */
    public function issue_payload($post, string $lang): array {
        $id    = is_object($post) ? $post->ID : intval($post);
        $title = $this->meta($id, '_roro_mag_title_' . $lang, get_the_title($id));
        $summary = $this->meta($id, '_roro_mag_summary_' . $lang, '');
        $issue_key = $this->meta($id, '_roro_mag_issue_key', '');
        $cover = get_the_post_thumbnail_url($id, 'large');
        return [
            'id'        => $id,
            'title'     => $title,
            'summary'   => $summary,
            'issue_key' => $issue_key,
            'cover'     => $cover,
            'permalink' => get_permalink($id),
        ];
    }

    /**
     * Build a simplified payload representing an article.  The
     * payload is used when rendering article lists or single article
     * views.  It includes the ID, title, content, excerpt, issue ID,
     * order index, featured image and permalink.  When language
     * specific meta values are absent the post content and excerpt
     * fields are used as fallbacks.
     *
     * @param WP_Post|int $post Article post object or ID.
     * @param string      $lang Current language code.
     * @return array<string,mixed>
     */
    public function article_payload($post, string $lang): array {
        $id    = is_object($post) ? $post->ID : intval($post);
        $title = $this->meta($id, '_roro_mag_title_' . $lang, get_the_title($id));
        $content = $this->meta($id, '_roro_mag_content_' . $lang, '');
        $excerpt = $this->meta($id, '_roro_mag_excerpt_' . $lang, '');
        $issue_id = intval($this->meta($id, '_roro_mag_issue_id', 0));
        $order = intval($this->meta($id, '_roro_mag_order', 0));
        $image = get_the_post_thumbnail_url($id, 'large');
        // Fall back to default content/excerpt when translated versions missing
        if ($content === '') {
            $content = apply_filters('the_content', get_post_field('post_content', $id));
        }
        if ($excerpt === '') {
            $excerpt = get_the_excerpt($id);
        }
        return [
            'id'       => $id,
            'title'    => $title,
            'content'  => $content,
            'excerpt'  => $excerpt,
            'issue_id' => $issue_id,
            'order'    => $order,
            'image'    => $image,
            'permalink'=> get_permalink($id),
        ];
    }

    /**
     * List issues for archive display.  This helper wraps WP_Query
     * with sensible defaults: published issues ordered by date
     * descending.  A limit and offset can be specified for
     * pagination.  Returns an array of WP_Post objects.
     */
    public function list_issues(int $limit = 6, int $offset = 0): array {
        $query = new WP_Query([
            'post_type'      => 'roro_mag_issue',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        return $query->posts;
    }

    /**
     * List all articles belonging to a particular issue.  Articles
     * are matched via the `_roro_mag_issue_id` meta key.  Results are
     * ordered first by the numeric meta value `_roro_mag_order` and
     * secondarily by title.  Returns an array of WP_Post objects.
     */
    public function list_articles_by_issue(int $issue_id): array {
        $query = new WP_Query([
            'post_type'      => 'roro_mag_article',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_roro_mag_issue_id',
            'meta_value'     => $issue_id,
            'orderby'        => ['meta_value_num' => 'ASC', 'title' => 'ASC'],
            'order'          => 'ASC',
        ]);
        return $query->posts;
    }

    /**
     * Helper to fetch meta values with a fallback default.  An
     * empty string (but not '0') is treated as missing and replaced
     * with the default.  WordPress stores all post meta values as
     * strings.
     */
    private function meta(int $post_id, string $key, $default = '') {
        $value = get_post_meta($post_id, $key, true);
        return $value !== '' ? $value : $default;
    }
}