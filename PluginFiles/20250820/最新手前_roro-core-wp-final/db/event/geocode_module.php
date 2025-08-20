<?php
/**
 * PHP版ジオコーディングモジュール
 *
 * このモジュールは、住所や郵便番号、施設名やイベント名などのキーワードから
 * 緯度・経度を取得するための関数群を提供します。以下の無料APIを利用します。
 *
 * 1. 国土地理院住所検索API
 *    日本国内の住所や郵便番号を対象にしたAPIで、JSON形式の結果を返します。
 *    `geometry.coordinates` 配列の1番目が経度、2番目が緯度です【899483599696193†L75-L87】。
 *    住所検索に特化しているため、ランドマーク名や建物名では結果が得られない場合があります【336263819784727†L60-L64】。
 *
 * 2. Geocoding.jp API
 *    個人が運営するAPIで、住所やランドマーク名をXMLで返します【292754953015759†L8-L25】。
 *    施設名などのキーワード検索に強い反面、10秒に1回程度の利用制限があります【292754953015759†L31-L33】。
 *
 * 3. Nominatim (OpenStreetMap) API
 *    グローバルなオープンデータを活用するジオコーディングサービス。
 *    無償公開サーバでは1秒に1リクエストが上限とされます【549288001404875†L29-L33】。
 *    日本の住所では精度が落ちることがあるため、最後の手段として利用します。
 *
 * 本モジュールでは上記APIを順番に呼び出し、最初に成功した結果を返します。
 * APIの利用規約やリクエスト間隔に配慮してご利用ください。
 */

/**
 * 国土地理院住所検索APIで住所をジオコーディングする。
 *
 * @param string $query 検索する住所や郵便番号
 * @return array|null [緯度, 経度] の配列。見つからない場合は null。
 */
function geocodeGsi(string $query): ?array
{
    $url = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=' . urlencode($query);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $response === false) {
        return null;
    }
    $data = json_decode($response, true);
    if (!is_array($data) || empty($data)) {
        return null;
    }
    $coords = $data[0]['geometry']['coordinates'] ?? null;
    if (is_array($coords) && count($coords) >= 2) {
        // geometry.coordinates は [経度, 緯度] の順なので入れ替える
        $lon = $coords[0];
        $lat = $coords[1];
        return [$lat, $lon];
    }
    return null;
}

/**
 * Geocoding.jp APIでジオコーディングを行う。
 *
 * @param string $query 検索する住所やランドマーク名
 * @return array|null [緯度, 経度] の配列。見つからない場合は null。
 */
function geocodeGeocodingJp(string $query): ?array
{
    $url = 'https://www.geocoding.jp/api/?q=' . urlencode($query);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // User-Agentを指定しないと403になる場合がある
    curl_setopt($ch, CURLOPT_USERAGENT, 'geocode-php-module');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $response === false) {
        return null;
    }
    // XMLを解析
    $xml = @simplexml_load_string($response);
    if ($xml === false) {
        return null;
    }
    // <lat> と <lng> 要素を取得
    if (isset($xml->coordinate->lat) && isset($xml->coordinate->lng)) {
        $lat = (float)$xml->coordinate->lat;
        $lon = (float)$xml->coordinate->lng;
        return [$lat, $lon];
    }
    return null;
}

/**
 * Nominatim APIでジオコーディングを行う。
 *
 * @param string $query 検索する住所や施設名
 * @return array|null [緯度, 経度] の配列。見つからない場合は null。
 */
function geocodeNominatim(string $query): ?array
{
    $params = http_build_query([
        'q' => $query,
        'format' => 'json',
        'limit' => 1
    ]);
    $url = 'https://nominatim.openstreetmap.org/search?' . $params;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // NominatimではUser-Agentを明示する必要がある
    curl_setopt($ch, CURLOPT_USERAGENT, 'geocode-php-module');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $response === false) {
        return null;
    }
    $data = json_decode($response, true);
    if (is_array($data) && count($data) > 0) {
        $lat = isset($data[0]['lat']) ? (float)$data[0]['lat'] : null;
        $lon = isset($data[0]['lon']) ? (float)$data[0]['lon'] : null;
        if ($lat !== null && $lon !== null) {
            return [$lat, $lon];
        }
    }
    return null;
}

/**
 * 与えられた文字列をジオコーディングし、最初に成功した結果を返す。
 *
 * @param string $query 住所や郵便番号、施設名など
 * @return array|null [緯度, 経度, 利用API名] の配列。失敗した場合は null。
 */
function geocode(string $query): ?array
{
    // 1. 国土地理院APIを試す（主に住所や郵便番号用）
    $result = geocodeGsi($query);
    if ($result !== null) {
        return [$result[0], $result[1], 'GSI'];
    }
    // 2. Geocoding.jpを試す（施設名や建物名にも対応）
    $result = geocodeGeocodingJp($query);
    if ($result !== null) {
        return [$result[0], $result[1], 'Geocoding.jp'];
    }
    // 3. Nominatimを最後の手段として試す
    $result = geocodeNominatim($query);
    if ($result !== null) {
        return [$result[0], $result[1], 'Nominatim'];
    }
    // すべて失敗
    return null;
}

// CLIで実行された場合のサンプル処理
if (PHP_SAPI === 'cli' && isset($argv) && count($argv) > 1) {
    // 第1引数以降を検索クエリとして結合
    $query = implode(' ', array_slice($argv, 1));
    $start = microtime(true);
    $res = geocode($query);
    $elapsed = microtime(true) - $start;
    if ($res !== null) {
        list($lat, $lon, $provider) = $res;
        echo "入力: " . $query . PHP_EOL;
        echo "緯度: " . $lat . "\n経度: " . $lon . PHP_EOL;
        echo "使用API: " . $provider . PHP_EOL;
        echo "処理時間: " . round($elapsed, 3) . " 秒" . PHP_EOL;
    } else {
        echo $query . " の位置情報は見つかりませんでした。" . PHP_EOL;
    }
}
