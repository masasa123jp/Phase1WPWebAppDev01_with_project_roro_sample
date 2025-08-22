<?php
/**
 * AI Advice Endpoint (REST API compliant)
 *
 * Converted from a former top-level script into a proper REST endpoint
 * that only runs under /wp-json/ via register_rest_route. This prevents
 * hijacking of /wp-admin/ or frontend requests.
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Ai_Advice_Endpoint extends Abstract_Endpoint {
    public const ROUTE = '/ai/advice';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    /**
     * Register REST route: POST /wp-json/roro/v1/ai/advice
     */
    public static function register(): void {
        register_rest_route( 'roro/v1', self::ROUTE, [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [ self::class, 'handle' ],
            'permission_callback' => [ self::class, 'permission_callback' ],
            'args' => [
                'mode' => [ 'type' => 'string', 'required' => false ],
                'modelProvider' => [ 'type' => 'string', 'required' => false ],
                'additionalParams' => [ 'type' => 'object', 'required' => false ],
                'customerDataFile' => [ 'type' => 'string', 'required' => false ],
                'touristDataFile'  => [ 'type' => 'string', 'required' => false ],
                'planText' => [ 'type' => 'string', 'required' => false ],
                'question' => [ 'type' => 'string', 'required' => false ],
                'preferences' => [ 'type' => 'array', 'required' => false ],
                'history' => [ 'type' => 'array', 'required' => false ],
                'message' => [ 'type' => 'string', 'required' => false ],
            ],
        ] );
    }

    /** Delegate to base class for auth/nonce checks. */
    public static function permission_callback( \WP_REST_Request $request ) {
        return parent::permission_callback( $request );
    }

    /** Handle POST request. */
    public static function handle( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new WP_Error( 'invalid_json_body', __( 'Invalid JSON body.', 'roro-core' ), [ 'status' => 400 ] );
        }
        $mode            = isset( $params['mode'] ) ? strtolower( (string) $params['mode'] ) : 'analysis';
        $modelProvider   = isset( $params['modelProvider'] ) ? strtolower( (string) $params['modelProvider'] ) : 'none';
        $additionalParams= ( isset( $params['additionalParams'] ) && is_array( $params['additionalParams'] ) ) ? $params['additionalParams'] : [];
        $customerFilePath= isset( $params['customerDataFile'] ) ? (string) $params['customerDataFile'] : null;
        $touristFilePath = isset( $params['touristDataFile'] )  ? (string) $params['touristDataFile']  : null;

        $customerData = [];
        $touristData  = [];
        if ( ! empty( $customerFilePath ) ) {
            $customerData = loadCsvData( $customerFilePath );
        }
        if ( ! empty( $touristFilePath ) ) {
            $touristData = loadCsvData( $touristFilePath );
        }

        switch ( $mode ) {
            case 'analysis':
                $planText = isset( $params['planText'] ) && is_string( $params['planText'] ) ? (string) $params['planText'] : '';
                if ( $planText === '' ) {
                    return new WP_Error( 'missing_plantext', __( 'Missing required parameter: planText', 'roro-core' ), [ 'status' => 400 ] );
                }
                $heuristicSuggestions = analyzePlanText( $planText );
                $dataInsights = [];
                if ( ! empty( $customerData ) || ! empty( $touristData ) ) {
                    $dataInsights = analyzeCustomerAndTouristData( $customerData, $touristData );
                }
                $prompt     = buildPrompt( $planText, $heuristicSuggestions, $dataInsights );
                $aiResponse = dispatchToProvider( $prompt, $heuristicSuggestions, $dataInsights, $modelProvider, $additionalParams );
                return new WP_REST_Response( [
                    'mode'      => 'analysis',
                    'prompt'    => $prompt,
                    'result'    => $aiResponse,
                    'timestamp' => date( 'c' ),
                ] );

            case 'recommendation':
                $question    = isset( $params['question'] ) && is_string( $params['question'] ) ? trim( (string) $params['question'] ) : '';
                $preferences = isset( $params['preferences'] ) && is_array( $params['preferences'] ) ? $params['preferences'] : [];
                if ( $question === '' && empty( $preferences ) ) {
                    return new WP_Error( 'missing_params', __( 'Missing required parameter: question or preferences for recommendation mode', 'roro-core' ), [ 'status' => 400 ] );
                }
                $recommendations = generateUserRecommendations( $question, $preferences, $customerData, $touristData );
                $promptRec  = buildRecommendationPrompt( $question, $preferences, $recommendations );
                $aiResponse = dispatchToProvider( $promptRec, $recommendations, [], $modelProvider, $additionalParams );
                return new WP_REST_Response( [
                    'mode'      => 'recommendation',
                    'prompt'    => $promptRec,
                    'result'    => $aiResponse,
                    'timestamp' => date( 'c' ),
                ] );

            case 'chat':
                $history = isset( $params['history'] ) && is_array( $params['history'] ) ? $params['history'] : [];
                $message = isset( $params['message'] ) && is_string( $params['message'] ) ? trim( (string) $params['message'] ) : '';
                if ( $message === '' ) {
                    return new WP_Error( 'missing_message', __( 'Missing required parameter: message for chat mode', 'roro-core' ), [ 'status' => 400 ] );
                }
                $chatHeuristic = generateChatResponse( $history, $message, $customerData, $touristData );
                $promptChat = buildChatPrompt( $history, $message );
                $aiChat = dispatchToProvider( $promptChat, [ $chatHeuristic ], [], $modelProvider, $additionalParams );
                return new WP_REST_Response( [
                    'mode'      => 'chat',
                    'prompt'    => $promptChat,
                    'result'    => $aiChat,
                    'timestamp' => date( 'c' ),
                ] );

            default:
                return new WP_Error( 'unsupported_mode', __( 'Unsupported mode. Use analysis, recommendation, or chat.', 'roro-core' ), [ 'status' => 400 ] );
        }
    }
}

