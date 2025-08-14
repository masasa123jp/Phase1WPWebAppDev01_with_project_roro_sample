"""
fetch_dog_events.py
--------------------

This script provides a unified interface for scraping or otherwise
collecting event information from a variety of dog‑ and pet‑related
websites.  Over time the list of sources has grown beyond the original
handful of sites; the functions contained here reflect that evolution.

In addition to retrieving events, the script now supports filtering
the results by geographic keywords (for example a metropolitan area or
station name) and time period.  You can specify an area or station
name and only events whose name, location or venue contain that
keyword will be returned.  Date filtering applies uniformly to all
sources: events starting before ``--start-date`` or ending after
``--end-date`` will be excluded.

The output format is configurable: choose between JSON (pretty
printed, suitable for further processing) and CSV (first row is a
header, subsequent rows contain event data).  This behaviour is
controlled via the ``--output-format`` option and defaults to JSON.

Supported sources (selectable via ``--site``):

* **latte** – uses the RSS feed published by Latte Channel to extract
  upcoming pet events.
* **happyplace** – pulls posts from the Happyplace WordPress API when
  accessible; alternatively the user can provide previously downloaded
  JSON and parse it via ``parse_happyplace_posts``.
* **dec** – scrapes the public event list on the Dog Event Club
  (https://doglife.info/) site.
* **equall** – scrapes the 2025 edition of the equall LIFE pet event
  roundup.
* **kuroshiba** – extracts nationwide dog event information from the
  "和黒柴な日々" blog post summarising 2025 pet events.  The article
  highlights that going out with dogs has become a norm and compiles
  event information across Japan to help owners find outings
* **wankonowa** – parses the 2025 Kanto dog event article on
  ワンコnowa.  The article was produced by the site's editorial team to
  answer the call for dog events owners can attend with their pets and
  describes a variety of event types including dog‑run fairs,
  breed‑specific gatherings and experiential programs
* **wannyan** – consumes the RSS feed for the "わんにゃんスマイル"
  event category.  The site publishes daily, weekly and monthly
  listings of pet events and cat festivals nationwide and encourages
  dog and cat lovers to discover events across Tokyo, Osaka and
  beyond
* **wepet** – scrapes a WePet article delivering upcoming dog and cat
  event information.  The article includes a section on the charm and
  precautions of dog events as well as an event list with dates and
  locations.
* **amile** – scrapes the AMILE (ペットライフスタイル) event index
  page which hosts event announcements such as the "広島わんわん夏まつり"
  and other nationwide pet fairs.
* **mlit** – scrapes the Ministry of Land, Infrastructure, Transport
  and Tourism (MLIT) public‑private partnership portal for the
  "地域のイベント情報" table, capturing organiser, date and
  description fields for each listing.
* **goguynet** – retrieves the latest headlines from the 号外NET
  regional news site, which publishes local event and gourmet news
  written by regional correspondents.

Each scraper function returns a list of dictionaries with at minimum
the following keys: ``name``, ``date``, ``location``, ``venue`` and
``source``.  Some functions also populate ``url`` and ``description``.

Basic usage (from the command line)::

    python fetch_dog_events.py --site latte --keyword マルシェ --start-date 2025-08-01

Call ``python fetch_dog_events.py --help`` for more information on
available options.

Notes
~~~~~
* Some sites restrict automated access or rely on client‑side
  JavaScript.  The scrapers here make best efforts to parse static
  content or RSS feeds but may fall back to returning an empty list.
* New sources can be added by following the pattern in
  ``fetch_kuroshiba_events`` and registering the function in the
  ``SITE_DISPATCH`` dictionary.

--------------------

このスクリプトは犬・ペット関連の各種ウェブサイトから
イベント情報をスクレイピング（またはその他の方法で収集）する
統一インターフェースを提供します。対象サイトは当初の数サイトから
拡大しており、本モジュール内の関数はその進化を反映しています。

イベント取得に加え、地理キーワード（例：都市圏名や駅名）および
期間での絞り込みをサポートします。エリア名または駅名を指定すると、
イベント名・開催地・会場にそのキーワードを含むものだけが返されます。
日付フィルタはすべてのソースに一律で適用され、
``--start-date`` より前に開始する、または ``--end-date`` より後に終了する
イベントは除外されます。

出力形式は JSON（整形済み、後処理向け）と CSV（1行目ヘッダー）の
いずれかを選択できます。``--output-format`` で制御し、デフォルトは JSON です。

対応ソース（``--site`` で選択）:

* **latte** – Latte Channel が公開する RSS フィードからペットイベントを取得
* **happyplace** – Happyplace の WordPress API から投稿を取得
  （オフライン JSON を ``parse_happyplace_posts`` で解析することも可）
* **dec** – Dog Event Club 公式サイトのイベント一覧を解析
* **equall** – equall LIFE による 2025 年版ペットイベントまとめ記事を解析
* **kuroshiba** – ブログ「和黒柴な日々」の全国犬イベント記事を解析
* **wankonowa** – ワンコnowa の 2025 年関東犬イベント記事を解析
* **wannyan** – 「わんにゃんスマイル」カテゴリ RSS を解析
* **wepet** – WePet の犬猫イベント紹介記事を解析
* **amile** – AMILE (ペットライフスタイル) のイベント一覧ページを解析
* **mlit** – 国交省 官民連携まちづくりポータルの「地域のイベント情報」表を解析
* **goguynet** – 号外NET の地域ニュース記事タイトルを取得

各スクレイパ関数は最低限 ``name``, ``date``, ``location``, ``venue``, ``source`` を含む辞書のリストを返します。サイトにより ``url`` と ``description`` も含まれます。

基本的な使い方::

    python fetch_dog_events.py --site latte --keyword マルシェ --start-date 2025-08-01

オプション一覧は ``python fetch_dog_events.py --help`` を参照してください。

注意
~~~~~
* 一部サイトは自動アクセスを制限したり、JavaScript でコンテンツを生成します。
  本スクレイパは静的 HTML や RSS を解析しますが、取得できない場合は
  空リストを返すことがあります。
* 新しいソースは ``fetch_kuroshiba_events`` の実装例を参考にし、
  ``SITE_DISPATCH`` に関数を登録することで追加可能です。


# ---------- 共通ユーティリティ ----------
# HTML を取得して BeautifulSoup オブジェクトを返す
#   retries: ネットワーク不安定時の簡易リトライ回数
#   注意: Cloudflare などでブロックされる場合は None を返す

# date_range_overlaps(d1, d2, start, end)
#   イベント期間とユーザー指定期間の重複を判定する

# ---------- Scraper: Latte Channel ----------
# RSS <item> のタイトル・本文から日付・場所・会場を抽出

# ---------- Scraper: Happyplace ----------
# WordPress REST API /posts を取得し本文から開催日・場所を抽出

# ---------- Scraper: Dog Event Club (DEC) ----------
# トップページの「YYYY.MM.DD Sun イベント名【会場】」形式を解析

# ---------- Scraper: equall LIFE ----------
# h3見出しごとにイベント名、その後の<p>から日付・場所を取得

# ---------- 以下、新規追加 Scraper ----------

# kuro-shiba.net（和黒柴な日々）
#   h3見出しと続くリストから日付・場所を解析

# wankonowa.com
#   目次リンクと情報ブロックを解析し日付・会場を取得

# wannyan-smile.com
#   RSS タイトル「YYYY/MM/DD ... in ◯◯」を解析

# wepet.jp
#   h3見出し＋テーブルの「日時」「場所」行を取得

# pet-lifestyle.com（AMILE）
#   カードコンポーネントからタイトル・日付を抽出

# mlit.go.jp/toshi/local-event
#   <table> 行から主催・日程・内容を取得

# goguynet.jp
#   <article> タイトルに「イベント」を含む最新投稿を取得

# ---------- コマンドライン処理 ----------
# argparse で --site, --area, --station, --start-date, --end-date,
#            --keyword, --output-format, --limit, --max-pages を受け付け
# 1) SITE_DISPATCH から関数呼び出し
# 2) _filter_events でキーワード・期間フィルタ
# 3) 出力フォーマットで JSON/CSV を出力
# 4) エラー時は警告を出し空出力

"""



