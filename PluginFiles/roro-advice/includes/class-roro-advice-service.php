<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Advice_Service {
    /**
     * Default one-point advice messages.
     *
     * These messages provide a fallback when the database does not
     * contain any advice. The array is keyed by a logical category
     * (general, health, training, diet) and language code.  Each entry
     * is an array of possible messages for that category and language.
     *
     * @var array
     */
    protected $default_advices = [
        'general' => [
            'ja' => [
                '毎日少しだけでも愛犬と遊ぶ時間を取ることで、信頼関係が深まります。',
                '散歩中は周囲の環境に気を配りながら、安全に楽しみましょう。',
                '犬に十分な休息を与え、落ち着ける場所を用意してあげましょう。',
            ],
            'en' => [
                'Spending even a little playtime with your dog each day will strengthen your bond.',
                'During walks, pay attention to the surroundings to ensure safety and fun.',
                'Provide your dog with plenty of rest and a calm, comfortable space.',
            ],
            'zh' => [
                '每天花一点时间陪狗狗玩耍，可以增进彼此的信任。',
                '散步时要注意周围环境，确保安全并享受乐趣。',
                '给狗狗充足的休息时间，并提供一个安静舒适的空间。',
            ],
            'ko' => [
                '매일 조금씩이라도 반려견과 놀이 시간을 갖는 것은 신뢰를 쌓는 데 도움이 됩니다.',
                '산책 중에는 주변 환경을 잘 살펴 안전하게 즐기세요.',
                '반려견이 편안하게 쉴 수 있는 공간과 충분한 휴식을 제공하세요.',
            ],
        ],
        'health' => [
            'ja' => [
                '健康維持のために定期的な運動と適切な体重管理を心がけましょう。',
                'フィラリア予防やワクチン接種など定期的な健康チェックを忘れずに行いましょう。',
            ],
            'en' => [
                'Regular exercise and proper weight management are essential for your dog’s health.',
                'Don’t forget regular health checks such as heartworm prevention and vaccinations.',
            ],
            'zh' => [
                '定期运动和保持适当体重对狗狗的健康非常重要。',
                '不要忘记定期进行心丝虫预防和疫苗接种等健康检查。',
            ],
            'ko' => [
                '정기적인 운동과 적절한 체중 관리는 반려견 건강에 필수적입니다.',
                '심장사상충 예방과 백신 접종 등 정기적인 건강 검진을 잊지 마세요.',
            ],
        ],
        'training' => [
            'ja' => [
                'しつけは短い時間を繰り返すことで効果が上がります。楽しく行いましょう。',
                '褒めるタイミングを大切にし、成功体験を積み重ねさせてあげましょう。',
            ],
            'en' => [
                'Short, frequent training sessions are more effective. Keep it fun and rewarding.',
                'Praise your dog at the right moment to build positive experiences.',
            ],
            'zh' => [
                '短而多次的训练效果更好，要保持有趣的氛围。',
                '在正确的时机表扬狗狗，帮助它累积成功的经验。',
            ],
            'ko' => [
                '짧고 자주 하는 훈련이 더 효과적입니다. 재미있고 보람 있게 진행하세요.',
                '적절한 시기에 칭찬하여 긍정적인 경험을 쌓게 해주세요.',
            ],
        ],
        'diet' => [
            'ja' => [
                'バランスの取れた食事は健康の基本です。質の良いフードを選びましょう。',
                '急激な食事の変更は胃腸に負担をかけるので、少しずつ切り替えましょう。',
            ],
            'en' => [
                'A balanced diet is the foundation of good health. Choose high‑quality food.',
                'Introduce new foods gradually to avoid stomach upset.',
            ],
            'zh' => [
                '均衡的饮食是健康的基础，选择高品质的食物。',
                '不要突然更换饮食，应逐渐过渡以免肠胃不适。',
            ],
            'ko' => [
                '균형 잡힌 식단은 건강의 기본입니다. 좋은 품질의 사료를 선택하세요.',
                '갑작스러운 식단 변경은 위장에 부담이 될 수 있으니 천천히 전환하세요.',
            ],
        ],
    ];

    public function detect_lang(){
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

    public function load_lang($lang){
        $file = RORO_ADV_PATH . "lang/{$lang}.php";
        if (file_exists($file)) { require $file; if(isset($roro_adv_messages)) return $roro_adv_messages; }
        require RORO_ADV_PATH . "lang/en.php";
        return $roro_adv_messages;
    }

    /**
     * カテゴリ別 or 全体からランダム1件
     *
     * Attempt to fetch a random advice row from the database. If the table
     * does not exist or no advice is found for the specified category, the
     * built‑in messages defined in $default_advices will be used as a
     * fallback. The category parameter can either be a database category
     * code or a logical category key (general, health, training, diet).
     *
     * @param string $category Category code for DB lookup or logical key for fallback.
     * @param string|null $locale Two‑letter language code (ja|en|zh|ko). Defaults to detected language.
     * @return string|null A single advice string, or null if none found.
     */
    public function get_random_advice( $category = '', $locale = null ) {
        global $wpdb;
        // Determine language if not provided
        if ( $locale === null ) {
            $locale = $this->detect_lang();
        }
        // Try to read from the custom table if it exists
        $text = null;
        $table_name = $wpdb->prefix . 'RORO_ONE_POINT_ADVICE_MASTER';
        // Check whether the advice table exists in the database schema
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
            $table_name
        ) );
        if ( $table_exists ) {
            if ( $category ) {
                $sql = $wpdb->prepare(
                    "SELECT advice_text FROM {$table_name} WHERE category_code = %s ORDER BY RAND() LIMIT 1",
                    sanitize_text_field( $category )
                );
            } else {
                $sql = "SELECT advice_text FROM {$table_name} ORDER BY RAND() LIMIT 1";
            }
            $text = $wpdb->get_var( $sql );
        }
        // If a row was found in the database, return it
        if ( $text ) {
            return $text;
        }
        // Fallback to built‑in advice arrays
        $logical = strtolower( (string) $category );
        if ( ! isset( $this->default_advices[ $logical ] ) ) {
            $logical = 'general';
        }
        $lang = substr( $locale, 0, 2 );
        if ( ! isset( $this->default_advices[ $logical ][ $lang ] ) ) {
            $lang = 'ja';
        }
        $messages = $this->default_advices[ $logical ][ $lang ];
        return $messages[ array_rand( $messages ) ];
    }

    /**
     * Retrieve a random advice message from the built‑in arrays.
     *
     * This helper can be used by other components (such as REST) to
     * explicitly obtain a fallback advice without querying the database.
     *
     * @param string $category Logical category key (general, health, training, diet).
     * @param string $locale   Two‑letter language code.
     * @return string          Advice message.
     */
    public function fallback_random_advice( $category = 'general', $locale = 'ja' ) {
        $logical = strtolower( (string) $category );
        if ( ! isset( $this->default_advices[ $logical ] ) ) {
            $logical = 'general';
        }
        $lang = substr( $locale, 0, 2 );
        if ( ! isset( $this->default_advices[ $logical ][ $lang ] ) ) {
            $lang = 'ja';
        }
        $messages = $this->default_advices[ $logical ][ $lang ];
        return $messages[ array_rand( $messages ) ];
    }
}
