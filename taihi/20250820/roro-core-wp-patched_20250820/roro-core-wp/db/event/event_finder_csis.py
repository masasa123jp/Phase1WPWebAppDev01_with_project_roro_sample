#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
イベント名をCSVから読み込み、Web検索で
  - 開催日 / 会場 / 住所 / 都道府県 / 自治体 / 参考URL
を取得し、
  - CSIS簡易ジオコーディングAPIで緯度・経度を付与
  - 取得内容の信頼度チェック（照合スコア・要確認理由）
を行ってCSV出力する。

本版では通信まわりを強化し、Bing/DDGS 双方のタイムアウトやネットワーク不調時に
プログラムがクラッシュせず「フォールバック→スキップ」できるように修正済み。

使い方:
  pip install requests beautifulsoup4 lxml pandas ddgs
   # 旧環境の互換用: pip install duckduckgo_search
  python event_finder_csis.py --input input.csv --output output.csv \
      --connect-timeout 10 --read-timeout 30 --sleep 1.0

入力CSV: ヘッダに「イベント名」を含むこと
出力CSV: イベント名,開催日,会場,住所,都道府県,自治体,緯度,経度,参考URL,照合スコア,要確認理由
"""

from __future__ import annotations

import argparse
import csv
import logging
import os
import random
import re
import time
from dataclasses import dataclass
from typing import Optional, Tuple, Dict, List
from urllib.parse import quote

import requests
from bs4 import BeautifulSoup

# --- DuckDuckGo 検索: 新パッケージ ddgs を優先 ---
DDGS = None
_ddgs_runtime_warning_note = None
try:
    # 推奨: 新パッケージ
    from ddgs import DDGS  # type: ignore
    from ddgs.exceptions import TimeoutException as DDGSTimeout  # type: ignore
except Exception:
    try:
        # 互換: 旧パッケージ（改名済み）
        from duckduckgo_search import DDGS  # type: ignore
        DDGSTimeout = Exception  # フォールバック
        _ddgs_runtime_warning_note = (
            "NOTICE: duckduckgo_search は ddgs に改名されています。pip install ddgs を推奨。"
        )
    except Exception:
        DDGS = None
        DDGSTimeout = Exception

# UA/セッション設定
UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/124.0 Safari/537.36"
)

DEFAULT_CONNECT_TIMEOUT = 10
DEFAULT_READ_TIMEOUT = 30

_session = requests.Session()
_session.headers.update({"User-Agent": UA, "Accept-Language": "ja,en-US;q=0.8,en;q=0.6"})

# リトライ戦略（connect/read/HTTPステータス）
try:
    from requests.adapters import HTTPAdapter
    from urllib3.util.retry import Retry

    retry = Retry(
        total=5,
        connect=5,
        read=5,
        backoff_factor=0.8,  # 0.0, 0.8, 1.6, 3.2, ...
        status_forcelist=(429, 500, 502, 503, 504),
        allowed_methods=frozenset(["HEAD", "GET", "OPTIONS"]),
        raise_on_status=False,
        respect_retry_after_header=True,
    )
    adapter = HTTPAdapter(max_retries=retry, pool_connections=50, pool_maxsize=50)
    _session.mount("http://", adapter)
    _session.mount("https://", adapter)
except Exception:
    pass  # 古い環境でも動くように

# 代理接続（必要ならCLI/環境変数で）
_PROXY = os.environ.get("HTTP_PROXY") or os.environ.get("http_proxy")
if _PROXY:
    _session.proxies.update({"http": _PROXY, "https": os.environ.get("HTTPS_PROXY", _PROXY)})

# DDGS セッションは 1 つを再利用（timeout を渡す）
_ddgs = None
if DDGS is not None:
    try:
        # ddgs/duckduckgo_search は timeout オプションをサポート（版により差異あり）
        _ddgs = DDGS(timeout=20)  # 使えない版でも except で握りつぶす
    except Exception:
        try:
            _ddgs = DDGS()
        except Exception:
            _ddgs = None

PREFS = [
    "北海道","青森県","岩手県","宮城県","秋田県","山形県","福島県",
    "茨城県","栃木県","群馬県","埼玉県","千葉県","東京都","神奈川県",
    "新潟県","富山県","石川県","福井県","山梨県","長野県",
    "岐阜県","静岡県","愛知県","三重県",
    "滋賀県","京都府","大阪府","兵庫県","奈良県","和歌山県",
    "鳥取県","島根県","岡山県","広島県","山口県",
    "徳島県","香川県","愛媛県","高知県",
    "福岡県","佐賀県","長崎県","熊本県","大分県","宮崎県","鹿児島県",
    "沖縄県"
]

TRUSTED_DOMAINS = [
    "t-site.jp", "tokyo-dog.jpn.org",
    "aeonmall.com", "city.", "pref.",
]

@dataclass
class EventInfo:
    name: str
    date: str = ""
    venue: str = ""
    address: str = ""
    prefecture: str = ""
    municipality: str = ""
    lat: Optional[float] = None
    lon: Optional[float] = None
    source_url: str = ""
    score: float = 0.0
    issue: str = ""


# ========= 共通ユーティリティ =========

def http_get(url: str, connect_timeout: int, read_timeout: int) -> Optional[str]:
    """GET（例外は握りつぶして None を返す）"""
    try:
        r = _session.get(url, timeout=(connect_timeout, read_timeout))
        logging.info(f"response: {r.url} {r.status_code}")
        r.raise_for_status()
        return r.text
    except requests.exceptions.RequestException as e:
        logging.warning(f"GET失敗: {e}")
        return None


def fetch_html(url: str, connect_timeout: int, read_timeout: int) -> Optional[str]:
    return http_get(url, connect_timeout, read_timeout)


def extract_text(soup: BeautifulSoup) -> str:
    for t in soup(["script", "style", "noscript"]):
        t.extract()
    return soup.get_text("\n", strip=True)


def find_address(text: str) -> str:
    m = re.search(r"(〒\s*\d{3}-\d{4}\s*)?((?:北海道|東?京都|(?:大阪|京都)府|..県).+?)(?:\n|$)", text)
    if m:
        addr = m.group(2)
        return addr.splitlines()[0][:200]
    m = re.search(r"((?:北海道|東?京都|(?:大阪|京都)府|..県).+?)(?:\n|$)", text)
    if m:
        return m.group(1).splitlines()[0][:200]
    return ""


def split_pref_city(address: str) -> Tuple[str, str]:
    if not address:
        return "", ""
    for pref in PREFS:
        if address.startswith(pref):
            rest = address[len(pref):]
            m = re.match(r"^(.+?[市区郡町村])", rest)
            return pref, (m.group(1).strip() if m else "")
    return "", ""


DAY_PAREN = r"(?:（[^）]{1,3}）|\([^)]{1,3}\))?"
DATE_PATTERNS: List[re.Pattern] = [
    re.compile(
        rf"\d{{4}}年\d{{1,2}}月\d{{1,2}}日{DAY_PAREN}(?:\s*[〜~\-–—]\s*\d{{1,2}}月?\d{{1,2}}日{DAY_PAREN})?"
    ),
    re.compile(r"\d{4}[./]\d{1,2}[./]\d{1,2}"),
    re.compile(
        rf"\d{{1,2}}月\d{{1,2}}日{DAY_PAREN}(?:\s*[〜~\-–—]\s*\d{{1,2}}日{DAY_PAREN})?"
    ),
]

def extract_date(text: str) -> str:
    for pat in DATE_PATTERNS:
        m = pat.search(text)
        if m:
            return m.group(0)[:120]
    m = re.search(r"(開催日|日時)[：:\s]*([^\n]{2,60})", text)
    if m:
        return m.group(2).strip()
    return ""


def extract_venue(text: str) -> str:
    m = re.search(r"(会場|場所)[：:\s]*([^\n]{2,100})", text)
    if m:
        return m.group(2).strip()
    m = re.search(r"[@＠]\s*([^\n]{2,100})", text)
    if m:
        return m.group(1).strip()
    return ""


def csis_geocode(address: str, connect_timeout: int, read_timeout: int) -> Tuple[Optional[float], Optional[float]]:
    if not address:
        return None, None
    base = "https://geocode.csis.u-tokyo.ac.jp/cgi-bin/simple_geocode.cgi?charset=UTF8&addr="
    url = base + quote(address)
    html = http_get(url, connect_timeout, read_timeout)
    if not html:
        return None, None
    lat_m = re.search(r"<latitude>([^<]+)</latitude>", html)
    lon_m = re.search(r"<longitude>([^<]+)</longitude>", html)
    lat = float(lat_m.group(1)) if lat_m else None
    lon = float(lon_m.group(1)) if lon_m else None
    return lat, lon


def similarity(a: str, b: str) -> float:
    if not a or not b:
        return 0.0
    a = a.lower()
    b = b.lower()
    at = set(re.split(r"\s|　|[!！?？、。・／/｜|（）()「」『』\-–—:：~〜]", a))
    bt = set(re.split(r"\s|　|[!！?？、。・／/｜|（）()「」『』\-–—:：~〜]", b))
    at.discard("")
    bt.discard("")
    inter = len(at & bt)
    base = max(1, len(at))
    return inter / base


# ========= 検索 =========

def _rank_url(u: str) -> int:
    s = u.lower()
    for d in TRUSTED_DOMAINS:
        if d in s:
            return 0
    return 1


def ddg_candidates(query: str, max_results: int, connect_timeout: int, read_timeout: int) -> List[str]:
    """DDGS（ddgs/duckduckgo_search）: タイムアウト/例外は握りつぶして空配列"""
    if _ddgs is None:
        return []
    out: List[str] = []
    try:
        # ddgs の text() は generator。timelimit を 'y' にして古い情報も拾う
        for r in _ddgs.text(query, region="jp-jp", safesearch="moderate", timelimit="y"):
            u = (r.get("href") or r.get("url") or "").strip()
            if u:
                out.append(u)
            if len(out) >= max_results:
                break
    except DDGSTimeout as e:
        logging.warning(f"DDGS timeout: {e}")
        return []
    except Exception as e:
        logging.warning(f"DDGS search failed: {e}")
        return []
    out.sort(key=_rank_url)
    return out


def bing_candidates(query: str, max_results: int, connect_timeout: int, read_timeout: int) -> List[str]:
    """Bing HTML 結果の簡易スクレイプ。例外は握りつぶして空配列"""
    q = quote(query)
    url = f"https://www.bing.com/search?q={q}&setlang=ja-JP&cc=JP"
    html = http_get(url, connect_timeout, read_timeout)
    if not html:
        logging.warning("Error to search using bing backend: request or response body error")
        return []
    soup = BeautifulSoup(html, "lxml")
    out: List[str] = []
    # Bingは li.b_algo > h2 > a に結果リンクが入るケースが多い
    for a in soup.select("li.b_algo h2 a"):
        href = a.get("href", "").strip()
        if href:
            out.append(href)
        if len(out) >= max_results:
            break
    out.sort(key=_rank_url)
    return out


def search_candidates(query: str, max_results: int, connect_timeout: int, read_timeout: int) -> List[str]:
    """DDG→Bing の順で試行。どちらも失敗してもクラッシュさせない。"""
    urls = ddg_candidates(query, max_results, connect_timeout, read_timeout)
    if not urls:
        urls = bing_candidates(query, max_results, connect_timeout, read_timeout)
    # 重複除去
    seen, uniq = set(), []
    for u in urls:
        if u in seen:
            continue
        seen.add(u)
        uniq.append(u)
    return uniq


# ========= 1イベント処理 =========

def scrape_one(
    name: str,
    sleep_sec: float,
    connect_timeout: int,
    read_timeout: int
) -> EventInfo:
    info = EventInfo(name=name)

    candidates = search_candidates(
        name + " 開催 日程 会場 住所",
        max_results=8,
        connect_timeout=connect_timeout,
        read_timeout=read_timeout,
    )

    tried: List[str] = []
    for pass_idx in (0, 1):
        for url in candidates:
            if url in tried:
                continue
            tried.append(url)
            if pass_idx == 0 and _rank_url(url) != 0:
                continue  # 1周目は信頼ドメインだけ

            html_text = fetch_html(url, connect_timeout, read_timeout)
            if not html_text:
                continue  # タイムアウト/HTTPエラーはスキップ

            soup = BeautifulSoup(html_text, "lxml")
            title = soup.title.get_text(strip=True) if soup.title else ""
            body_text = extract_text(soup)

            address = find_address(body_text)
            date_str = extract_date(body_text)
            venue = extract_venue(body_text)
            if not venue:
                m = re.search(r"(会場|場所)[：:\s]*([^\n]{2,80})", body_text)
                if m:
                    venue = m.group(2).strip()

            pref, muni = split_pref_city(address) if address else ("", "")

            score = max(similarity(name, title), similarity(name, body_text[:200]))
            has_core = (date_str or venue) and (address or pref)
            name_tokens = [t for t in re.split(r"\s|　", name) if t]
            token_hit = any(t in title for t in name_tokens)

            if has_core and (score >= 0.5 or token_hit):
                info.date = date_str
                info.venue = venue
                info.address = address
                info.prefecture = pref
                info.municipality = muni
                info.source_url = url
                info.score = round(score, 2)

                lat, lon = csis_geocode(info.address or f"{info.prefecture}{info.municipality}",
                                        connect_timeout, read_timeout)
                info.lat, info.lon = lat, lon

                issues = []
                if info.score < 0.7:
                    issues.append("イベント名とページの照合スコアが低い")
                if not info.date:
                    issues.append("開催日が未抽出")
                if not info.address and not info.prefecture:
                    issues.append("住所/都道府県が未抽出")
                if info.lat is None or info.lon is None:
                    issues.append("緯度経度（CSIS）未取得")
                info.issue = " / ".join(issues)
                return info

            time.sleep(sleep_sec)

    # ここまで来たら見つからず
    info.issue = "検索タイムアウト/ネットワーク不調または該当ページ未特定"
    return info


# ========= メイン =========

def run(input_csv: str, output_csv: str, sleep_sec: float,
        connect_timeout: int, read_timeout: int):
    if _ddgs_runtime_warning_note:
        logging.warning(_ddgs_runtime_warning_note)

    out_rows: List[Dict[str, object]] = []
    with open(input_csv, newline="", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        if "イベント名" not in reader.fieldnames:
            raise SystemExit("入力CSVに 'イベント名' ヘッダが必要です。")
        for row in reader:
            name = (row.get("イベント名") or "").strip()
            if not name:
                continue
            logging.info(f"検索: {name}")
            info = scrape_one(name, sleep_sec=sleep_sec,
                              connect_timeout=connect_timeout, read_timeout=read_timeout)
            out_rows.append({
                "イベント名": info.name,
                "開催日": info.date,
                "会場": info.venue,
                "住所": info.address,
                "都道府県": info.prefecture,
                "自治体": info.municipality,
                "緯度": info.lat if info.lat is not None else "",
                "経度": info.lon if info.lon is not None else "",
                "参考URL": info.source_url,
                "照合スコア": info.score,
                "要確認理由": info.issue,
            })

    with open(output_csv, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=[
            "イベント名","開催日","会場","住所","都道府県","自治体",
            "緯度","経度","参考URL","照合スコア","要確認理由"
        ])
        writer.writeheader()
        writer.writerows(out_rows)

    print(f"✅ 完了: {output_csv}")


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True, help="入力CSV（ヘッダ: イベント名）")
    ap.add_argument("--output", required=True, help="出力CSV")
    ap.add_argument("--sleep", type=float, default=1.0, help="ページ間スリープ秒（マナー用）")
    ap.add_argument("--connect-timeout", type=int, default=DEFAULT_CONNECT_TIMEOUT,
                    help="接続タイムアウト秒")
    ap.add_argument("--read-timeout", type=int, default=DEFAULT_READ_TIMEOUT,
                    help="読み取りタイムアウト秒")
    args = ap.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(levelname)s: %(message)s")
    run(args.input, args.output, sleep_sec=args.sleep,
        connect_timeout=args.connect_timeout, read_timeout=args.read_timeout)