import argparse
import datetime as _dt
import json
import re
import sys
from html import unescape
from typing import Any, Dict, Iterable, List, Optional

try:
    from bs4 import BeautifulSoup  # type: ignore
except ImportError:
    raise SystemExit("BeautifulSoup4 is required. Install with `pip install beautifulsoup4`.")

import requests


def _parse_date_range(text: str) -> str:
    """Parse a date or date range from a string and return it in
    ISO8601 format.  Handles patterns like '8月23日(土)～8月24日(日)' or
    '9月6日'.  Returns the input if no date is detected.
    """
    # Replace Japanese date markers with separators
    text = text.strip()
    # Patterns: YYYY?年?M月D日, ranges separated by ～ or -
    # We'll simply remove Japanese characters and split on non‑digit markers
    pattern = r"(?:(\d{4})年)?(\d{1,2})月(\d{1,2})日"
    dates = re.findall(pattern, text)
    if not dates:
        return text
    parsed_dates: List[str] = []
    current_year = _dt.date.today().year
    for year, month, day in dates:
        y = int(year) if year else current_year
        try:
            date = _dt.date(y, int(month), int(day)).isoformat()
        except ValueError:
            continue
        parsed_dates.append(date)
    if len(parsed_dates) == 1:
        return parsed_dates[0]
    return f"{parsed_dates[0]} to {parsed_dates[-1]}"


def fetch_latte_events(
    keyword: Optional[str] = None,
    start_date: Optional[str] = None,
    end_date: Optional[str] = None,
) -> List[Dict[str, Any]]:
    """Fetch events from Latte Channel's RSS feed and scrape details from each article.

    Latte Channel publishes individual articles for each upcoming pet event and
    exposes them via a WordPress RSS feed.  The original implementation
    relied solely on the feed description to derive a date and discarded
    location and venue information.  However, each linked article contains
    structured information such as the event name (名称), date/time
    (開催日時) and venue/address (会場) within the body.  This revised
    scraper follows the RSS links and parses these fields from the page
    whenever possible.  If the article cannot be retrieved or does not
    contain the expected markers, the scraper falls back to using the
    feed title and attempts to extract a date from the description.

    :param keyword: If provided, only events containing this keyword in
        the article title or description are returned.
    :param start_date: ISO8601 date string; events before this date
        are excluded (if a date is detectable).
    :param end_date: ISO8601 date string; events after this date are
        excluded.
    :returns: A list of event dictionaries with keys ``name``, ``date``,
        ``location``, ``venue``, ``source`` and ``url``.
    """
    feed_url = "https://lattechannel.com/category/pet-events/feed/"
    try:
        resp = requests.get(feed_url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "xml")
    events: List[Dict[str, Any]] = []
    for item in soup.find_all("item"):
        # Basic metadata from the feed
        title = item.title.get_text(strip=True)
        link = item.link.get_text(strip=True)
        description = unescape(item.description.get_text(strip=True)) if item.description else ""
        # Skip early if keyword does not match feed title/description
        if keyword and keyword not in title and keyword not in description:
            continue
        name: str = title
        date_str: str = ""
        location: str = ""
        venue: str = ""
        # Attempt to fetch the article page to extract structured info
        try:
            art = requests.get(link, timeout=10)
            art.raise_for_status()
            page = BeautifulSoup(art.content, "html.parser")
            # Extract the article heading (often includes the prefecture/city in brackets)
            heading_elem = page.find(["h1", "h2"])
            if heading_elem:
                heading_text = heading_elem.get_text(strip=True)
                # Capture bracketed location like 【群馬県北群馬郡】
                loc_match = re.search(r"【([^】]+)】", heading_text)
                if loc_match:
                    location = loc_match.group(1)
            # Flatten text for regex search
            page_text = page.get_text("\n", strip=True)
            # Event name from "名称：" line, else fallback to feed title
            name_match = re.search(r"名称[:：]\s*([^\n]+)", page_text)
            if name_match:
                name = name_match.group(1).strip()
            # Date/time from "開催日時：" or "開催日：" line
            dt_match = re.search(r"開催日(?:時)?[:：]\s*([^\n]+)", page_text)
            if dt_match:
                # Keep the raw Japanese date/time; convert simple single‑day dates to ISO when possible
                date_candidate = dt_match.group(1).strip()
                # Attempt to parse patterns like '2025年10月11日（土）' or '10月11日（土）、12日（日）'
                # Extract first date range occurrence and convert to ISO
                dates = re.findall(r"(\d{4})?年?(\d{1,2})月(\d{1,2})日", date_candidate)
                if dates:
                    # Convert only the first date
                    y, m, d = dates[0]
                    year = int(y) if y else _dt.date.today().year
                    try:
                        date_str = _dt.date(year, int(m), int(d)).isoformat()
                    except ValueError:
                        date_str = date_candidate
                else:
                    date_str = date_candidate
            # Venue/address from "会場：" line
            venue_match = re.search(r"会場[:：]\s*([^\n]+)", page_text)
            if venue_match:
                venue = venue_match.group(1).strip()
        except Exception:
            # Fallback: attempt to parse a simple date from feed description
            date_match = re.search(r"(\d{1,2})月(\d{1,2})日", description)
            if date_match:
                month, day = date_match.groups()
                year = _dt.date.today().year
                try:
                    date_str = _dt.date(year, int(month), int(day)).isoformat()
                except ValueError:
                    date_str = ""
        # Build the event record
        event = {
            "name": name,
            "date": date_str,
            "location": location,
            "venue": venue,
            "source": "LatteChannel",
            "url": link,
        }
        events.append(event)
    # Apply date filtering if requested
    def within_dates(ev: Dict[str, Any]) -> bool:
        # If the event date is a single ISO date string, compare lexically
        if start_date and ev["date"]:
            try:
                if len(ev["date"]) == 10 and ev["date"] < start_date:
                    return False
            except Exception:
                pass
        if end_date and ev["date"]:
            try:
                if len(ev["date"]) == 10 and ev["date"] > end_date:
                    return False
            except Exception:
                pass
        return True
    return [e for e in events if within_dates(e)]