// Below: helper functions (namespaced) used by the handler.
function analyzePlanText(string $text): array
{
    // 全角文字を含む日本語のため、mb_ 系関数を使用
    $normalized = mb_convert_kana($text, 'as'); // 半角全角の正規化
    $suggestions = [];

    // 必須項目とそのチェック用キーワード
    $sections = [
        '企業概要'    => ['企業概要', '会社概要', '企業の概要'],
        '事業概要'    => ['事業概要', '事業の概要'],
        'コンセプト'  => ['コンセプト', '理念', 'ビジョン'],
        '市場環境'    => ['市場', '市場環境', '市場規模', '市場動向'],
        '強みと弱み'  => ['強み', '弱み', 'SWOT', '競争優位'],
        'サービス概要'=> ['サービス', '商品', 'サービス概要', '商品説明'],
        '販売戦略'    => ['販売戦略', 'マーケティング', '営業戦略'],
        '体制・人員'  => ['体制', '人員計画', '組織', '従業員'],
        '財務計画'    => ['財務', '収支計画', '資金計画', '収益'],
    ];

    // 各項目の存在確認
    foreach ($sections as $sectionName => $keywords) {
        $found = false;
        foreach ($keywords as $kw) {
            if (mb_stripos($normalized, $kw) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $suggestions[] = sprintf('「%s」に関する記述が見当たりません。企業概要やビジョン、市場規模、競合環境、体制、財務計画などの基本項目を網羅しましょう。', $sectionName);
        }
    }

    // 市場調査の有無
    if (mb_stripos($normalized, '市場調査') === false && mb_stripos($normalized, '競合') === false) {
        $suggestions[] = '市場環境や競合分析が不足しています。市場規模・競合状況を調査し、根拠のあるデータを示しましょう【53789604655679†L90-L99】。';
    }

    // 数値データの有無（売上・利益など）
    if (!preg_match('/[0-9]+\s*(億|万|千|百)?/', $normalized) || mb_stripos($normalized, '売上') === false) {
        $suggestions[] = '財務計画に関連する数値や根拠が不足しています。売上予測・収支計画・投資額など具体的な数値を示しましょう。';
    }

    // 文章全体の長さが極端に短い場合は、各項目を深掘りするように促す
    if (mb_strlen($normalized) < 500) {
        $suggestions[] = '全体の記述量が少ないようです。事業計画書では各項目について十分な情報と具体例を盛り込むことが重要です【53789604655679†L90-L99】。';
    }

    // 結果が空なら、全体の内容が網羅されているとみなして総括的な提案を行う
    if (empty($suggestions)) {
        $suggestions[] = '主要な項目は網羅されていますが、内容の具体性や最新の市場データの引用を検討してください。';
    }
    return $suggestions;
}

/**
 * CSV ファイルを読み込み、行ごとに連想配列の配列を返します。
 * カラム名は1行目にあると仮定します。
 *
 * @param string $filePath CSVファイルのパス
 * @return array<int,array<string,string>> 読み込んだデータ
 */
function loadCsvData(string $filePath): array
{
    $data = [];
    if (!is_readable($filePath)) {
        return $data;
    }
    if (($handle = fopen($filePath, 'r')) !== false) {
        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $row;
                continue;
            }
            $entry = [];
            foreach ($row as $index => $value) {
                $key = isset($header[$index]) ? $header[$index] : 'col' . $index;
                $entry[$key] = $value;
            }
            $data[] = $entry;
        }
        fclose($handle);
    }
    return $data;
}

