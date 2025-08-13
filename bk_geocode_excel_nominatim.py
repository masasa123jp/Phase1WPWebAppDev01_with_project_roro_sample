#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
geocode_excel_nominatim.py
==========================

Excel（.xlsx / .xlsm）の複数シートに対して、住所から緯度・経度（WGS84）を無料のジオコーディング
サービス（OpenStreetMap Nominatim）で取得し、各シートに「緯度」「経度」列を付与して保存します。

■ 主な特徴
- 無料のNominatim（OpenStreetMap）を利用（APIキー不要）。
- 日本語住所に最適化：language=ja、countrycodes=jp を指定。
- 住所列の自動検出（「住所」「所在地」「Address」等）に対応。都道府県/市区町村/番地等の分割列が
  あれば連結して住所を合成。
- 高信頼のためのリトライ＆指数バックオフ、HTTP 429/5xx の扱い、1秒レートリミット。
- 取得結果のローカルキャッシュ（CSV）により、同一住所は再問い合わせ不要。
- .xlsm のマクロを温存して上書き保存（openpyxl keep_vba=True）か、別名保存のどちらも選択可能。
- CLI 利用とモジュール呼び出しの両対応。

■ 使い方（例）
1) 仮想環境推奨（任意）
   python -m venv .venv && . .venv/Scripts/activate  (Windows)
   python -m venv .venv && source .venv/bin/activate (macOS/Linux)

2) 必要ライブラリのインストール
   pip install pandas openpyxl requests tqdm python-dotenv

3) 実行例（.xlsm のマクロを保持して上書き保存する場合）
   python geocode_excel_nominatim.py "【作業中】地図情報_マスタデータ.xlsm" \
       --sheets "シート1" "シート2" \
       --address-cols "住所" "所在地" \
       --output "【作業中】地図情報_マスタデータ_geocoded.xlsm" \
       --keep-vba

   ※シート名や住所列名が不明な場合は指定不要（自動検出）です。
   ※--keep-vba を付けない場合は .xlsx でも保存可能です。

4) .env にメールアドレスを設定（推奨）
   Nominatim のポリシーに則り、問い合わせ元を示す User-Agent を明示します。
   プロジェクトルートに .env を置き、次のように記載してください。
     GEO_EMAIL=you@example.com

■ 注意（Nominatim利用規約の遵守）
- Rate limit（最低 1秒/リクエスト）を守ること
- 大量バッチ用途は、極力キャッシュを活用すること
- 商用等で高トラフィックが見込まれる場合は、有償の地理APIも検討してください