def fetch_dec_events() -> List[Dict[str, Any]]:
    """Fetch events from Dog Event Club (DEC).

    This scraper attempts to parse the event listing on the
    ``https://doglife.info/`` homepage.  Each line on the page
    contains a date and an event name with a venue in parentheses.
    """
    url = "https://doglife.info/"
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    # Find text segments that look like "2025.08.31 Sun" followed by event name
    text = soup.get_text("\n")
    pattern = re.compile(r"(\d{4}\.\d{2}\.\d{2})\s+\w+\s+([^【\n]+)【?([^\n]*)", re.MULTILINE)
    for match in pattern.finditer(text):
        date_raw, name, venue_raw = match.groups()
        date_iso = date_raw.replace(".", "-")
        venue = venue_raw.strip()
        events.append({
            "name": name.strip(),
            "date": date_iso,
            "location": "",
            "venue": venue,
            "source": "DogEventClub",
            "url": url
        })
    return events


def fetch_equall_events(month: Optional[int] = None) -> List[Dict[str, Any]]:
    """Fetch events from equall LIFE's 2025 pet event article.

    :param month: Optional month (1–12) to filter events by their
        approximate month.  If None, returns all events found.
    """
    url = "https://media.equall.jp/archives/10445"
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    # Each event appears as an h3 tag followed by details
    for header in soup.find_all(["h2", "h3"]):
        name = header.get_text(strip=True)
        # Look for following sibling paragraphs for date and venue
        details = []
        for sib in header.find_next_siblings():
            if sib.name and sib.name.startswith("h"):
                break
            details.append(sib.get_text(" ", strip=True))
        info_text = " ".join(details)
        date_match = re.search(r"(\d{1,2})月(\d{1,2})日(?:.*?〜(\d{1,2})月(\d{1,2})日)?", info_text)
        date_str = ""
        if date_match:
            m1, d1, m2, d2 = date_match.groups(default="")
            y = _dt.date.today().year
            try:
                start = _dt.date(y, int(m1), int(d1)).isoformat()
                if m2 and d2:
                    end = _dt.date(y, int(m2), int(d2)).isoformat()
                    date_str = f"{start} to {end}"
                else:
                    date_str = start
            except ValueError:
                date_str = ""
        location = ""
        venue_match = re.search(r"会場\s*(.*?)\s", info_text)
        if venue_match:
            location = venue_match.group(1)
        # Filter by month if requested
        if month and date_str:
            try:
                m = int(date_str.split("-")[1])
                if m != month:
                    continue
            except Exception:
                pass
        events.append({
            "name": name,
            "date": date_str,
            "location": location,
            "venue": "",
            "source": "equall LIFE",
            "url": url
        })
    return events