/**
 * 顧客データと観光スポットデータを分析し、事業計画の改善に役立つインサイトを生成します。
 * 例えば人気の観光地ランキングや顧客の年齢層分布を計算し、計画書の販売戦略やターゲット層設定に活かします。
 *
 * @param array $customerData 読み込んだ顧客データ
 * @param array $touristData  読み込んだ観光スポットデータ
 * @return array インサイト情報
 */
function analyzeCustomerAndTouristData(array $customerData, array $touristData): array
{
    $insights = [];
    // 顧客データが存在する場合
    if (!empty($customerData)) {
        // 年齢層の分布を計算（仮に age というカラムがあると想定）
        $ageDistribution = [
            '10代以下' => 0,
            '20代'    => 0,
            '30代'    => 0,
            '40代'    => 0,
            '50代以上' => 0,
        ];
        foreach ($customerData as $entry) {
            if (!isset($entry['age'])) {
                continue;
            }
            $age = (int)$entry['age'];
            if ($age < 20) {
                $ageDistribution['10代以下']++;
            } elseif ($age < 30) {
                $ageDistribution['20代']++;
            } elseif ($age < 40) {
                $ageDistribution['30代']++;
            } elseif ($age < 50) {
                $ageDistribution['40代']++;
            } else {
                $ageDistribution['50代以上']++;
            }
        }
        $insights['ageDistribution'] = $ageDistribution;
    }
    // 観光スポットデータが存在する場合
    if (!empty($touristData)) {
        // 地域別のスポット数を数える（仮に region カラムがあると想定）
        $regionCounts = [];
        foreach ($touristData as $spot) {
            if (!isset($spot['region'])) {
                continue;
            }
            $region = $spot['region'];
            if (!isset($regionCounts[$region])) {
                $regionCounts[$region] = 0;
            }
            $regionCounts[$region]++;
        }
        arsort($regionCounts);
        $insights['popularRegions'] = $regionCounts;
    }
    // ここで他の指標（男女比や訪問頻度など）を追加することもできます
    return $insights;
}

/**
 * 提案文生成用のプロンプトを構築します。
 * ヒューリスティックな改善案とデータインサイトを含め、外部AIに
 * 「この計画を改善するためのアドバイスをしてください」と促します。
 *
 * @param string $planText 計画書本文
 * @param array $heuristicSuggestions ヒューリスティックな改善案
 * @param array $dataInsights データから得られたインサイト
 * @return string AIモデルに渡すプロンプト
 */
