#!/usr/bin/env python3
"""
住所や郵便番号、施設名、イベント名、建物名などの自由な文字列から
緯度・経度を取得するためのシンプルなジオコーディングツール。

このスクリプトでは、以下の無料のジオコーディングサービスを順番に呼び出して
結果を取得します。

1. **国土地理院の住所検索API**
   日本国内の住所や郵便番号から緯度・経度を取得できます。登録不要で
   JSON 形式の結果を返し、緯度は `geometry.coordinates[1]`、経度は
   `geometry.coordinates[0]` に含まれています【899483599696193†L75-L87】。
   住所検索に特化しているため施設名やランドマーク名では結果が得られない
   ことがありますが、無料でCORS対応という利点があります【336263819784727†L60-L64】。

2. **Geocoding.jp API**
   個人が運営する API で、住所やランドマーク名から緯度・経度を取得し
   XML を返します【292754953015759†L8-L25】。検索頻度は 10 秒に1回程度に
   抑える必要があります【292754953015759†L31-L33】。施設名や建物名などの
   キーワード検索にも対応しており、GSI API で結果が出ない場合の
   フォールバックとして利用します【336263819784727†L39-L43】。

3. **Nominatim（OpenStreetMap）**
   オープンデータを利用したグローバルなジオコーディングサービスです。
   無償で利用できますが、利用ポリシーにより「1 秒あたり最大 1 リクエスト」
   などの制限があります【549288001404875†L29-L33】。海外の施設名やイベント名にも
   対応しますが、日本語住所の場合は GSI API や geocoding.jp ほどの精度は期待できません。

このスクリプトは順にサービスを呼び出して最初に成功した結果を返します。
制約として、各APIの利用規約に従ってリクエスト間隔を空ける必要があります。
必要に応じて `time.sleep()` を挿入してください。
"""

import requests
import urllib.parse
import xml.etree.ElementTree as ET
import time
from typing import Optional, Tuple, Dict

# APIのエンドポイント定義
GSI_URL = "https://msearch.gsi.go.jp/address-search/AddressSearch"
GEOCODING_JP_URL = "https://www.geocoding.jp/api/"
NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"


def geocode_gsi(query: str) -> Optional[Tuple[float, float]]:
    """国土地理院APIで住所をジオコーディングする。

    パラメータ:
        query: 住所や郵便番号の文字列

    戻り値:
        (latitude, longitude) のタプル。該当がなければ None。
    """
    # URLエンコードしてリクエスト
    params = {'q': query}
    try:
        response = requests.get(GSI_URL, params=params, timeout=10)
        response.raise_for_status()
        data = response.json()
        if not data:
            return None
        # geometry.coordinates は [経度, 緯度] の順【899483599696193†L75-L87】
        first = data[0]
        coords = first.get('geometry', {}).get('coordinates', None)
        if coords and len(coords) >= 2:
            lon, lat = coords[0], coords[1]
            return (lat, lon)
    except Exception:
        return None
    return None


def geocode_geocodingjp(query: str) -> Optional[Tuple[float, float]]:
    """Geocoding.jp API で住所や施設名をジオコーディングする。

    パラメータ:
        query: 住所やランドマーク名などの文字列

    戻り値:
        (latitude, longitude) のタプル。該当がなければ None。
    """
    params = {'q': query}
    # Geocoding.jp は個人運営であり、利用者は 10 秒に1回のアクセスが推奨されます【292754953015759†L31-L33】。
    try:
        response = requests.get(GEOCODING_JP_URL, params=params, timeout=10, headers={'User-Agent': 'geocode-script'})
        response.raise_for_status()
        # XMLを解析
        xml_root = ET.fromstring(response.text)
        # <lat> と <lng> を抽出
        lat_elem = xml_root.find('.//lat')
        lng_elem = xml_root.find('.//lng')
        if lat_elem is not None and lng_elem is not None:
            lat = float(lat_elem.text)
            lon = float(lng_elem.text)
            return (lat, lon)
    except Exception:
        return None
    return None


def geocode_nominatim(query: str) -> Optional[Tuple[float, float]]:
    """Nominatim(OpenStreetMap) API でジオコーディングする。

    パラメータ:
        query: 住所や施設名の文字列

    戻り値:
        (latitude, longitude) のタプル。該当がなければ None。
    """
    params = {
        'q': query,
        'format': 'json',
        'limit': 1
    }
    # Nominatim の利用にはUser-Agentの指定が必要で、1秒に1リクエストの制限があります【549288001404875†L29-L33】。
    try:
        response = requests.get(NOMINATIM_URL, params=params, timeout=10, headers={'User-Agent': 'geocode-script'})
        response.raise_for_status()
        data = response.json()
        if data:
            # 緯度経度は文字列で返されるので float に変換
            lat = float(data[0]['lat'])
            lon = float(data[0]['lon'])
            return (lat, lon)
    except Exception:
        return None
    return None


def geocode(query: str) -> Optional[Tuple[float, float, str]]:
    """入力された文字列をジオコーディングし、最初に成功した結果を返す。

    戻り値は `(latitude, longitude, provider)` 形式で、provider は使用した API 名
    を示します。全て失敗した場合は None を返します。
    """
    # 1. 国土地理院APIを試す（住所や郵便番号向け）
    result = geocode_gsi(query)
    if result:
        lat, lon = result
        return (lat, lon, 'GSI')
    # 2. Geocoding.jp を試す（施設名やランドマーク向け）
    result = geocode_geocodingjp(query)
    if result:
        lat, lon = result
        return (lat, lon, 'Geocoding.jp')
    # 3. Nominatim を試す（最後の手段として）
    result = geocode_nominatim(query)
    if result:
        lat, lon = result
        return (lat, lon, 'Nominatim')
    return None


def main():
    """コマンドラインから呼び出された場合の処理。

    第1引数に住所や施設名を指定すると、その緯度経度を表示します。
    または、CSVファイルを指定して各行をジオコーディングする拡張も可能です。
    """
    import sys
    if len(sys.argv) < 2:
        print("使い方: python geocode.py <住所または施設名>")
        return
    query = ' '.join(sys.argv[1:])
    start_time = time.time()
    result = geocode(query)
    elapsed = time.time() - start_time
    if result:
        lat, lon, provider = result
        print(f"入力: {query}")
        print(f"緯度: {lat}, 経度: {lon}")
        print(f"使用API: {provider}")
        print(f"処理時間: {elapsed:.2f} 秒")
    else:
        print(f"{query} の位置情報は見つかりませんでした。")


if __name__ == '__main__':
    main()