def fetch_happyplace_posts(page: int = 1, per_page: int = 100) -> List[Dict[str, Any]]:
    """Internal helper to call Happyplace WordPress API.

    Returns a list of raw post objects.
    """
    api_url = f"https://happyplace.pet/wp-json/wp/v2/posts?categories=7&page={page}&per_page={per_page}"
    try:
        resp = requests.get(api_url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    return resp.json()


def parse_happyplace_posts(posts: Iterable[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """Parse a list of Happyplace posts into event dictionaries.

    This parser extracts the event name from the post title and
    attempts to find dates and location within the rendered content.
    """
    events: List[Dict[str, Any]] = []
    for post in posts:
        title = unescape(post.get("title", {}).get("rendered", "")).strip()
        content_html = post.get("content", {}).get("rendered", "")
        soup = BeautifulSoup(content_html, "html.parser")
        text = soup.get_text(" ", strip=True)
        date_str = ""
        date_match = re.search(r"(\d{4})\.?(\d{1,2})\.?(\d{1,2})", text)
        if date_match:
            y, m, d = map(int, date_match.groups())
            try:
                date_str = _dt.date(y, m, d).isoformat()
            except ValueError:
                date_str = ""
        location = ""
        loc_match = re.search(r"会場[:：]\s*(.*?)\s", text)
        if loc_match:
            location = loc_match.group(1)
        # If no location was found in the content, attempt to extract a
        # bracketed location from the title.  Many Happyplace event
        # posts prefix the event name with a bracket containing the
        # prefecture and city, e.g. "【東京都渋谷区】イベント名".  Capturing
        # this string provides at least a coarse location that can be
        # geocoded by the general augmentation routine.
        if not location:
            m = re.search(r"【([^】]+)】", title)
            if m:
                location = m.group(1)
        events.append({
            "name": title,
            "date": date_str,
            "location": location,
            "venue": "",
            "source": "Happyplace",
            "url": post.get("link")
        })
    return events


def fetch_happyplace_events(keyword: Optional[str] = None,
                            max_pages: int = 3) -> List[Dict[str, Any]]:
    """Fetch events from Happyplace using the WordPress API.

    :param keyword: Filter posts whose title contains this keyword.
    :param max_pages: Number of API pages to fetch (each returns up to
        100 posts).
    """
    all_posts: List[Dict[str, Any]] = []
    for page in range(1, max_pages + 1):
        posts = fetch_happyplace_posts(page)
        if not posts:
            break
        all_posts.extend(posts)
        if len(posts) < 100:
            break
    events = parse_happyplace_posts(all_posts)
    if keyword:
        events = [e for e in events if keyword in e["name"]]
    return events


def fetch_kuroshiba_events() -> List[Dict[str, Any]]:
    """Scrape dog event information from 和黒柴な日々.

    The target article summarises pet and dog events across Japan for
    2025 and promises to update information as new events are
    announced【808016132049752†screenshot】.  This scraper attempts to extract each
    event entry (if possible) by looking for list items or headings
    containing a date and event name.
    """
    url = "https://kuro-shiba.net/post-9112/"
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    # Events often appear as h3 tags followed by lists
    for h3 in soup.find_all("h3"):
        name = h3.get_text(strip=True)
        # Look for date in subsequent text
        details = h3.find_next_sibling()
        date_text = ""
        location = ""
        if details:
            txt = details.get_text(" ", strip=True)
            date_text_match = re.search(r"(\d{1,2})月(\d{1,2})日(?:〜(\d{1,2})月(\d{1,2})日)?", txt)
            if date_text_match:
                m1, d1, m2, d2 = date_text_match.groups(default="")
                y = _dt.date.today().year
                try:
                    start = _dt.date(y, int(m1), int(d1)).isoformat()
                    if m2 and d2:
                        end = _dt.date(y, int(m2), int(d2)).isoformat()
                        date_text = f"{start} to {end}"
                    else:
                        date_text = start
                except ValueError:
                    date_text = ""
            # Attempt to parse location within parentheses
            loc_match = re.search(r"（([^）]+)）", txt)
            if loc_match:
                location = loc_match.group(1)
        events.append({
            "name": name,
            "date": date_text,
            "location": location,
            "venue": "",
            "source": "和黒柴な日々",
            "url": url
        })
    return events


def fetch_wankonowa_events(keyword: Optional[str] = None) -> List[Dict[str, Any]]:
    """Scrape events from ワンコnowa's 2025 Kanto dog event article.

    The article groups events by municipality and describes
    dog‑run festivals, free dog runs, breed‑specific events and other
    experiential outings【783787798218371†L54-L58】.  This scraper extracts each
    heading (prefecture) and event name along with date and venue
    information from the Information blocks.
    """
    url = "https://wankonowa.com/column/event/1426/"
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    for header in soup.find_all("h2"):
        event_name = header.get_text(strip=True)
        # Skip the article title which is at the top
        if "ドッグイベント" in event_name and "版" in event_name:
            continue
        info = header.find_next("div", class_=re.compile("information", re.IGNORECASE))
        date = ""
        location = ""
        venue = ""
        if info:
            text = info.get_text(" ", strip=True)
            # Find date range
            date = _parse_date_range(text)
            # Extract location and venue if present
            loc_match = re.search(r"住所\s*[:：]?\s*([\d\wー\-一-龥]+)", text)
            if loc_match:
                location = loc_match.group(1)
            venue_match = re.search(r"会場\s*[:：]?\s*(.*?)\s", text)
            if venue_match:
                venue = venue_match.group(1)
        if keyword and keyword not in event_name:
            continue
        events.append({
            "name": event_name,
            "date": date,
            "location": location,
            "venue": venue,
            "source": "ワンコnowa",
            "url": url
        })
    return events


def fetch_wannyan_smile_events(keyword: Optional[str] = None,
                               start_date: Optional[str] = None,
                               end_date: Optional[str] = None) -> List[Dict[str, Any]]:
    """Fetch events from わんにゃんスマイル via its category RSS feed.

    :param keyword: Filter events containing this keyword in the title.
    :param start_date: Exclude events before this ISO8601 date.
    :param end_date: Exclude events after this date.
    """
    feed_url = "https://wannyan-smile.com/column-list/c1/feed/"
    try:
        resp = requests.get(feed_url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "xml")
    events: List[Dict[str, Any]] = []
    for item in soup.find_all("item"):
        title = item.title.get_text(strip=True)
        link = item.link.get_text(strip=True)
        description = unescape(item.description.get_text(strip=True)) if item.description else ""
        # Attempt to extract date from description (Japanese dates)
        date = _parse_date_range(description)
        if start_date and date and date < start_date:
            continue
        if end_date and date and date > end_date:
            continue
        if keyword and keyword not in title and keyword not in description:
            continue
        location = ""
        loc_match = re.search(r"(\d+年)?(\d{1,2})月(\d{1,2})日.*?\s*(.*?)\s", description)
        if loc_match:
            location = loc_match.group(4)
        events.append({
            "name": title,
            "date": date,
            "location": location,
            "venue": "",
            "source": "わんにゃんスマイル",
            "url": link,
            "description": description
        })
    return events


def fetch_wepet_events() -> List[Dict[str, Any]]:
    """Scrape pet event information from WePet's article.

    Returns a list of events described in the article at
    ``https://www.wepet.jp/event/607/``.  Each event entry is presented
    in the article as a table or section containing a title, date and
    venue.  The article also notes that it delivers information on
    upcoming dog and cat events and links to a video on the charm and
    cautions of dog events【527591041152128†screenshot】.
    """
    url = "https://www.wepet.jp/event/607/"
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    # Each event appears under an h3 heading with a following table
    for section in soup.find_all(["h3", "h2"]):
        heading = section.get_text(strip=True)
        # Skip sections that are not events (like charm/caution headings)
        if not heading or "イベント" not in heading:
            continue
        table = section.find_next("table")
        date = ""
        location = ""
        venue = ""
        if table:
            rows = table.find_all("tr")
            for tr in rows:
                th_text = tr.find("th").get_text(strip=True) if tr.find("th") else ""
                td_text = tr.find("td").get_text(" ", strip=True) if tr.find("td") else ""
                if "日時" in th_text or "開催日" in th_text:
                    date = _parse_date_range(td_text)
                elif "場所" in th_text or "会場" in th_text:
                    venue = td_text
                elif "会場" in th_text:
                    venue = td_text
        events.append({
            "name": heading,
            "date": date,
            "location": location,
            "venue": venue,
            "source": "WePet",
            "url": url
        })
    return events


def fetch_amile_events() -> List[Dict[str, Any]]:
    """Scrape pet event information from AMILE (ペットライフスタイル).

    AMILE (ペットライフスタイル) publishes a list of event articles at
    https://pet-lifestyle.com/events/.  Each card on this page links to an
    article containing detailed information about one or more pet events.
    Earlier versions of this scraper attempted to extract event data
    directly from the list page and often returned zero results because
    the markup uses generic ``<div>`` elements rather than ``<article>``
    tags.  This implementation takes a two‑stage approach:

    1. Fetch the listing page and collect all event article URLs.  Cards
       are represented by anchors with the class ``BlogEntry__title`` or
       nested within a ``blogCard`` container.  The link target (``href``)
       is extracted for each card.
    2. For each article, download the page and parse individual event
       sections.  Event sections are marked by heading elements (``<h2>``
       with an ``id`` beginning with ``__link__``) and are followed by
       paragraphs containing ``<strong>日時：`` and ``<strong>住所：`` (or
       ``場所：``) metadata.  If a page does not contain any such
       headings, the page title is used as the event name and the first
       ``日時``/``住所`` pair is extracted from the body.

    The parser attempts to normalise dates to ISO 8601 (YYYY-MM-DD) by
    capturing the first ``YYYY年M月D日`` sequence.  Location strings are
    captured verbatim from the ``住所``/``場所`` field.  Venue
    information is not explicitly available and is therefore left blank.

    :returns: A list of dictionaries, each representing a single event with
        keys ``name``, ``date``, ``location``, ``venue``, ``source`` and
        ``url``.
    """
    base_url = "https://pet-lifestyle.com"
    listing_url = f"{base_url}/events/"
    try:
        resp = requests.get(listing_url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    # Collect links to event articles.  Some cards embed the link in a
    # BlogEntry__title class while others nest it in a blogCard anchor.
    article_links: List[str] = []
    # From v0.3 we search for card titles using the BlogEntry__title class.
    for title_div in soup.find_all("div", class_=lambda x: x and "BlogEntry__title" in x):
        a = title_div.find("a", href=True)
        if a:
            href = a["href"]
            # Ensure absolute URL
            if href.startswith("/"):
                href = base_url + href
            article_links.append(href)
    # If no article links were found (markup might have changed), fall back
    # to parsing anchors inside blogCard containers.
    if not article_links:
        for card in soup.find_all("div", class_=lambda x: x and "blogCard" in x):
            a = card.find("a", href=True)
            if a:
                href = a["href"]
                if href.startswith("/"):
                    href = base_url + href
                article_links.append(href)
    events: List[Dict[str, Any]] = []
    for article_url in article_links:
        try:
            aresp = requests.get(article_url, timeout=10)
            aresp.raise_for_status()
        except Exception:
            # Skip articles that cannot be fetched
            continue
        a_soup = BeautifulSoup(aresp.content, "html.parser")
        # Determine a base event name from the page title.  This will be
        # used if no individual event sections are found.
        page_title_tag = a_soup.find("title")
        page_title = page_title_tag.get_text(strip=True) if page_title_tag else ""
        # Extract event sections marked by headings with id="__link__*".
        section_headings = a_soup.find_all(["h1", "h2", "h3"], id=re.compile(r"__link__"))
        if section_headings:
            for heading in section_headings:
                name = heading.get_text(strip=True)
                # Find the next paragraph that contains date and address info
                date_str = ""
                location = ""
                # Traverse siblings until we hit another heading or end
                node = heading.find_next_sibling()
                while node and node.name not in ["h1", "h2", "h3"]:
                    text = node.get_text(" ", strip=True)
                    if not date_str:
                        # Look for YYYY年M月D日 pattern
                        m = re.search(r"(\d{4})年(\d{1,2})月(\d{1,2})日", text)
                        if m:
                            y, mo, d = m.groups()
                            try:
                                date_str = _dt.date(int(y), int(mo), int(d)).isoformat()
                            except ValueError:
                                date_str = ""
                    if not location:
                        loc_match = re.search(r"(?:住所|場所)：\s*([^<\n]+)", text)
                        if loc_match:
                            location = loc_match.group(1).strip()
                    if date_str and location:
                        break
                    node = node.find_next_sibling()
                events.append({
                    "name": name,
                    "date": date_str,
                    "location": location,
                    "venue": "",
                    "source": "AMILE",
                    "url": article_url,
                })
        else:
            # Fallback: try to extract a single event from the article
            body_text = a_soup.get_text(" ", strip=True)
            date_str = ""
            location = ""
            m = re.search(r"(\d{4})年(\d{1,2})月(\d{1,2})日", body_text)
            if m:
                y, mo, d = m.groups()
                try:
                    date_str = _dt.date(int(y), int(mo), int(d)).isoformat()
                except ValueError:
                    date_str = ""
            loc_match = re.search(r"(?:住所|場所)：\s*([^<\n]+)", body_text)
            if loc_match:
                location = loc_match.group(1).strip()
            if page_title:
                events.append({
                    "name": page_title,
                    "date": date_str,
                    "location": location,
                    "venue": "",
                    "source": "AMILE",
                    "url": article_url,
                })
    return events


def fetch_mlit_events() -> List[Dict[str, Any]]:
    """Scrape event listings from MLIT's "地域のイベント情報" page.

    The MLIT portal for "地域のイベント情報" no longer uses a traditional HTML
    `<table>` to render event data.  Instead, it builds an unordered list
    (`<ul id="js-le-table">`) populated via client‑side JavaScript.  Each
    event is represented by an `<li>` with the class `le-table-row row-data`
    containing three `<div>` elements: the first holds the organiser and
    location, the second holds the date string, and the third contains
    the event title (often with a hyperlink) and optional flyers.  This
    updated parser extracts those elements directly from the static HTML
    returned by the server, which includes the prepopulated list.  If
    the expected structure is not found, an empty list is returned.

    :returns: A list of dictionaries with keys ``name``, ``date``, ``location``
        and ``url``.  Venue information is generally not provided by MLIT,
        so the ``venue`` field is left blank.
    """
    url = "https://www.mlit.go.jp/toshi/local-event/"
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    ul = soup.find("ul", id="js-le-table")
    if not ul:
        return events
    for li in ul.find_all("li", class_=lambda x: x and "row-data" in x.split()):
        items = li.find_all("div", class_="row-item")
        if len(items) < 3:
            continue
        organiser = items[0].get_text(" ", strip=True)
        date_text = items[1].get_text(" ", strip=True)
        content_div = items[2]
        # Extract event name and link (if any)
        # Some entries include a link followed by " / チラシ" etc.
        # Among all <a> tags, pick the first one whose text is not 'チラシ'
        name = ""
        link = url
        anchors = content_div.find_all("a")
        for a in anchors:
            text = a.get_text(strip=True)
            if text and "チラシ" not in text:
                name = text
                link = a.get("href") or url
                break
        if not name:
            # Fallback: use the raw text before any slash
            raw = content_div.get_text(" ", strip=True)
            name = raw.split("/")[0].strip()
            link = url
        # If the link is relative (starts with "../"), resolve it against the base URL
        if link.startswith("../"):
            link = requests.compat.urljoin(url, link)
        # Normalize date separators by replacing fullwidth comma and tilde with comma+space
        date_norm = date_text.replace("、", ", ").replace("～", " to ")
        events.append({
            "name": name,
            "date": date_norm,
            "location": organiser,
            "venue": "",
            "source": "MLIT",
            "url": link,
        })
    return events


def fetch_goguynet_events(limit: int = 10) -> List[Dict[str, Any]]:
    """Fetch recent event headlines from 号外NET.

    号外NET is a regional news aggregation site that reports on local
    events, new store openings and regional topics【361177046474770†L28-L31】.  The
    top page (https://goguynet.jp/) contains a grid of article cards.  Each
    card is an anchor (`<a>`) with the class ``itemTitle01``.  Within
    the card a label span (``span.label-default``) identifies the
    category (e.g., ``イベント`` for events or ``開店/閉店`` for
    store openings), a headline appears inside a nested `<h1>` tag,
    and a `<div class="listDate01">` holds the publish timestamp.  This
    parser selects only cards whose label is ``イベント`` and extracts
    the city and event name from the headline.  The publication date
    (not the event date) is retained in the ``date`` field.  A
    ``limit`` parameter restricts the number of returned items.

    Note that 号外NET delegates detailed event information to separate
    regional subdomains.  This function surfaces only the most recent
    event headlines and their links; it does not attempt to parse
    individual subdomain pages for additional details.

    :param limit: Maximum number of event entries to return.
    :returns: A list of dictionaries with keys ``name``, ``date``,
        ``location``, ``venue``, ``source`` and ``url``.
    """
    base_url = "https://goguynet.jp/"
    try:
        resp = requests.get(base_url, timeout=10)
        resp.raise_for_status()
    except Exception:
        return []
    soup = BeautifulSoup(resp.content, "html.parser")
    events: List[Dict[str, Any]] = []
    # Find all article anchors on the front page.  These anchors wrap the
    # thumbnail, label, title and date.  We filter by the label text.
    anchors = soup.find_all("a", class_=lambda x: x and "itemTitle01" in x)
    for a in anchors:
        # Extract category label (イベント, 開店/閉店, 話題, etc.)
        label_el = a.select_one("span.label-default")
        if not label_el:
            continue
        category = label_el.get_text(strip=True)
        if "イベント" not in category:
            continue
        # Extract headline text.  It is wrapped in <h1 class="itemTitle01In"> with
        # an inner <span> containing the actual text.
        title_el = a.select_one("h1.itemTitle01In span")
        title = title_el.get_text(strip=True) if title_el else a.get_text(strip=True)
        # Extract publish date/time
        date_el = a.select_one("div.listDate01 span")
        date_text = date_el.get_text(strip=True) if date_el else ""
        # Attempt to derive location from the bracketed prefix (e.g., 【浜松市】)
        location = ""
        m = re.match(r"^【([^】]+)】", title)
        if m:
            location = m.group(1)
            # Remove the bracketed part from the name
            name = title[m.end():].lstrip()
        else:
            name = title
        events.append({
            "name": name,
            "date": date_text,
            "location": location,
            "venue": "",
            "source": "号外NET",
            "url": a.get("href") or base_url,
        })
        if len(events) >= limit:
            break
    return events


SITE_DISPATCH = {
    "latte": fetch_latte_events,
    "happyplace": fetch_happyplace_events,
    "dec": fetch_dec_events,
    "equall": fetch_equall_events,
    "kuroshiba": fetch_kuroshiba_events,
    "wankonowa": fetch_wankonowa_events,
    "wannyan": fetch_wannyan_smile_events,
    "wepet": fetch_wepet_events,
    "amile": fetch_amile_events,
    "mlit": fetch_mlit_events,
    "goguynet": fetch_goguynet_events,
}


def _filter_events(events: Iterable[Dict[str, Any]],
                   area: Optional[str] = None,
                   station: Optional[str] = None,
                   start_date: Optional[str] = None,
                   end_date: Optional[str] = None) -> List[Dict[str, Any]]:
    """Filter a sequence of event dictionaries.

    :param area: Keep events where the area keyword appears in the
        name, location or venue fields (case‑insensitive).
    :param station: Keep events where the station keyword appears in
        the name, location or venue fields (case‑insensitive).
    :param start_date: Only include events that occur on or after this
        ISO8601 date.  For events spanning multiple days the start
        date of the range is used.
    :param end_date: Only include events that occur on or before this
        ISO8601 date.  For events spanning multiple days the end
        date of the range is used.
    """
    def matches_keyword(event: Dict[str, Any], keyword: str) -> bool:
        keyword_lower = keyword.lower()
        for field in [event.get("name", ""), event.get("location", ""), event.get("venue", "")]:
            if keyword_lower in str(field).lower():
                return True
        return False

    def within_date(ev: Dict[str, Any]) -> bool:
        if not (start_date or end_date):
            return True
        date_field = ev.get("date", "")
        if not date_field:
            return True
        # If the date field contains a range, split on 'to'
        parts = [p.strip() for p in date_field.split("to")]
        try:
            start = parts[0]
            end = parts[-1]
        except Exception:
            start = end = date_field
        if start_date and start < start_date:
            return False
        if end_date and end > end_date:
            return False
        return True

    filtered: List[Dict[str, Any]] = []
    for ev in events:
        if area and not matches_keyword(ev, area):
            continue
        if station and not matches_keyword(ev, station):
            continue
        if not within_date(ev):
            continue
        filtered.append(ev)
    return filtered


# -----------------------------------------------------------------------------
# Location augmentation utilities
def _split_pref_city(location: str) -> (str, str):
    """
    Split a Japanese location string into prefecture and city parts.

    The ``location`` field of an event often contains a prefecture
    followed by a city or district, for example ``"群馬県北群馬郡"``.  This
    helper uses a regular expression to capture the smallest prefix
    ending with ``都``, ``道``, ``府`` or ``県`` as the prefecture and
    returns the remainder as the city.  If the location does not
    contain a prefecture suffix, it is treated entirely as a city.

    :param location: Original location string (may be empty).
    :returns: Tuple ``(prefecture, city)``; either part may be blank if
        not identifiable.
    """
    if not location:
        return "", ""
    m = re.match(r"(.+?[都道府県])\s*(.*)", location)
    if m:
        pref, city = m.group(1), m.group(2)
        return pref.strip(), city.strip()
    # No prefecture suffix – treat the whole string as city
    return "", location.strip()


def _augment_events_with_geo(events: Iterable[Dict[str, Any]]) -> List[Dict[str, Any]]:
    """
    Augment each event dictionary with additional location fields.

    This function adds the following keys to each event:

    * ``address`` – a best‑effort textual address, derived from the
      event's venue if present, otherwise from the location.
    * ``prefecture`` – the prefecture name extracted from the
      ``location`` field using ``_split_pref_city``.
    * ``city`` – the remainder of the location after removing the
      prefecture.
    * ``lat`` and ``lon`` – approximate latitude and longitude of the
      prefecture centre.  These are looked up via a lazy geocoding
      helper that uses OpenStreetMap's Nominatim service.  If the
      prefecture is unknown or geocoding fails, empty strings are
      returned.

    :param events: Iterable of event dicts to augment.
    :returns: A new list of events with added fields.
    """
    augmented: List[Dict[str, Any]] = []
    for ev in events:
        # Copy the event to avoid mutating the original list
        new_ev = dict(ev)
        # Derive address
        addr = new_ev.get("venue") or new_ev.get("location") or ""
        new_ev["address"] = addr
        # Split location into prefecture and city
        pref, city = _split_pref_city(str(new_ev.get("location", "")))
        new_ev["prefecture"] = pref
        new_ev["city"] = city
        # Lazy geocode prefecture to lat/lon if prefecture exists
        lat = lon = ""
        if pref:
            lat, lon = _get_prefecture_coords(pref)
        # If no coordinates yet (e.g. no prefecture found), attempt to
        # geocode the city or address.  Compose a query from available
        # pieces: address, prefecture and city.  Using the most
        # specific information first increases the chance of a precise
        # result.
        if not lat and not lon:
            # Prefer to geocode based on prefecture and city only to
            # minimise the number of unique queries (full addresses tend
            # to be unique and would result in many API requests).
            query = ""
            if pref or city:
                # Build "pref city" query, omitting empty parts
                query = " ".join(part for part in [pref, city] if part).strip()
            # If both prefecture and city are empty but an address exists,
            # fall back to using the address; otherwise use the raw
            # location string.
            if not query:
                if new_ev.get("address"):
                    query = str(new_ev["address"]).strip()
                elif new_ev.get("location"):
                    query = str(new_ev["location"]).strip()
            # Perform geocoding if we have a query
            if query:
                lat, lon = _get_generic_coords(query)
        new_ev["lat"] = lat
        new_ev["lon"] = lon
        # Final fallback: if coordinates are still missing, attempt to
        # geocode the event name itself.  This can help when the
        # location fields do not provide a prefecture or city.  Use
        # only the part of the name outside of any bracketed prefix
        # (e.g. "【東京都】イベント名" -> "イベント名") to avoid
        # confusing the geocoder.  Append "Japan" to constrain the
        # search to Japan.  When geocoding the name we also request
        # address details; if found we populate the ``address``,
        # ``prefecture`` and ``city`` fields accordingly.
        if not new_ev["lat"] and not new_ev["lon"]:
            # Avoid expensive name‑based geocoding for Happyplace events.
            # These events may number in the hundreds, so geocoding
            # each title individually would result in excessive API calls
            # and long execution times.  Instead, rely on location
            # extracted from the title or content.  For other sources,
            # fallback to geocoding the event name to obtain a
            # coarse location.
            if new_ev.get("source") != "Happyplace":
                name = str(new_ev.get("name", "")).strip()
                # Remove bracketed prefix like 【◯◯】 if present
                cleaned = re.sub(r"^【[^】]+】", "", name)
                if cleaned:
                    query = f"{cleaned} Japan"
                    lat2, lon2, addr2 = _get_generic_coords_and_address(query)
                    if lat2 and lon2:
                        new_ev["lat"] = lat2
                        new_ev["lon"] = lon2
                        # Update address if not already set or if it is blank
                        if not new_ev.get("address"):
                            new_ev["address"] = addr2
                        # Attempt to update prefecture/city from the geocoded address
                        if addr2:
                            parts = [p.strip() for p in addr2.split(",")]
                            pref_candidate = ""
                            city_candidate = ""
                            for i in range(len(parts) - 1):
                                s = parts[i]
                                if re.search(r"[都道府県]$", s):
                                    pref_candidate = s
                                    city_candidate = parts[i - 1] if i >= 1 else ""
                                    break
                            if pref_candidate and not new_ev.get("prefecture"):
                                new_ev["prefecture"] = pref_candidate
                            if city_candidate and not new_ev.get("city"):
                                new_ev["city"] = city_candidate
        augmented.append(new_ev)
    return augmented


# Cache for prefecture coordinates to avoid redundant geocoding
from typing import Tuple

# Cache mapping prefecture names to (lat, lon) coordinates.  Tuple[str, str]
_PREF_COORD_CACHE: Dict[str, Tuple[str, str]] = {}

def _get_prefecture_coords(prefecture: str) -> Tuple[str, str]:
    """
    Return the latitude and longitude for a given prefecture.

    This function caches results to minimise network requests.  It
    queries the OpenStreetMap Nominatim API for the prefecture name
    followed by "Japan" and returns a tuple of strings (lat, lon).
    If the lookup fails or returns no results, two empty strings are
    returned.  The User‑Agent header is set to identify the scraper.

    :param prefecture: Name of the prefecture (e.g. "群馬県").
    :returns: Tuple (lat, lon) as strings.
    """
    from urllib.parse import urlencode
    global _PREF_COORD_CACHE
    if not prefecture:
        return "", ""
    if prefecture in _PREF_COORD_CACHE:
        return _PREF_COORD_CACHE[prefecture]
    try:
        params = {"q": f"{prefecture} Japan", "format": "json", "limit": 1}
        url = "https://nominatim.openstreetmap.org/search?" + urlencode(params)
        resp = requests.get(url, headers={"User-Agent": "fetch-dog-events/0.3"}, timeout=5)
        if resp.status_code == 200:
            data = resp.json()
            if data:
                lat = data[0].get("lat", "")
                lon = data[0].get("lon", "")
                _PREF_COORD_CACHE[prefecture] = (lat, lon)
                return lat, lon
    except Exception:
        pass
    _PREF_COORD_CACHE[prefecture] = ("", "")
    return "", ""


# Cache for generic geocode lookups to avoid excessive API calls.
_GENERIC_COORD_CACHE: Dict[str, Tuple[str, str]] = {}
# Cache mapping arbitrary geocode queries to (lat, lon, address) tuples.  This
# cache is separate from the lat/lon cache above because when we
# request address details from the Nominatim API the response
# includes a ``display_name`` string describing the full location.  We
# store this along with the coordinates so that repeated queries
# return both the coordinates and a human‑readable address without
# issuing another network request.
_GENERIC_ADDR_CACHE: Dict[str, Tuple[str, str, str]] = {}

def _get_generic_coords(query: str) -> Tuple[str, str]:
    """
    Geocode an arbitrary query string using the OpenStreetMap Nominatim API.

    This helper takes a free‑form address string (for example "長岡京市
    京都府" or a full venue address) and returns a tuple of strings
    ``(lat, lon)`` if successful.  Results are cached by query string to
    minimise redundant network requests.

    :param query: Free‑form address query to geocode.
    :returns: Tuple (lat, lon) as strings; empty strings if lookup fails.
    """
    from urllib.parse import urlencode
    global _GENERIC_COORD_CACHE
    if not query:
        return "", ""
    if query in _GENERIC_COORD_CACHE:
        return _GENERIC_COORD_CACHE[query]
    try:
        params = {"q": query, "format": "json", "limit": 1}
        url = "https://nominatim.openstreetmap.org/search?" + urlencode(params)
        resp = requests.get(url, headers={"User-Agent": "fetch-dog-events/0.3"}, timeout=5)
        if resp.status_code == 200:
            data = resp.json()
            if data:
                lat = data[0].get("lat", "")
                lon = data[0].get("lon", "")
                _GENERIC_COORD_CACHE[query] = (lat, lon)
                return lat, lon
    except Exception:
        pass
    _GENERIC_COORD_CACHE[query] = ("", "")
    return "", ""

def _get_generic_coords_and_address(query: str) -> Tuple[str, str, str]:
    """
    Geocode an arbitrary query and return latitude, longitude and a
    human‑readable address.  This helper extends
    ``_get_generic_coords`` by requesting address details from
    Nominatim.  It caches results by query to minimise network
    traffic.  The address is returned as the ``display_name`` field
    from the geocoder, which typically contains a comma‑separated
    description of the place.

    :param query: Free‑form address or event name to geocode.
    :returns: Tuple ``(lat, lon, address)``.  Empty strings are
        returned if the geocode fails or yields no results.
    """
    from urllib.parse import urlencode
    global _GENERIC_ADDR_CACHE
    if not query:
        return "", "", ""
    if query in _GENERIC_ADDR_CACHE:
        return _GENERIC_ADDR_CACHE[query]
    try:
        params = {
            "q": query,
            "format": "json",
            "addressdetails": 1,
            "limit": 1
        }
        url = "https://nominatim.openstreetmap.org/search?" + urlencode(params)
        resp = requests.get(
            url,
            headers={"User-Agent": "fetch-dog-events/0.3"},
            timeout=5
        )
        if resp.status_code == 200:
            data = resp.json()
            if data:
                entry = data[0]
                lat = entry.get("lat", "")
                lon = entry.get("lon", "")
                address = entry.get("display_name", "")
                _GENERIC_ADDR_CACHE[query] = (lat, lon, address)
                return lat, lon, address
    except Exception:
        pass
    _GENERIC_ADDR_CACHE[query] = ("", "", "")
    return "", "", ""


def _output_events(events: Iterable[Dict[str, Any]], fmt: str) -> None:
    """
    Output events in either JSON or CSV format.

    :param events: Iterable of event dictionaries to output.
    :param fmt: Either ``"json"`` or ``"csv"``.  JSON is pretty printed,
        CSV prints a header row followed by comma‑separated rows.

    This function inspects the keys of the first event (if any) and
    constructs a list of field names to emit.  At minimum the
    following fields are included: ``name``, ``date``, ``location``,
    ``venue``, ``address``, ``prefecture``, ``city``, ``source`` and
    ``url``.  Additional keys present on the event dictionaries are
    preserved in JSON output but will not be included in the CSV
    header unless explicitly added here.
    """
    ev_list = list(events)
    if fmt == "csv":
        # Always include core fields in a sensible order.  Use any
        # additional keys present on the first event as an indicator
        # that the user has augmented the event objects.
        # Include lat/lon as standard fields.  These columns appear after
        # the geographic subdivisions to support downstream analysis.
        core_fields = [
            "name", "date", "location", "venue", "address",
            "prefecture", "city", "lat", "lon", "source", "url"
        ]
        # Build the fieldnames list by preserving the order of core_fields
        # and appending any extra keys from the first event that are
        # not already present.  This ensures backwards compatibility.
        extra_fields: List[str] = []
        if ev_list:
            for k in ev_list[0].keys():
                if k not in core_fields:
                    extra_fields.append(k)
        fieldnames = core_fields + extra_fields
        # Print header row
        print(",".join(fieldnames))
        for ev in ev_list:
            row = []
            for fn in fieldnames:
                value = ev.get(fn, "")
                # Replace commas in values with spaces to avoid CSV
                # delimiter collisions
                row.append(str(value).replace(",", " "))
            print(",".join(row))
    else:
        print(json.dumps(ev_list, ensure_ascii=False, indent=2))


def main(argv: List[str]) -> None:
    parser = argparse.ArgumentParser(description="Fetch dog/pet events from various sites")
    parser.add_argument("--site", required=True, choices=SITE_DISPATCH.keys(),
                        help="Site name to fetch events from")
    parser.add_argument("--keyword", help="Filter events containing this keyword")
    parser.add_argument("--start-date", help="Filter events on or after this ISO date (YYYY-MM-DD)")
    parser.add_argument("--end-date", help="Filter events on or before this ISO date (YYYY-MM-DD)")
    parser.add_argument("--month", type=int, help="Month (1-12) to filter equall LIFE events")
    parser.add_argument("--max-pages", type=int, default=3, help="Maximum pages to fetch for Happyplace (default 3)")
    parser.add_argument("--limit", type=int, default=10, help="Limit of items to fetch for Goguynet front page")
    parser.add_argument("--area", help="Geographic keyword (metropolitan area or city) to filter events")
    parser.add_argument("--station", help="Station name keyword to filter events")
    parser.add_argument("--output-format", choices=["json", "csv"], default="json",
                        help="Output format: 'json' (default) or 'csv'")
    args = parser.parse_args(argv)
    site = args.site
    fn = SITE_DISPATCH.get(site)
    if not fn:
        print(f"Unsupported site: {site}", file=sys.stderr)
        return
    kwargs: Dict[str, Any] = {}
    # Pass through site‑specific options
    if site in {"latte", "wannyan"}:
        if args.keyword:
            kwargs["keyword"] = args.keyword
        if args.start_date:
            kwargs["start_date"] = args.start_date
        if args.end_date:
            kwargs["end_date"] = args.end_date
    if site == "equall" and args.month:
        kwargs["month"] = args.month
    if site == "happyplace":
        if args.keyword:
            kwargs["keyword"] = args.keyword
        kwargs["max_pages"] = args.max_pages
    if site == "wankonowa" and args.keyword:
        kwargs["keyword"] = args.keyword
    if site == "goguynet":
        kwargs["limit"] = args.limit
    # Fetch events from the selected site
    events: List[Dict[str, Any]] = fn(**kwargs) if kwargs else fn()
    # Apply global filters based on keyword and date range
    events = _filter_events(events,
                            area=args.area,
                            station=args.station,
                            start_date=args.start_date,
                            end_date=args.end_date)
    # Augment events with additional location fields (address, prefecture, city, lat, lon)
    events = _augment_events_with_geo(events)
    # Output in chosen format
    _output_events(events, args.output_format)


if __name__ == "__main__":
    main(sys.argv[1:])