function buildPrompt(string $planText, array $heuristicSuggestions, array $dataInsights): string
{
    $prompt  = "以下の事業計画書を読み、必要な改善点を指摘してください。" . PHP_EOL;
    $prompt .= "計画書:" . PHP_EOL . $planText . PHP_EOL;
    if (!empty($heuristicSuggestions)) {
        $prompt .= "\n既に判明している改善ポイント:\n";
        foreach ($heuristicSuggestions as $i => $sug) {
            $prompt .= ($i + 1) . ". " . $sug . "\n";
        }
    }
    if (!empty($dataInsights)) {
        $prompt .= "\n顧客や観光スポットデータから得られたインサイト:\n";
        foreach ($dataInsights as $name => $value) {
            if (is_array($value)) {
                $prompt .= "$name: ";
                $items = [];
                foreach ($value as $key => $val) {
                    $items[] = $key . ' (' . $val . ')';
                }
                $prompt .= implode(', ', $items) . "\n";
            } else {
                $prompt .= "$name: $value\n";
            }
        }
    }
    $prompt .= "\nあなたは経験豊富な経営コンサルタントとして、上記の情報を元に具体的で実行可能な改善提案を日本語で作成してください。";
    return $prompt;
}

/**
 * OpenAI API を呼び出して応答を取得します。
 *
 * $params には以下のキーを指定できます:
 *  - apiKey: OpenAI の API キー（必須）
 *  - model: 使用するモデル名（例: gpt-3.5-turbo, gpt-4o 等）
 *  - endpoint: API エンドポイント URL（任意。指定しない場合は https://api.openai.com/v1/chat/completions）
 *  - temperature: 応答のランダム性を制御する数値（任意）
 *
 * @param string $prompt プロンプト
 * @param array  $params 追加パラメータ
 * @return array 応答結果
 */
function callOpenAI(string $prompt, array $params = []): array
{
    $apiKey    = isset($params['apiKey']) ? $params['apiKey'] : getenv('OPENAI_API_KEY');
    $model     = isset($params['model'])  ? $params['model']  : 'gpt-4o';
    $endpoint  = isset($params['endpoint']) ? $params['endpoint'] : 'https://api.openai.com/v1/chat/completions';
    $temperature = isset($params['temperature']) ? (float)$params['temperature'] : 0.3;
    if (empty($apiKey)) {
        return [
            'error' => 'OpenAI API key is not provided.',
            'model' => 'openai'
        ];
    }
    // リクエストボディ
    $body = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => $temperature,
    ];
    $bodyJson = json_encode($body);

    // HTTP リクエストの送信
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($err)) {
        return [
            'error' => 'Failed to call OpenAI API: ' . $err,
            'model' => 'openai'
        ];
    }
    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data)) {
        return [
            'error' => 'OpenAI API returned HTTP ' . $httpCode,
            'details' => $data,
            'model' => 'openai'
        ];
    }
    // 応答文を抽出
    $choices = $data['choices'] ?? [];
    $content = '';
    if (!empty($choices)) {
        $content = $choices[0]['message']['content'] ?? '';
    }
    return [
        'advice' => $content,
        'model'  => $model,
        'raw'    => $data,
    ];
}

/**
 * Azure OpenAI Service を呼び出して応答を取得します。
 * Azure 固有のパラメータ（endpoint, deploymentId, apiVersion）が必要です。
 *
 * $params には以下のキーを指定します:
 *  - apiKey: 認証用 API キー
 *  - endpoint: エンドポイント URL（例: https://your-resource-name.openai.azure.com）
 *  - deploymentId: デプロイメント名
 *  - apiVersion: API バージョン（例: 2024-02-15-preview）
 *  - temperature: 任意
 *
 * @param string $prompt
 * @param array  $params
 * @return array 応答結果
 */