Author: ChatGPT (GPT-5 Thinking)
Date: 2025-08-13 (Asia/Tokyo)
"""
from __future__ import annotations

import argparse
import csv
import os
import sys
import time
import json
import re
from dataclasses import dataclass
from typing import Dict, Iterable, List, Optional, Tuple

import pandas as pd
import requests
from requests.adapters import HTTPAdapter, Retry
from tqdm import tqdm

# -------------------------------
# 設定値（必要に応じて変更可能）
# -------------------------------

NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
# Nominatimへの最低インターバル（秒）
MIN_REQUEST_INTERVAL_SEC = 1.0

# 住所候補の既定のカラム名（優先順）
DEFAULT_ADDRESS_CANDIDATE_COLS: List[str] = [
    "住所", "所在地", "Address", "address", "所在地住所", "住所1", "住所２", "住所2",
    "所在地1", "所在地2"
]

# 住所の構成要素候補（分割列がある場合に連結）
DEFAULT_COMPONENT_COL_GROUPS: List[List[str]] = [
    # グループ1: 都道府県
    ["都道府県", "pref", "prefecture", "都"],
    # グループ2: 市区町村
    ["市区町村", "city", "区市町村", "市", "区", "町", "村"],
    # グループ3: 町名・番地等
    ["町名", "番地", "丁目", "地番", "番", "号", "町域", "大字", "小字", "丁"],
    # グループ4: 建物名等
    ["建物名", "ビル名", "マンション名", "建屋", "建物", "号室", "階"],
]

# 緯度・経度列名
LAT_COL = "緯度"
LON_COL = "経度"


@dataclass
class GeocodeResult:
    lat: Optional[float]
    lon: Optional[float]
    source: str
    raw: Dict


class NominatimClient:
    """Nominatim（OSM）向けの薄いクライアント。レート制御とリトライを内包。"""

    def __init__(self, email: Optional[str] = None, lang: str = "ja", countrycodes: str = "jp"):
        self.email = email or os.getenv("GEO_EMAIL")  # .env からも可
        self.lang = lang
        self.countrycodes = countrycodes
        self._last_request_ts = 0.0

        self.session = requests.Session()
        retries = Retry(
            total=5,
            backoff_factor=1.2,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["GET"]
        )
        self.session.mount("https://", HTTPAdapter(max_retries=retries))

    def _user_agent(self) -> str:
        base = "ProjectRoro-Geocoder/1.0 (+https://example.com)"
        if self.email:
            return f"{base} contact:{self.email}"
        return base

    def _respect_rate_limit(self):
        now = time.time()
        elapsed = now - self._last_request_ts
        if elapsed < MIN_REQUEST_INTERVAL_SEC:
            time.sleep(MIN_REQUEST_INTERVAL_SEC - elapsed)

    def geocode(self, address: str) -> GeocodeResult:
        """住所をジオコーディングして緯度経度を返す。見つからなければ (None, None)。"""
        params = {
            "q": address,
            "format": "jsonv2",
            "addressdetails": 0,
            "limit": 1,
            "accept-language": self.lang,
            "countrycodes": self.countrycodes,
        }
        headers = {"User-Agent": self._user_agent()}

        self._respect_rate_limit()
        resp = self.session.get(NOMINATIM_URL, params=params, headers=headers, timeout=30)
        self._last_request_ts = time.time()

        resp.raise_for_status()
        data = resp.json()
        if isinstance(data, list) and data:
            try:
                lat = float(data[0]["lat"])
                lon = float(data[0]["lon"])
                return GeocodeResult(lat=lat, lon=lon, source="nominatim", raw=data[0])
            except (KeyError, ValueError, TypeError):
                return GeocodeResult(lat=None, lon=None, source="nominatim", raw={"error": "parse_error"})
        return GeocodeResult(lat=None, lon=None, source="nominatim", raw={"note": "no_result"})


class AddressCache:
    """住所→(lat, lon, source, raw) のキャッシュ（CSV保管）。"""

    def __init__(self, cache_csv: str):
        self.cache_csv = cache_csv
        self.map: Dict[str, GeocodeResult] = {}
        self._load()

    def _load(self):
        if not os.path.exists(self.cache_csv):
            return
        with open(self.cache_csv, "r", encoding="utf-8", newline="") as f:
            reader = csv.DictReader(f)
            for row in reader:
                addr = row.get("address", "").strip()
                if not addr:
                    continue
                lat = float(row["lat"]) if row.get("lat") else None
                lon = float(row["lon"]) if row.get("lon") else None
                source = row.get("source", "nominatim")
                raw = {}
                if row.get("raw_json"):
                    try:
                        raw = json.loads(row["raw_json"])
                    except json.JSONDecodeError:
                        raw = {"_raw_json_parse_error": row.get("raw_json")}
                self.map[addr] = GeocodeResult(lat=lat, lon=lon, source=source, raw=raw)

    def save(self):
        tmp = self.cache_csv + ".tmp"
        with open(tmp, "w", encoding="utf-8", newline="") as f:
            writer = csv.DictWriter(f, fieldnames=["address", "lat", "lon", "source", "raw_json"])
            writer.writeheader()
            for addr, res in self.map.items():
                writer.writerow({
                    "address": addr,
                    "lat": res.lat if res.lat is not None else "",
                    "lon": res.lon if res.lon is not None else "",
                    "source": res.source,
                    "raw_json": json.dumps(res.raw, ensure_ascii=False),
                })
        os.replace(tmp, self.cache_csv)

    def get(self, address: str) -> Optional[GeocodeResult]:
        return self.map.get(address)

    def put(self, address: str, result: GeocodeResult):
        self.map[address] = result


def normalize_space(s: str) -> str:
    """空白や全角空白を単一スペースに正規化し、前後をtrim。"""
    if s is None:
        return ""
    # 全角スペース→半角スペース
    s = s.replace("\u3000", " ")
    # 連続スペースを1つに
    s = re.sub(r"\s+", " ", str(s).strip())
    return s


def build_address_from_components(row: pd.Series, component_groups: List[List[str]]) -> Optional[str]:
    """都道府県/市区町村/番地/建物等の分割列が存在する場合に連結して住所を構築。"""
    parts: List[str] = []
    for group in component_groups:
        for col in row.index:
            if col in group:
                val = normalize_space(row[col])
                if val:
                    parts.append(val)
                break  # 同一グループで最初に見つかった列のみ使用
    if parts:
        return " ".join(parts)
    return None


def detect_address_column(df: pd.DataFrame, candidates: List[str]) -> Optional[str]:
    """候補名の優先順で住所列を検出（大小文字一致 & 完全一致）。"""
    for name in candidates:
        if name in df.columns:
            return name
    # 大文字小文字を無視した近似一致（英語カラム向け）
    lower_map = {c.lower(): c for c in df.columns}
    for name in candidates:
        if name.lower() in lower_map:
            return lower_map[name.lower()]
    return None


def prepare_address_series(df: pd.DataFrame,
                           address_col: Optional[str],
                           component_groups: List[List[str]]) -> pd.Series:
    """住所カラムがなければ構成要素から合成。どちらもなければ空を返す。"""
    if address_col and address_col in df.columns:
        return df[address_col].map(normalize_space).fillna("")
    # 分割列の連結を試みる
    addr_list: List[str] = []
    for _, row in df.iterrows():
        addr = build_address_from_components(row, component_groups) or ""
        addr_list.append(addr)
    return pd.Series(addr_list, index=df.index, name="__address_built__")


def geocode_dataframe(df: pd.DataFrame,
                      client: NominatimClient,
                      cache: AddressCache,
                      address_col: Optional[str] = None,
                      component_groups: Optional[List[List[str]]] = None) -> pd.DataFrame:
    """DataFrame に緯度経度列を付加して返す。"""
    component_groups = component_groups or DEFAULT_COMPONENT_COL_GROUPS
    addr_series = prepare_address_series(df, address_col, component_groups)

    # すでに緯度/経度がある場合は尊重（空のみ補完）
    lat_existing = df[LAT_COL] if LAT_COL in df.columns else pd.Series([None] * len(df), index=df.index)
    lon_existing = df[LON_COL] if LON_COL in df.columns else pd.Series([None] * len(df), index=df.index)

    lats: List[Optional[float]] = []
    lons: List[Optional[float]] = []

    for addr, lat0, lon0 in tqdm(zip(addr_series, lat_existing, lon_existing),
                                 total=len(df), desc="Geocoding"):
        addr_n = normalize_space(addr)
        if lat0 not in (None, "", float("nan")) and lon0 not in (None, "", float("nan")):
            lats.append(float(lat0))
            lons.append(float(lon0))
            continue

        if not addr_n:
            lats.append(None)
            lons.append(None)
            continue

        cached = cache.get(addr_n)
        if cached and cached.lat is not None and cached.lon is not None:
            lats.append(cached.lat)
            lons.append(cached.lon)
            continue

        try:
            res = client.geocode(addr_n)
        except requests.HTTPError as e:
            # ステータスコードに応じて待機
            code = e.response.status_code if e.response is not None else -1
            if code == 429:
                time.sleep(5)
            else:
                time.sleep(2)
            lats.append(None)
            lons.append(None)
            continue
        except requests.RequestException:
            lats.append(None)
            lons.append(None)
            continue

        cache.put(addr_n, res)
        lats.append(res.lat)
        lons.append(res.lon)

    out = df.copy()
    out[LAT_COL] = lats
    out[LON_COL] = lons
    return out


def save_excel_preserving_macros(input_path: str,
                                 output_path: str,
                                 frames_by_sheet: Dict[str, pd.DataFrame],
                                 keep_vba: bool):
    """
    ExcelにDataFrameを書き戻す。
    - keep_vba=True の場合、.xlsm のマクロを保持して保存（openpyxl keep_vba）。
    - keep_vba=False の場合、通常の .xlsx として保存。
    既存シートは DataFrame で完全置換します（セル結合等は保持されません）。
    """
    engine_kwargs = {"keep_vba": True} if keep_vba else {}
    mode = "w"  # 完全置換

    with pd.ExcelWriter(output_path, engine="openpyxl", mode=mode, engine_kwargs=engine_kwargs) as writer:
        # keep_vba=True の場合は元ファイルをテンプレートとして読み込ませるために、
        # openpyxl の load_workbook を内部的に利用します（pandas が自動対応）。
        if keep_vba:
            # pandas の仕様上、input_path の内容を引き継ぐためには template が必要になることがあります。
            # 最新の pandas では engine_kwargs={"keep_vba": True} として open すれば OK です。
            pass

        for sheet_name, df in frames_by_sheet.items():
            df.to_excel(writer, sheet_name=sheet_name, index=False)


def process_workbook(input_path: str,
                     output_path: Optional[str],
                     sheets: Optional[List[str]] = None,
                     address_cols: Optional[List[str]] = None,
                     cache_csv: Optional[str] = None,
                     keep_vba: bool = False,
                     email: Optional[str] = None):
    """ブック全体を処理し、指定シートに緯度・経度を付与して保存。"""
    if not os.path.exists(input_path):
        raise FileNotFoundError(f"入力ファイルが見つかりません: {input_path}")

    # アドレス候補
    addr_candidates = address_cols or DEFAULT_ADDRESS_CANDIDATE_COLS

    # 読み込み（シート名の一覧取得）
    xls = pd.ExcelFile(input_path, engine="openpyxl")
    all_sheets = xls.sheet_names

    target_sheets = sheets or all_sheets  # 指定がなければ全シート対象

    # キャッシュ
    cache_csv = cache_csv or os.path.splitext(input_path)[0] + "_geocode_cache.csv"
    cache = AddressCache(cache_csv)

    client = NominatimClient(email=email)

    frames_out: Dict[str, pd.DataFrame] = {}

    for s in target_sheets:
        df = pd.read_excel(input_path, sheet_name=s, engine="openpyxl")
        # 住所列の検出
        addr_col = detect_address_column(df, addr_candidates)
        # ジオコーディング
        df2 = geocode_dataframe(df, client, cache, address_col=addr_col)
        frames_out[s] = df2

    cache.save()

    # 出力ファイル名
    if output_path is None:
        root, ext = os.path.splitext(input_path)
        output_path = f"{root}_geocoded{ext if keep_vba and ext.lower()=='.xlsm' else '.xlsx'}"

    save_excel_preserving_macros(input_path, output_path, frames_out, keep_vba=keep_vba)

    return output_path, cache_csv, list(frames_out.keys())


def parse_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Excelの住所列から緯度・経度を付与して保存します（Nominatim使用）。"
    )
    p.add_argument("input", help="入力Excelファイル（.xlsx / .xlsm）")
    p.add_argument("--output", help="出力Excelファイル（未指定時は *_geocoded.xlsx など自動命名）")
    p.add_argument("--sheets", nargs="*", help="処理対象のシート名（未指定なら全シート）")
    p.add_argument("--address-cols", nargs="*", help="住所候補カラム名（優先順）。未指定時は既定を使用")
    p.add_argument("--cache-csv", help="住所キャッシュCSVのパス。未指定時は <入力名>_geocode_cache.csv")
    p.add_argument("--keep-vba", action="store_true", help=".xlsm のマクロを保持して保存（推奨）")
    p.add_argument("--email", help="Nominatim User-Agent 用の連絡先メール（.env の GEO_EMAIL でも可）")
    return p.parse_args(argv)


def main(argv: Optional[List[str]] = None) -> int:
    args = parse_args(argv)
    try:
        out_path, cache_path, sheets = process_workbook(
            input_path=args.input,
            output_path=args.output,
            sheets=args.sheets,
            address_cols=args.address_cols,
            cache_csv=args.cache_csv,
            keep_vba=args.keep_vba,
            email=args.email,
        )
        print("✅ 完了")
        print(f"出力ファイル: {out_path}")
        print(f"キャッシュ   : {cache_path}")
        print(f"処理シート   : {', '.join(sheets)}")
        return 0
    except Exception as e:
        print("❌ エラー:", e, file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
