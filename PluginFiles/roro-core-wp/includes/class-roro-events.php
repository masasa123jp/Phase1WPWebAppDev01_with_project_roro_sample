<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Roro_Core_Events {

    public const CPT = 'roro_event';

    // メタキー
    public const META_LAT     = 'roro_lat';
    public const META_LNG     = 'roro_lng';
    public const META_START   = 'roro_start_at';
    public const META_END     = 'roro_end_at';
    public const META_ADDRESS = 'roro_address';
    public const META_URL     = 'roro_url';

    /** CPT 登録（init） */
    public static function register_cpt(): void {
        $labels = [
            'name'          => 'RORO Events',
            'singular_name' => 'RORO Event',
        ];
        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'has_archive'        => true,
            'rewrite'            => ['slug' => 'roro-event'],
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-location',
            'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields'],
        ];
        register_post_type(self::CPT, $args);
    }

    /** サンプルデータを必要に応じて投入（アクティベーション時） */
    public static function maybe_seed_samples(): void {
        $q = new WP_Query([
            'post_type'      => self::CPT,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        if (!empty($q->posts)) {
            return; // 既にデータがある
        }

        $samples = [
            [
                'post_title'   => 'わんわんマルシェ@代々木公園',
                'post_content' => '犬用品の屋台やしつけ相談など。入場無料。',
                self::META_LAT => '35.671669',
                self::META_LNG => '139.694901',
                self::META_START => gmdate('Y-m-d H:i:s', strtotime('+7 days 10:00')),
                self::META_END   => gmdate('Y-m-d H:i:s', strtotime('+7 days 16:00')),
                self::META_ADDRESS => '東京都渋谷区 代々木公園',
                self::META_URL     => 'https://example.com/event/1',
            ],
            [
                'post_title'   => 'ドッグラン交流会@駒沢',
                'post_content' => '小型犬向けの交流イベント。事前予約制。',
                self::META_LAT => '35.628019',
                self::META_LNG => '139.661944',
                self::META_START => gmdate('Y-m-d H:i:s', strtotime('+14 days 09:00')),
                self::META_END   => gmdate('Y-m-d H:i:s', strtotime('+14 days 12:00')),
                self::META_ADDRESS => '東京都世田谷区 駒沢公園',
                self::META_URL     => 'https://example.com/event/2',
            ],
        ];

        foreach ($samples as $ev) {
            $post_id = wp_insert_post([
                'post_type'   => self::CPT,
                'post_status' => 'publish',
                'post_title'  => $ev['post_title'],
                'post_content'=> $ev['post_content'],
            ]);
            if ($post_id && !is_wp_error($post_id)) {
                foreach ([self::META_LAT, self::META_LNG, self::META_START, self::META_END, self::META_ADDRESS, self::META_URL] as $key) {
                    if (isset($ev[$key])) {
                        update_post_meta($post_id, $key, $ev[$key]);
                    }
                }
            }
        }
    }

    /**
     * イベント一覧を配列で返す（REST 用）
     * - 期間やキーワードでの簡易フィルタを提供
     */
    public static function list_events(array $args = []): array {
        $meta_query = [];
        $tax_query  = [];
        $q = sanitize_text_field($args['q'] ?? '');
        $from = sanitize_text_field($args['from'] ?? '');
        $to   = sanitize_text_field($args['to'] ?? '');

        if ($from) {
            $meta_query[] = [
                'key'     => self::META_START,
                'value'   => $from,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ];
        }
        if ($to) {
            $meta_query[] = [
                'key'     => self::META_END,
                'value'   => $to,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ];
        }

        $query = new WP_Query([
            'post_type'      => self::CPT,
            's'              => $q,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta_query,
            'fields'         => 'ids',
        ]);

        $items = [];
        foreach ($query->posts as $id) {
            $items[] = [
                'id'        => (int)$id,
                'title'     => get_the_title($id),
                'excerpt'   => wp_strip_all_tags(get_the_excerpt($id)),
                'content'   => wp_strip_all_tags(get_post_field('post_content', $id)),
                'lat'       => get_post_meta($id, self::META_LAT, true),
                'lng'       => get_post_meta($id, self::META_LNG, true),
                'start_at'  => get_post_meta($id, self::META_START, true),
                'end_at'    => get_post_meta($id, self::META_END, true),
                'address'   => get_post_meta($id, self::META_ADDRESS, true),
                'url'       => get_post_meta($id, self::META_URL, true),
                'permalink' => get_permalink($id),
            ];
        }
        return $items;
    }
}