function callAzureOpenAI(string $prompt, array $params = []): array
{
    $apiKey       = $params['apiKey']       ?? getenv('AZURE_OPENAI_API_KEY');
    $endpoint     = $params['endpoint']     ?? getenv('AZURE_OPENAI_ENDPOINT');
    $deploymentId = $params['deploymentId'] ?? getenv('AZURE_OPENAI_DEPLOYMENT');
    $apiVersion   = $params['apiVersion']   ?? '2024-02-15-preview';
    $temperature  = isset($params['temperature']) ? (float)$params['temperature'] : 0.3;
    if (empty($apiKey) || empty($endpoint) || empty($deploymentId)) {
        return [
            'error' => 'Azure OpenAI API key, endpoint or deploymentId is missing.',
            'model' => 'azure'
        ];
    }
    $url = rtrim($endpoint, '/') . "/openai/deployments/" . rawurlencode($deploymentId) . "/chat/completions?api-version=" . urlencode($apiVersion);
    $body = [
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => $temperature,
    ];
    $bodyJson = json_encode($body);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($response === false || !empty($err)) {
        return [
            'error' => 'Failed to call Azure OpenAI API: ' . $err,
            'model' => 'azure'
        ];
    }
    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data)) {
        return [
            'error' => 'Azure OpenAI API returned HTTP ' . $httpCode,
            'details' => $data,
            'model' => 'azure'
        ];
    }
    $choices = $data['choices'] ?? [];
    $content = '';
    if (!empty($choices)) {
        $content = $choices[0]['message']['content'] ?? '';
    }
    return [
        'advice' => $content,
        'model'  => 'azure-openai',
        'raw'    => $data,
    ];
}

/**
 * Dify API を呼び出して応答を取得します。
 * Dify は OpenAI 互換のエンドポイントを提供しますが、API キーや URL が異なる場合があります。
 *
 * $params には以下のキーを指定します:
 *  - apiKey: API キー
 *  - endpoint: API エンドポイント URL（例: https://api.dify.ai/v1/chat/completions）
 *  - model: 使用するモデル
 *  - temperature: 応答のランダム性
 *
 * @param string $prompt
 * @param array  $params
 * @return array
 */
function callDify(string $prompt, array $params = []): array
{
    $apiKey     = $params['apiKey']    ?? getenv('DIFY_API_KEY');
    $endpoint   = $params['endpoint']  ?? 'https://api.dify.ai/v1/chat/completions';
    $model      = $params['model']     ?? 'gpt-4';
    $temperature= isset($params['temperature']) ? (float)$params['temperature'] : 0.3;
    if (empty($apiKey)) {
        return [
            'error' => 'Dify API key is not provided.',
            'model' => 'dify'
        ];
    }
    $body = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => $temperature,
    ];
    $bodyJson = json_encode($body);
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($response === false || !empty($err)) {
        return [
            'error' => 'Failed to call Dify API: ' . $err,
            'model' => 'dify'
        ];
    }
    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data)) {
        return [
            'error' => 'Dify API returned HTTP ' . $httpCode,
            'details' => $data,
            'model' => 'dify'
        ];
    }
    $choices = $data['choices'] ?? [];
    $content = '';
    if (!empty($choices)) {
        $content = $choices[0]['message']['content'] ?? '';
    }
    return [
        'advice' => $content,
        'model'  => 'dify',
        'raw'    => $data,
    ];
}

/**
 * AIプロバイダへのディスパッチ関数。
 * ヒューリスティックな結果をそのまま返す場合と、指定されたモデルプロバイダへ
 * プロンプトを送信して応答を取得する場合を判定します。
 *
 * @param string $prompt AIに渡すプロンプト
 * @param array  $heuristicResults ヒューリスティックに得られた結果（文字列配列）
 * @param array  $dataInsights データインサイト（分析結果）
 * @param string $provider 利用するプロバイダ名(openai|azure|dify|none)
 * @param array  $params プロバイダに渡す追加パラメータ
 * @return array 応答結果
 */
