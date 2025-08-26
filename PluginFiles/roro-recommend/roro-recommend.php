<?php
/*
Plugin Name: RORO Recommend
Description: 提案プラグイン – 各ユーザーに「今日のおすすめ」を提供します。日替わりでおすすめスポットとワンポイントアドバイスを表示します。
Version: 1.0.0
Author: RORO Team
Text Domain: roro-recommend
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // exit if accessed directly
}

/**
 * ロード時にテキストドメインを読み込みます。
 */
function roro_recommend_load_textdomain() {
    load_plugin_textdomain( 'roro-recommend', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'roro_recommend_load_textdomain' );

/**
 * 推薦アイテムのデータを定義します。各アイテムはタイトルと説明を多言語で持ちます。
 */
function roro_recommend_get_data() {
    return [
        'items' => [
            'park_walk' => [
                'category' => 'spot',
                'titles' => [
                    'ja' => '近所の公園散歩',
                    'en' => 'Neighborhood Park Walk',
                    'zh' => '附近公园散步',
                    'ko' => '근처 공원 산책',
                ],
                'descriptions' => [
                    'ja' => '愛犬と緑あふれる公園でリフレッシュしましょう。朝や夕方の涼しい時間がおすすめです。',
                    'en' => 'Refresh with your dog in a green park. Morning and evening are best.',
                    'zh' => '带着爱犬在绿意盎然的公园散步，建议清晨或傍晚时间。',
                    'ko' => '반려견과 함께 공원의 녹음을 즐기며 산책하세요. 아침과 저녁이 가장 좋습니다.',
                ],
            ],
            'dog_cafe' => [
                'category' => 'cafe',
                'titles' => [
                    'ja' => 'ドッグカフェ体験',
                    'en' => 'Dog Café Experience',
                    'zh' => '狗狗咖啡馆体验',
                    'ko' => '도그 카페 체험',
                ],
                'descriptions' => [
                    'ja' => 'ペット同伴可能なカフェでくつろぎながら美味しいドリンクを。新しい友達もできるかも？',
                    'en' => 'Enjoy delicious drinks at a pet-friendly café. You might make new friends!',
                    'zh' => '在允许携带宠物的咖啡馆中享受美味的饮品，还可能结识新朋友。',
                    'ko' => '반려동물 동반이 가능한 카페에서 맛있는 음료를 즐겨보세요. 새로운 친구를 만날 수도 있습니다.',
                ],
            ],
            'training_class' => [
                'category' => 'event',
                'titles' => [
                    'ja' => 'しつけ教室に参加',
                    'en' => 'Join a Training Class',
                    'zh' => '参加训犬课程',
                    'ko' => '훈련 교실 참여',
                ],
                'descriptions' => [
                    'ja' => '専門家によるしつけ教室でスキルアップ。犬とのコミュニケーションがよりスムーズになります。',
                    'en' => 'Improve skills at a training class by experts. Communicate better with your dog.',
                    'zh' => '参加专家主持的训犬课堂，提高技能，与爱犬沟通更顺畅。',
                    'ko' => '전문가의 훈련 클래스에서 실력을 키워보세요. 반려견과 소통이 더욱 원활해집니다.',
                ],
            ],
            'pet_shop' => [
                'category' => 'shop',
                'titles' => [
                    'ja' => 'ペットショップでお買い物',
                    'en' => 'Shopping at a Pet Shop',
                    'zh' => '宠物店购物',
                    'ko' => '애완동물 가게 쇼핑',
                ],
                'descriptions' => [
                    'ja' => '新しいおもちゃやおやつを探しましょう。ペットにぴったりのアイテムが見つかるかも。',
                    'en' => 'Look for new toys and treats. You may find the perfect item for your pet.',
                    'zh' => '为爱宠寻找新玩具或零食，或许会找到完美的商品。',
                    'ko' => '새로운 장난감과 간식을 찾아보세요. 반려동물에게 딱 맞는 아이템을 발견할 수도 있습니다.',
                ],
            ],
        ],
    ];
}

/**
 * 指定されたロケールに基づいてランダムなおすすめアイテムを返します。
 *
 * @param string $locale 現在のロケール（ja、en、zh、koなど）
 * @return array 選ばれたアイテム
 */
function roro_recommend_get_random_item( $locale ) {
    $data = roro_recommend_get_data();
    $items = $data['items'];
    $keys = array_keys( $items );
    $random_key = $keys[ array_rand( $keys ) ];
    $item = $items[ $random_key ];
    $lang = substr( $locale, 0, 2 );
    // 適切な言語がない場合は英語をデフォルト
    $title = $item['titles'][ $lang ] ?? $item['titles']['en'];
    $description = $item['descriptions'][ $lang ] ?? $item['descriptions']['en'];
    return [
        'id' => $random_key,
        'title' => $title,
        'description' => $description,
    ];
}

/**
 * ユーザー毎に今日のおすすめを決定します。1日に1回だけ同じアイテムを提供し、翌日には新しいものに更新します。
 *
 * @param string $locale ロケール
 * @return array おすすめアイテム
 */
function roro_recommend_get_daily_recommendation( $locale ) {
    $user_id = get_current_user_id();
    $today = date_i18n( 'Y-m-d' );
    if ( $user_id ) {
        $last_date = get_user_meta( $user_id, 'roro_recommend_last_date', true );
        $last_item = get_user_meta( $user_id, 'roro_recommend_last_item', true );
        if ( $last_date === $today && $last_item ) {
            // 既に今日のおすすめが記録されている場合、そのまま返す
            $data = roro_recommend_get_data();
            if ( isset( $data['items'][ $last_item ] ) ) {
                $item = $data['items'][ $last_item ];
                $lang = substr( $locale, 0, 2 );
                $title = $item['titles'][ $lang ] ?? $item['titles']['en'];
                $description = $item['descriptions'][ $lang ] ?? $item['descriptions']['en'];
                return [
                    'id' => $last_item,
                    'title' => $title,
                    'description' => $description,
                ];
            }
        }
    }
    // 新しいおすすめを選択し、保存
    $recommendation = roro_recommend_get_random_item( $locale );
    if ( $user_id ) {
        update_user_meta( $user_id, 'roro_recommend_last_date', $today );
        update_user_meta( $user_id, 'roro_recommend_last_item', $recommendation['id'] );
    }
    return $recommendation;
}

/**
 * 任意のカテゴリからランダムなワンポイントアドバイスを選びます。
 * RORO Adviceプラグインが有効な場合はその関数を使用します。なければ簡易なアドバイスを返します。
 *
 * @param string $locale ロケール
 * @return string アドバイスメッセージ
 */
function roro_recommend_get_random_advice( $locale ) {
    // RORO Advice プラグインの関数が存在すればそれを使う
    if ( function_exists( 'roro_advice_get_random_message' ) ) {
        return roro_advice_get_random_message( 'general', $locale );
    }
    // 簡易デフォルトアドバイス
    $advice_data = [
        'ja' => [
            '毎日の散歩は時間帯を変えて楽しみましょう。',
            '水分補給を忘れずに。愛犬の飲み水は常に新鮮に保ちましょう。',
            '安全なフードを選ぶために原材料をチェックしましょう。',
        ],
        'en' => [
            'Vary the time of your daily walks for a fresh experience.',
            'Keep your pet hydrated. Always provide fresh water.',
            'Check ingredients to choose safe food for your pet.',
        ],
        'zh' => [
            '每天散步时可以换个时间，享受新鲜感。',
            '记得给爱犬补充水分，保持饮水新鲜。',
            '选择狗粮时注意查看配料，确保安全。',
        ],
        'ko' => [
            '매일 산책 시간을 바꿔보세요. 새로운 기분을 느낄 수 있습니다.',
            '반려견에게 신선한 물을 제공해 충분히 수분을 섭취하게 하세요.',
            '사료를 선택할 때는 원재료를 확인하여 안전한 제품을 고르세요.',
        ],
    ];
    $lang = substr( $locale, 0, 2 );
    $list = $advice_data[ $lang ] ?? $advice_data['en'];
    return $list[ array_rand( $list ) ];
}

/**
 * ショートコード [roro_recommend] を実装します。ユーザーごとに今日のおすすめスポットとアドバイスを表示します。
 * オプションで show_advice="0" を指定するとアドバイスを非表示にできます。
 *
 * @param array $atts ショートコード属性
 * @return string HTML 出力
 */
function roro_recommend_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'show_advice' => '1' ], $atts, 'roro_recommend' );
    $locale = get_locale();
    $recommendation = roro_recommend_get_daily_recommendation( $locale );
    $title = esc_html( $recommendation['title'] );
    $description = esc_html( $recommendation['description'] );
    $output  = '<div class="roro-recommend">';
    $output .= '<h3>' . esc_html__( '今日のおすすめ', 'roro-recommend' ) . '</h3>';
    $output .= '<p class="roro-recommend-title"><strong>' . $title . '</strong></p>';
    $output .= '<p class="roro-recommend-desc">' . $description . '</p>';
    if ( $atts['show_advice'] === '1' ) {
        $advice = esc_html( roro_recommend_get_random_advice( $locale ) );
        $output .= '<p class="roro-recommend-advice">' . esc_html__( 'アドバイス: ', 'roro-recommend' ) . $advice . '</p>';
    }
    $output .= '</div>';
    return $output;
}
add_shortcode( 'roro_recommend', 'roro_recommend_shortcode' );