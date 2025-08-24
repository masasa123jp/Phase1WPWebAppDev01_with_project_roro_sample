<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Chat_Service {

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
        $file = RORO_CHAT_PATH . "lang/{$lang}.php";
        if (file_exists($file)) { require $file; if(isset($roro_chat_messages)) return $roro_chat_messages; }
        require RORO_CHAT_PATH . "lang/en.php";
        return $roro_chat_messages;
    }

    public function new_conversation($user_id){
        global $wpdb;
        $tbl = $wpdb->prefix . 'RORO_AI_CONVERSATION';
        try{
            $wpdb->insert($tbl, [
                'user_id'    => intval($user_id),
                'created_at' => current_time('mysql'),
            ]);
            return intval($wpdb->insert_id);
        }catch(Exception $e){
            return 0;
        }
    }

    public function save_message($conversation_id, $role, $content){
        global $wpdb;
        $tbl = $wpdb->prefix . 'RORO_AI_MESSAGE';
        try{
            $wpdb->insert($tbl, [
                'conversation_id' => intval($conversation_id),
                'role'            => sanitize_text_field($role),
                'content'         => $content,
                'created_at'      => current_time('mysql'),
            ]);
        }catch(Exception $e){ /* テーブルが無い等は無視 */ }
    }

    public function handle_user_message($message, $conversation_id=0, $user_id=0){
        $provider = get_option('roro_chat_provider', 'echo');
        $message = trim(wp_strip_all_tags($message));
        if ($message === '') return ['reply'=>'', 'conversation_id'=>$conversation_id];

        // 会話ID管理（なければ新規）
        if (!$conversation_id) {
            $conversation_id = $this->new_conversation($user_id);
        }
        if ($conversation_id) $this->save_message($conversation_id, 'user', $message);

        // プロバイダ判定
        if ($provider === 'openai' && ($key = get_option('roro_chat_openai_api_key'))) {
            $reply = $this->call_openai($message, $key, get_option('roro_chat_openai_model', 'gpt-4o-mini'));
        } elseif ($provider === 'dify' && ($key = get_option('roro_chat_dify_api_key')) && ($base = get_option('roro_chat_dify_base'))) {
            $reply = $this->call_dify($message, $key, $base);
        } else {
            $reply = $this->fallback_rule_based($message);
        }

        if ($conversation_id && $reply) $this->save_message($conversation_id, 'assistant', $reply);
        return ['reply'=>$reply, 'conversation_id'=>$conversation_id];
    }

    private function call_openai($message, $api_key, $model='gpt-4o-mini'){
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $body = [
            'model' => $model,
            'messages' => [
                ['role'=>'system','content'=>'You are a helpful assistant for a pet/outdoor event discovery app called RORO. Answer concisely in the user\'s language.'],
                ['role'=>'user','content'=>$message]
            ],
            'temperature' => 0.6,
            'max_tokens' => 400
        ];
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 25,
            'blocking'=> true,
            'body'    => wp_json_encode($body)
        ];
        $res = wp_remote_post($endpoint, $args);
        if (is_wp_error($res)) { return $this->fallback_rule_based($message); }
        $code = wp_remote_retrieve_response_code($res);
        $j = json_decode(wp_remote_retrieve_body($res), true);
        if ($code>=200 && $code<300 && isset($j['choices'][0]['message']['content'])) {
            return trim($j['choices'][0]['message']['content']);
        }
        return $this->fallback_rule_based($message);
    }

    private function call_dify($message, $api_key, $base){
        // Dify 推奨の /v1/chat-messages 互換（アプリ毎に異なる場合あり）
        $endpoint = rtrim($base, '/') . '/v1/chat-messages';
        $body = ['inputs'=>[], 'response_mode'=>'blocking', 'query'=>$message];
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 25,
            'blocking'=> true,
            'body'    => wp_json_encode($body)
        ];
        $res = wp_remote_post($endpoint, $args);
        if (is_wp_error($res)) { return $this->fallback_rule_based($message); }
        $code = wp_remote_retrieve_response_code($res);
        $j = json_decode(wp_remote_retrieve_body($res), true);
        if ($code>=200 && $code<300 && isset($j['answer'])) {
            return trim($j['answer']);
        }
        return $this->fallback_rule_based($message);
    }

    private function fallback_rule_based($message){
        global $wpdb;
        $m = mb_strtolower($message);
        $ans = [];

        // イベント検索（タイトル/説明/住所）
        $ev_tbl = $wpdb->prefix . 'RORO_EVENTS_MASTER';
        $like = '%' . $wpdb->esc_like($message) . '%';
        $evs = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, start_time, address FROM {$ev_tbl}
            WHERE title LIKE %s OR description LIKE %s OR address LIKE %s
            ORDER BY start_time ASC LIMIT 5
        ", $like, $like, $like), ARRAY_A);

        if ($evs){
            $ans[] = "見つかったイベント:";
            foreach($evs as $r){
                $line = sprintf("- %s (%s) @ %s", $r['title'], $r['start_time'], $r['address']);
                $ans[] = $line;
            }
        }

        // アドバイス（ランダム）
        $ad_tbl = $wpdb->prefix . 'RORO_ONE_POINT_ADVICE_MASTER';
        $ad = $wpdb->get_var("SELECT advice_text FROM {$ad_tbl} ORDER BY RAND() LIMIT 1");
        if ($ad){
            $ans[] = "ワンポイントアドバイス: " . $ad;
        }

        if (!$ans){
            return "ご質問ありがとうございます。現在の設定では外部AIが無効のため、簡易応答で対応しています。具体的なキーワード（例：『ドッグラン』『八王子』）でお試しください。";
        }
        return implode("\n", $ans);
    }
}