function dispatchToProvider(string $prompt, array $heuristicResults, array $dataInsights, string $provider, array $params): array
{
    switch ($provider) {
        case 'openai':
            return callOpenAI($prompt, $params);
        case 'azure':
            return callAzureOpenAI($prompt, $params);
        case 'dify':
            return callDify($prompt, $params);
        default:
            // 外部サービスを使用しない場合はヒューリスティックな応答を返す
            return [
                'advice'  => $heuristicResults,
                'insights'=> $dataInsights,
                'model'   => 'heuristic'
            ];
    }
}

/**
 * 一般ユーザー向けの質問や希望に基づいて推奨を生成します。
 * 観光スポットデータから条件に合致する候補を選び、簡単な説明とともに返します。
 *
 * @param string $question ユーザーの質問やメッセージ
 * @param array  $preferences ユーザーの好みや条件（例: region, category, age, budget）
 * @param array  $customerData 顧客データ（未使用だが拡張用）
 * @param array  $touristData 観光スポットデータ
 * @return array 推奨リスト（文字列）
 */
function generateUserRecommendations(string $question, array $preferences, array $customerData, array $touristData): array
{
    $recommendations = [];
    // 観光データがなければ空配列を返す
    if (empty($touristData)) {
        return ['観光スポットデータが見つかりませんでした。'];
    }
    // フィルタ条件を抽出
    $targetRegions = [];
    $targetCategories = [];
    if (isset($preferences['region'])) {
        $targetRegions = is_array($preferences['region']) ? $preferences['region'] : [$preferences['region']];
    }
    if (isset($preferences['category'])) {
        $targetCategories = is_array($preferences['category']) ? $preferences['category'] : [$preferences['category']];
    }
    // キーワード検索: question から簡単なキーワードを抽出（日本語の簡易処理）
    $keywords = [];
    if (!empty($question)) {
        // キーワードとして漢字やひらがな・カタカナで構成された2文字以上の単語を抽出
        if (preg_match_all('/[\p{L}]{2,}/u', $question, $matches)) {
            $keywords = $matches[0];
        }
    }
    // スポットをスコアリング
    $scoredSpots = [];
    foreach ($touristData as $spot) {
        $score = 0;
        // 地域マッチ
        if (!empty($targetRegions) && isset($spot['region']) && in_array($spot['region'], $targetRegions, true)) {
            $score += 3;
        }
        // カテゴリマッチ
        if (!empty($targetCategories) && isset($spot['category']) && in_array($spot['category'], $targetCategories, true)) {
            $score += 2;
        }
        // キーワードマッチ（スポット名や説明に含まれるか）
        foreach ($keywords as $kw) {
            if ((isset($spot['name']) && mb_stripos($spot['name'], $kw) !== false) || (isset($spot['description']) && mb_stripos($spot['description'], $kw) !== false)) {
                $score += 1;
            }
        }
        // 基本の人気指標（ratingや訪問数があれば利用）
        if (isset($spot['rating'])) {
            $score += (float)$spot['rating'];
        }
        if ($score > 0) {
            $scoredSpots[] = ['spot' => $spot, 'score' => $score];
        }
    }
    // スコアが付かなかった場合は全スポットから人気順に抽出
    if (empty($scoredSpots)) {
        foreach ($touristData as $spot) {
            $score = isset($spot['rating']) ? (float)$spot['rating'] : 0;
            $scoredSpots[] = ['spot' => $spot, 'score' => $score];
        }
    }
    // スコア順に並べ替え
    usort($scoredSpots, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    // 上位3件を推奨
    $topN = array_slice($scoredSpots, 0, 3);
    foreach ($topN as $item) {
        $spot = $item['spot'];
        $name = $spot['name'] ?? '名称不明のスポット';
        $region = $spot['region'] ?? '';
        $desc = $spot['description'] ?? '';
        $recommendations[] = $name . (!empty($region) ? "（$region）" : '') . ' - ' . mb_substr($desc, 0, 60) . '...';
    }
    return $recommendations;
}

/**
 * 推薦モード用のプロンプトを構築します。
 * ユーザーの質問・希望とヒューリスティックな推奨を含めて、外部AIにより詳しい提案を依頼します。
 *
 * @param string $question ユーザーの質問
 * @param array  $preferences ユーザーの希望条件
 * @param array  $recommendations ヒューリスティックな推薦結果
 * @return string プロンプト
 */
function buildRecommendationPrompt(string $question, array $preferences, array $recommendations): string
{
    $prompt = "ユーザーからの質問や希望: " . ($question !== '' ? $question : 'なし') . "\n";
    if (!empty($preferences)) {
        $prefParts = [];
        foreach ($preferences as $k => $v) {
            if (is_array($v)) {
                $prefParts[] = $k . '=' . implode('|', $v);
            } else {
                $prefParts[] = $k . '=' . $v;
            }
        }
        $prompt .= "希望条件: " . implode(', ', $prefParts) . "\n";
    }
    if (!empty($recommendations)) {
        $prompt .= "ヒューリスティックな推奨:\n";
        foreach ($recommendations as $i => $rec) {
            $prompt .= ($i + 1) . ". " . $rec . "\n";
        }
    }
    $prompt .= "\n上記の情報を参考にして、ユーザーにとって魅力的で具体的な旅行や体験プランを日本語で提案してください。";
    return $prompt;
}

/**
 * チャットモードでのヒューリスティックな応答を生成します。
 * 履歴や新しいメッセージから簡易なルールベースの回答を返します。
 *
 * @param array  $history 過去の対話履歴（各要素は['role'=>'user'|'assistant','content'=>string]）
 * @param string $message 新しいユーザーからのメッセージ
 * @param array  $customerData 顧客データ
 * @param array  $touristData 観光データ
 * @return string ヒューリスティックな応答文
 */
function generateChatResponse(array $history, string $message, array $customerData, array $touristData): string
{
    $msg = mb_convert_kana($message, 'as');
    // 旅行関連のキーワードが含まれるかチェック
    $travelKeywords = ['おすすめ', '行きたい', '観光', 'スポット', '旅行'];
    foreach ($travelKeywords as $kw) {
        if (mb_stripos($msg, $kw) !== false) {
            // ユーザーの希望に基づいて簡易な推薦を返す
            $recs = generateUserRecommendations($message, [], $customerData, $touristData);
            return 'おすすめのスポットは以下の通りです: ' . implode(' / ', $recs);
        }
    }
    // 事業計画関連のキーワード
    $planKeywords = ['計画', 'ビジネス', '事業', '改善'];
    foreach ($planKeywords as $kw) {
        if (mb_stripos($msg, $kw) !== false) {
            // 簡易に事業計画に関する助言を返す
            return '事業計画の改善については、市場調査や財務計画の具体化が重要です。必要な情報を教えていただければ詳しくお答えします。';
        }
    }
    // 特定のパターンに該当しない場合は汎用応答
    return 'ご質問ありがとうございます。より具体的な内容を教えていただければ、適切な回答をお届けします。';
}

/**
 * チャットモード用のプロンプトを構築します。履歴を自然な会話形式で連結し、
 * ユーザーからの新しいメッセージに対してAIが回答するように誘導します。
 *
 * @param array  $history 過去の会話履歴
 * @param string $message 新しいユーザーメッセージ
 * @return string プロンプト
 */
function buildChatPrompt(array $history, string $message): string
{
    $prompt = "以下はユーザーとAIの対話です。この会話の流れを理解し、最後のユーザーの質問に対してAIとして適切に日本語で返答してください。\n";
    foreach ($history as $turn) {
        if (!isset($turn['role'], $turn['content'])) {
            continue;
        }
        $role = $turn['role'] === 'assistant' ? 'AI' : 'ユーザー';
        $prompt .= $role . ': ' . $turn['content'] . "\n";
    }
    $prompt .= 'ユーザー: ' . $message . "\n";
    $prompt .= 'AI:';
    return $prompt;
}