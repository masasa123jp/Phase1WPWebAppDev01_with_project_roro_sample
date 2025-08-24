<?php
/**
 * RORO Magazine: CPT + Taxonomy + Meta + REST
 *
 * @package roro-core-wp
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('RORO_Magazine', false)):

final class RORO_Magazine {

    private static ?self $instance = null;
    public const CPT = 'roro_magazine';
    public const TAX_LANG = 'roro_lang';
    public const META_ISSUE = 'roro_issue'; // 例: 2025-06
    public const META_PAGES = 'roro_pages'; // array<object> [{title, content, image}]
    public const META_COVER = 'roro_cover_image_url';

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate(): void {
        self::instance()->register_all();
        flush_rewrite_rules(false);
        // 既定の言語タームを用意
        $langs = ['ja','en','zh','ko'];
        foreach ($langs as $code) {
            if (!term_exists($code, self::TAX_LANG)) {
                wp_insert_term($code, self::TAX_LANG, ['slug' => $code, 'description' => strtoupper($code)]);
            }
        }
    }

    public static function deactivate(): void {
        flush_rewrite_rules(false);
    }

    public function init(): void {
        add_action('init', [$this, 'register_all']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_meta'], 10, 2);

        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /** CPT + Taxonomy + Meta */
    public function register_all(): void {
        $this->register_tax_lang();
        $this->register_cpt_magazine();
        $this->register_meta_fields();
    }

    private function register_tax_lang(): void {
        register_taxonomy(
            self::TAX_LANG,
            [self::CPT],
            [
                'labels' => [
                    'name'          => __('Languages', 'roro-core-wp'),
                    'singular_name' => __('Language', 'roro-core-wp'),
                ],
                'public'       => true,
                'hierarchical' => false,
                'show_ui'      => true,
                'show_in_rest' => true,
                'rewrite'      => ['slug' => 'mag-lang'],
            ]
        );
    }

    private function register_cpt_magazine(): void {
        $labels = [
            'name'               => __('Magazines', 'roro-core-wp'),
            'singular_name'      => __('Magazine', 'roro-core-wp'),
            'add_new'            => __('Add New', 'roro-core-wp'),
            'add_new_item'       => __('Add New Magazine', 'roro-core-wp'),
            'edit_item'          => __('Edit Magazine', 'roro-core-wp'),
            'new_item'           => __('New Magazine', 'roro-core-wp'),
            'view_item'          => __('View Magazine', 'roro-core-wp'),
            'search_items'       => __('Search Magazines', 'roro-core-wp'),
            'not_found'          => __('No magazines found', 'roro-core-wp'),
            'not_found_in_trash' => __('No magazines found in Trash', 'roro-core-wp'),
            'all_items'          => __('All Magazines', 'roro-core-wp'),
        ];

        register_post_type(
            self::CPT,
            [
                'labels' => $labels,
                'public' => true,
                'has_archive' => true,
                'menu_icon'   => 'dashicons-media-document',
                'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
                'show_in_rest'=> true,
                'rewrite'     => ['slug' => 'magazine', 'with_front' => true],
            ]
        );
    }

    private function register_meta_fields(): void {
        register_post_meta(self::CPT, self::META_ISSUE, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => [$this, 'can_edit_meta'],
            'sanitize_callback' => function ($val) {
                $val = is_string($val) ? trim($val) : '';
                // YYYY-MM 形式に限定（簡易チェック）
                return (preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $val) === 1) ? $val : '';
            },
        ]);

        register_post_meta(self::CPT, self::META_COVER, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => [$this, 'can_edit_meta'],
            'sanitize_callback' => 'esc_url_raw',
        ]);

        register_post_meta(self::CPT, self::META_PAGES, [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title'   => ['type' => 'string'],
                            'content' => ['type' => 'string'],
                            'image'   => ['type' => 'string', 'format' => 'uri'],
                        ],
                    ],
                ],
            ],
            'auth_callback'     => [$this, 'can_edit_meta'],
            'sanitize_callback' => function ($val) {
                if (!is_array($val)) return [];
                $out = [];
                foreach ($val as $p) {
                    if (!is_array($p)) continue;
                    $out[] = [
                        'title'   => sanitize_text_field((string)($p['title'] ?? '')),
                        'content' => wp_kses_post((string)($p['content'] ?? '')),
                        'image'   => esc_url_raw((string)($p['image'] ?? '')),
                    ];
                }
                return $out;
            },
        ]);
    }

    public function can_edit_meta(): bool {
        return current_user_can('edit_posts');
    }

    /** メタボックス */
    public function add_meta_boxes(): void {
        add_meta_box(
            'roro_magazine_details',
            __('Magazine Details', 'roro-core-wp'),
            [$this, 'render_metabox'],
            self::CPT,
            'side',
            'default'
        );
    }

    public function render_metabox(\WP_Post $post): void {
        wp_nonce_field('roro_magazine_meta', 'roro_magazine_meta_nonce');

        $issue = (string)get_post_meta($post->ID, self::META_ISSUE, true);
        $cover = (string)get_post_meta($post->ID, self::META_COVER, true);

        ?>
        <p>
            <label for="roro_issue"><strong><?php echo esc_html__('Issue (YYYY-MM)', 'roro-core-wp'); ?></strong></label>
            <input type="text" id="roro_issue" name="roro_issue" value="<?php echo esc_attr($issue); ?>" placeholder="2025-06" style="width:100%;">
        </p>
        <p>
            <label for="roro_cover_image_url"><strong><?php echo esc_html__('Cover Image URL', 'roro-core-wp'); ?></strong></label>
            <input type="url" id="roro_cover_image_url" name="roro_cover_image_url" value="<?php echo esc_attr($cover); ?>" placeholder="https://example.com/cover.jpg" style="width:100%;">
        </p>
        <p class="description"><?php echo esc_html__('Use Featured Image as primary visual if set. This URL is optional.', 'roro-core-wp'); ?></p>
        <?php
    }

    public function save_meta(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['roro_magazine_meta_nonce']) || !wp_verify_nonce((string)$_POST['roro_magazine_meta_nonce'], 'roro_magazine_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Issue
        if (isset($_POST['roro_issue'])) {
            $issue = sanitize_text_field((string)$_POST['roro_issue']);
            if (preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $issue) === 1) {
                update_post_meta($post_id, self::META_ISSUE, $issue);
            } else {
                delete_post_meta($post_id, self::META_ISSUE);
            }
        }
        // Cover URL
        if (isset($_POST['roro_cover_image_url'])) {
            $cover = esc_url_raw((string)$_POST['roro_cover_image_url']);
            if ($cover !== '') {
                update_post_meta($post_id, self::META_COVER, $cover);
            } else {
                delete_post_meta($post_id, self::META_COVER);
            }
        }
    }

    /** REST ルート登録 */
    public function register_rest_routes(): void {
        $ns = 'roro/v1';

        // GET /magazines?lang=ja&issue=2025-06&search=summer
        register_rest_route($ns, '/magazines', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_list_magazines'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'lang'  => ['type' => 'string', 'required' => false],
                    'issue' => ['type' => 'string', 'required' => false],
                    'search'=> ['type' => 'string', 'required' => false],
                    'page'  => ['type' => 'integer', 'required' => false, 'default' => 1],
                    'per_page' => ['type' => 'integer', 'required' => false, 'default' => 12],
                ],
            ],
        ]);

        // GET /magazines/{id}
        register_rest_route($ns, '/magazines/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_get_magazine'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => ['type' => 'integer', 'required' => true],
                ],
            ],
        ]);

        // POST /magazines/{id}/favorite
        register_rest_route($ns, '/magazines/(?P<id>\d+)/favorite', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_favorite'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    'id' => ['type' => 'integer', 'required' => true],
                    'action' => ['type' => 'string', 'required' => false, 'default' => 'toggle'], // add|remove|toggle
                ],
            ],
        ]);
    }

    public function rest_list_magazines(\WP_REST_Request $req): \WP_REST_Response {
        $lang     = (string)$req->get_param('lang');
        $issue    = (string)$req->get_param('issue');
        $search   = (string)$req->get_param('search');
        $paged    = max(1, (int)$req->get_param('page'));
        $per_page = min(50, max(1, (int)$req->get_param('per_page')));

        $tax_query = [];
        if ($lang !== '') {
            $tax_query[] = [
                'taxonomy' => self::TAX_LANG,
                'field'    => 'slug',
                'terms'    => [$lang],
            ];
        }

        $meta_query = [];
        if ($issue !== '') {
            $meta_query[] = [
                'key'     => self::META_ISSUE,
                'value'   => $issue,
                'compare' => '=',
            ];
        }

        $q = new \WP_Query([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            's'              => $search,
            'paged'          => $paged,
            'posts_per_page' => $per_page,
            'tax_query'      => $tax_query ?: null,
            'meta_query'     => $meta_query ?: null,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $items = [];
        foreach ($q->posts as $p) {
            $cover = get_post_meta($p->ID, self::META_COVER, true);
            if (!$cover) {
                $thumb = get_the_post_thumbnail_url($p->ID, 'large');
                $cover = $thumb ?: '';
            }
            $terms = wp_get_post_terms($p->ID, self::TAX_LANG, ['fields' => 'slugs']);
            $items[] = [
                'id'      => (int)$p->ID,
                'title'   => html_entity_decode(get_the_title($p), ENT_QUOTES | ENT_HTML5),
                'excerpt' => wp_strip_all_tags(get_the_excerpt($p), true),
                'issue'   => (string)get_post_meta($p->ID, self::META_ISSUE, true),
                'lang'    => $terms ? $terms[0] : '',
                'cover'   => (string)$cover,
                'date'    => get_the_date(DATE_ATOM, $p),
                'link'    => get_permalink($p),
            ];
        }

        return new \WP_REST_Response([
            'items'      => $items,
            'total'      => (int)$q->found_posts,
            'totalPages' => (int)$q->max_num_pages,
            'page'       => $paged,
        ], 200);
    }

    public function rest_get_magazine(\WP_REST_Request $req): \WP_REST_Response {
        $id = (int)$req['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== self::CPT || $post->post_status !== 'publish') {
            return new \WP_REST_Response(['message' => __('Not found', 'roro-core-wp')], 404);
        }

        $cover = get_post_meta($post->ID, self::META_COVER, true);
        if (!$cover) {
            $thumb = get_the_post_thumbnail_url($post->ID, 'full');
            $cover = $thumb ?: '';
        }
        $terms = wp_get_post_terms($post->ID, self::TAX_LANG, ['fields' => 'slugs']);
        $pages = get_post_meta($post->ID, self::META_PAGES, true);
        if (!is_array($pages)) $pages = [];

        return new \WP_REST_Response([
            'id'      => (int)$post->ID,
            'title'   => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5),
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post), true),
            'issue'   => (string)get_post_meta($post->ID, self::META_ISSUE, true),
            'lang'    => $terms ? $terms[0] : '',
            'cover'   => (string)$cover,
            'pages'   => $pages,
            'date'    => get_the_date(DATE_ATOM, $post),
            'link'    => get_permalink($post),
        ], 200);
    }

    public function rest_favorite(\WP_REST_Request $req): \WP_REST_Response {
        $id = (int)$req['id'];
        $action = (string)$req->get_param('action');
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new \WP_REST_Response(['message' => __('Unauthorized', 'roro-core-wp')], 401);
        }
        $key = 'roro_fav_mag';
        $list = get_user_meta($user_id, $key, true);
        if (!is_array($list)) $list = [];

        $exists = in_array($id, $list, true);
        if ($action === 'add' || ($action === 'toggle' && !$exists)) {
            $list[] = $id;
            $list = array_values(array_unique(array_map('intval', $list)));
            update_user_meta($user_id, $key, $list);
        } elseif ($action === 'remove' || ($action === 'toggle' && $exists)) {
            $list = array_values(array_diff($list, [$id]));
            update_user_meta($user_id, $key, $list);
        }

        return new \WP_REST_Response([
            'ok'      => true,
            'favors'  => $list,
            'message' => __('Updated', 'roro-core-wp'),
        ], 200);
    }
}

endif;
