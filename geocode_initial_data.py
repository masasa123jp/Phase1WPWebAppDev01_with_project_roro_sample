#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
geocode_initial_data.py (revised)
---------------------------------
- TRAVEL_SPOT / GMAPM の INSERT 文を検出し、住所カラムをジオコーディングして
  lat / lng を列として追加、または既存 lat / lng が NULL の行のみ補完します。
- 文字列のパースは SQL 準拠の「'' によるエスケープ」を正しく処理する独自トークナイザで実装。
- 列位置は列名から動的に決定（ハードコーディング廃止）。
- Nominatim の利用規約順守（1 req/sec、識別可能な User-Agent）:
    https://operations.osmfoundation.org/policies/nominatim/
- API 仕様（Search）:
    https://nominatim.org/release-docs/latest/api/Search/

使い方:
    python geocode_initial_data.py input.sql output.sql \
        --user-agent "your-app/1.0 (contact: you@example.com)" \
        --email you@example.com \
        --delay 1.0 \
        --countrycodes jp \
        --cache cache.json

※ User-Agent は必須レベル（未指定だと警告し、実行は試みますが 403 になる可能性が高い）。
※ 大量データは公開 Nominatim ではなく、自前インスタンス or 商用APIの利用を推奨。

Author: revised by ChatGPT
Date: 2025-08-12
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import time
from typing import Dict, List, Optional, Sequence, Tuple

try:
    import requests  # 3rd-party
except ImportError as exc:
    raise SystemExit(
        "The 'requests' library is required. Install via: pip install requests"
    ) from exc


# -----------------------------
# Utility: SQL literal handling
# -----------------------------

_NUM_INT_RE = re.compile(r"^[+-]?\d+$")
_NUM_FLOAT_RE = re.compile(r"^[+-]?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$|^[+-]?\d+[eE][+-]?\d+$")

def sql_serialize(value) -> str:
    r"""Convert a Python value into a SQL literal.

    Strings are quoted with single quotes and internal single quotes are
    doubled.  Backslashes are doubled as well.  None maps to
    ``NULL``.  Newlines are preserved.  Numbers are returned as
    strings.  Any other type is coerced to a string and quoted.

    Parameters
    ----------
    value
        The Python value to convert.

    Returns
    -------
    str
        A SQL literal representing ``value``.
    """
    if value is None:
        return 'NULL'
    if isinstance(value, (int, float)):
        return str(value)
    # Ensure text is a str
    text = str(value)
    # Escape backslashes first, then single quotes
    text = text.replace('\\', '\\\\').replace("'", "''")
    return f"'{text}'"


# ---------------------------------------
# Parser: one VALUES row -> list of items
# ---------------------------------------

def _parse_values_row(row: str) -> List[object]:
    """1行の VALUES タプル "(...)" をSQL仕様に沿って分解して Python 値に変換。

    - 前後の括弧は必須。
    - 文字列は '...'。内部の '' は 1つの ' として扱う。
    - NULL は None。数値は int/float に変換。それ以外は文字列。
    """
    row = row.strip()
    if not (row.startswith("(") and row.endswith(")")):
        raise ValueError(f"Row does not start with '(' and end with ')': {row[:80]}...")
    i = 1
    n = len(row) - 1  # 最後の ')'
    buf: List[str] = []
    vals: List[object] = []

    def flush():
        token = "".join(buf).strip()
        buf.clear()
        if token == "" or token.upper() == "NULL":
            vals.append(None)
            return
        # 文字列はこの関数の中ではクォート除去済みで渡す想定
        if token.startswith("__STR__:"):
            text = token[len("__STR__:") :]
            vals.append(text)
            return
        # 数値判定
        if _NUM_INT_RE.match(token):
            try:
                vals.append(int(token))
                return
            except Exception:
                pass
        if _NUM_FLOAT_RE.match(token):
            try:
                vals.append(float(token))
                return
            except Exception:
                pass
        # それ以外は文字列として扱う
        vals.append(token)

    in_str = False
    # 文字列の内容は __STR__ プレフィックスを付けて一時的にバッファへ（外側の値と区別するため）
    str_buf: List[str] = []
    while i < n:
        c = row[i]

        if not in_str:
            if c == "'":
                # 文字列開始
                in_str = True
                str_buf.clear()
                i += 1
                continue
            elif c == ",":
                # 値切り出し
                flush()
                i += 1
                continue
            else:
                buf.append(c)
                i += 1
                continue
        else:
            # in_str=True
            if c == "'":
                # 直後も ' ならエスケープ '' → 単一の '
                if i + 1 < n and row[i + 1] == "'":
                    str_buf.append("'")
                    i += 2
                    continue
                # 文字列クローズ
                in_str = False
                # 文字列は __STR__ でバッファへ入れておく（スペースやカンマと衝突しないように）
                buf.append("__STR__:" + "".join(str_buf))
                str_buf.clear()
                i += 1
                continue
            else:
                str_buf.append(c)
                i += 1
                continue

    if in_str:
        raise ValueError("Unclosed string literal in row: " + row[:120])
    # 残りをフラッシュ
    flush()
    return vals


# ------------------------------------------------
# Extract each "(...)" tuple from a VALUES section
# ------------------------------------------------

def _extract_rows(values_section: str) -> List[str]:
    """VALUES セクションから個々のタプル "(...)" を取り出す（ネスト考慮）。
    文字列内の '' を正しくスキップし、括弧深度0の ')' で1レコード終端とみなす。"""
    rows: List[str] = []
    i, n = 0, len(values_section)
    while i < n:
        # 先頭の区切り/空白をスキップ
        while i < n and values_section[i] in " \t\r\n,":
            i += 1
        if i >= n:
            break
        if values_section[i] != "(":
            # ごみをスキップ
            i += 1
            continue
        start = i
        depth = 0
        in_str = False
        i += 1
        depth = 1
        while i < n:
            c = values_section[i]
            if not in_str:
                if c == "'":
                    in_str = True
                    i += 1
                    continue
                if c == "(":
                    depth += 1
                    i += 1
                    continue
                if c == ")":
                    depth -= 1
                    i += 1
                    if depth == 0:
                        rows.append(values_section[start:i])
                        break
                    continue
                i += 1
            else:
                # in_str
                if c == "'":
                    # '' エスケープ
                    if i + 1 < n and values_section[i + 1] == "'":
                        i += 2
                        continue
                    in_str = False
                    i += 1
                    continue
                else:
                    i += 1
        else:
            # ループ自然終了＝閉じ括弧なし
            rows.append(values_section[start:])
            break
        # 末尾のカンマは次ループ先頭でスキップ
    return rows


# ----------------------------
# Nominatim geocoding (public)
# ----------------------------

def _geocode(
    address: Optional[str],
    session: requests.Session,
    user_agent: str,
    email: Optional[str],
    countrycodes: Optional[str],
    delay_sec: float,
    cache: Dict[str, Tuple[Optional[float], Optional[float]]],
) -> Tuple[Optional[float], Optional[float]]:
    """住所を Nominatim でジオコーディング。キャッシュあり。"""
    if not address:
        return None, None
    key = f"{address}|{countrycodes or ''}"
    if key in cache:
        return cache[key]
    params = {
        "q": address,
        "format": "json",
        "limit": 1,
    }
    if countrycodes:
        params["countrycodes"] = countrycodes
    headers = {
        "User-Agent": user_agent,
        "Accept-Language": "ja",
    }
    if email:
        # ドキュメント上 optional。問い合わせ先として使われることがある
        params["email"] = email

    try:
        resp = session.get(
            "https://nominatim.openstreetmap.org/search",
            params=params,
            headers=headers,
            timeout=15,
        )
        resp.raise_for_status()
        data = resp.json()
        if isinstance(data, list) and data:
            lat = float(data[0]["lat"])
            lon = float(data[0]["lon"])
        else:
            lat, lon = None, None
    except Exception:
        lat, lon = None, None

    cache[key] = (lat, lon)
    # レート制限遵守（最低1秒）— 公開Nominatimの利用規約
    time.sleep(max(0.0, delay_sec))
    return lat, lon


# --------------------------
# INSERT の書き換えロジック
# --------------------------

def _rewrite_insert(
    table_name: str,
    insert_stmt_no_semicolon: str,
    geocode_args: dict,
    cache: Dict[str, Tuple[Optional[float], Optional[float]]],
) -> str:
    """INSERT 文1本（末尾のセミコロンなし）を lat/lng 付きに書き換える。"""
    # 列リスト抽出
    m = re.search(r"\(\s*([^)]+?)\s*\)\s*VALUES", insert_stmt_no_semicolon, flags=re.IGNORECASE | re.DOTALL)
    if not m:
        return insert_stmt_no_semicolon  # 形式外はそのまま
    columns_text = m.group(1)
    # 列名をトークン化（バッククォート/ダブルクォートを除去し、正規化）
    col_raw = [c.strip() for c in columns_text.split(",")]
    cols = [re.sub(r'^[`"]?|[`"]?$', "", c).strip() for c in col_raw]
    cols_lc = [c.lower() for c in cols]

    # 住所カラム
    addr_col = "address"
    if addr_col not in cols_lc:
        # 想定外スキーマは何もしない
        return insert_stmt_no_semicolon

    # lat/lng 位置（既存 or 追加）
    has_latlng = ("lat" in cols_lc) and ("lng" in cols_lc)
    if table_name.upper() == "TRAVEL_SPOT":
        # TRAVEL_SPOT は url の直後に lat/lng を入れる（なければ address の直後）
        pivot_name = "url"
    else:
        # GMAPM は homepage の直後
        pivot_name = "homepage"

    if has_latlng:
        insert_pos = None  # 変更しない
        out_cols = cols[:]  # 既存カラムを維持
        lat_idx, lng_idx = cols_lc.index("lat"), cols_lc.index("lng")
    else:
        if pivot_name in cols_lc:
            insert_pos = cols_lc.index(pivot_name) + 1
        else:
            insert_pos = cols_lc.index(addr_col) + 1
        out_cols = cols[:insert_pos] + ["lat", "lng"] + cols[insert_pos:]
        lat_idx = out_cols.index("lat")
        lng_idx = out_cols.index("lng")

    # VALUES セクション抽出
    values_section = insert_stmt_no_semicolon[m.end():]
    rows = _extract_rows(values_section)

    # 書き換え
    session = geocode_args["session"]
    ua = geocode_args["user_agent"]
    email = geocode_args.get("email")
    cc = geocode_args.get("countrycodes")
    delay = geocode_args.get("delay", 1.0)

    out_rows: List[str] = []
    for r in rows:
        vals = _parse_values_row(r)  # list
        # 既存 lat/lng がない場合は挿入
        if not has_latlng:
            # スロット追加
            if insert_pos is None:
                insert_pos = len(vals)  # 念のため
            vals = vals[:insert_pos] + [None, None] + vals[insert_pos:]
        # 住所を拾う
        addr_idx = cols_lc.index(addr_col)
        address_value = vals[addr_idx]
        if isinstance(address_value, str):
            address = address_value.strip()
        else:
            address = None

        # lat/lng を埋める（既に値がある場合は NULL のみ上書き）
        need_lat = (vals[lat_idx] is None or vals[lat_idx] == "")
        need_lng = (vals[lng_idx] is None or vals[lng_idx] == "")
        if need_lat or need_lng:
            lat, lng = _geocode(address, session, ua, email, cc, delay, cache)
            if need_lat:
                vals[lat_idx] = lat
            if need_lng:
                vals[lng_idx] = lng

        # 直列化
        serialized = "(" + ", ".join(sql_serialize(v) for v in vals) + ")"
        out_rows.append(serialized)

    # カラム部＋VALUES 再構築
    new_col_section = "(" + ", ".join(out_cols) + ")"
    prefix = insert_stmt_no_semicolon[: m.start()] + new_col_section + " VALUES"
    return prefix + "\n  " + ",\n  ".join(out_rows)


# -------------------------
# ファイル全体の処理フロー
# -------------------------

_INSERT_HEAD_RE = re.compile(r"INSERT\s+INTO\s+(TRAVEL_SPOT|GMAPM)\s*\(", flags=re.IGNORECASE)

def _process_file(
    in_path: str,
    out_path: str,
    user_agent: str,
    email: Optional[str],
    countrycodes: Optional[str],
    delay: float,
    cache_path: Optional[str],
) -> None:
    with open(in_path, "r", encoding="utf-8") as f:
        sql = f.read()

    # キャッシュ
    cache: Dict[str, Tuple[Optional[float], Optional[float]]] = {}
    if cache_path and os.path.exists(cache_path):
        try:
            with open(cache_path, "r", encoding="utf-8") as cf:
                cache = json.load(cf)
        except Exception:
            cache = {}

    session = requests.Session()
    parts: List[str] = []
    pos = 0

    for m in _INSERT_HEAD_RE.finditer(sql):
        tbl = m.group(1).upper()
        start = m.start()
        # 先頭〜INSERT前
        parts.append(sql[pos:start])

        # この INSERT の末尾 ';' を探す（カッコ/クォートを考慮）
        i = m.end()
        depth = 1  # '(' を1として開始
        in_str = False
        while i < len(sql):
            c = sql[i]
            if not in_str:
                if c == "'":
                    in_str = True
                    i += 1
                    continue
                if c == "(":
                    depth += 1
                    i += 1
                    continue
                if c == ")":
                    depth -= 1
                    i += 1
                    continue
                if c == ";" and depth == 0:
                    end = i
                    break
                i += 1
            else:
                if c == "'":
                    # '' エスケープ
                    if i + 1 < len(sql) and sql[i + 1] == "'":
                        i += 2
                        continue
                    in_str = False
                    i += 1
                    continue
                else:
                    i += 1
        else:
            # セミコロンが見つからない場合は残り全部
            end = len(sql)

        insert_stmt = sql[start:end]
        rewritten = _rewrite_insert(
            tbl,
            insert_stmt,
            geocode_args={
                "session": session,
                "user_agent": user_agent,
                "email": email,
                "countrycodes": countrycodes,
                "delay": delay,
            },
            cache=cache,
        )
        parts.append(rewritten + ";")
        pos = end + 1

    parts.append(sql[pos:])
    out_text = "".join(parts)

    with open(out_path, "w", encoding="utf-8") as f:
        f.write(out_text)

    # キャッシュ保存
    if cache_path:
        try:
            with open(cache_path, "w", encoding="utf-8") as cf:
                json.dump(cache, cf, ensure_ascii=False, indent=2)
        except Exception:
            pass


# -----------
# エントリ点
# -----------

def main(argv: Optional[Sequence[str]] = None) -> int:
    ap = argparse.ArgumentParser(
        description="Add or fill lat/lng in INSERTs for TRAVEL_SPOT/GMAPM by geocoding addresses (Nominatim)."
    )
    ap.add_argument("input_sql", help="Path to input SQL file (e.g. initial_data.sql)")
    ap.add_argument("output_sql", help="Path to output SQL file with lat/lng added or filled")
    ap.add_argument("--user-agent", default=os.getenv("NOMINATIM_USER_AGENT"),
                    help="HTTP User-Agent with contact info (required by Nominatim policy). "
                         "Example: 'roro-geocode/1.0 (contact: you@example.com)'")
    ap.add_argument("--email", default=os.getenv("NOMINATIM_EMAIL"),
                    help="Contact email (optional, may help with support)")
    ap.add_argument("--delay", type=float, default=float(os.getenv("GEOCODE_DELAY", "1.0")),
                    help="Sleep seconds between requests (>=1.0 for public Nominatim)")
    ap.add_argument("--countrycodes", default=os.getenv("GEOCODE_COUNTRYCODES", "jp"),
                    help="ISO country codes (comma-separated), e.g. 'jp' or 'jp,us'")
    ap.add_argument("--cache", default=os.getenv("GEOCODE_CACHE"),
                    help="Path to JSON cache file (address → lat/lng)")

    args = ap.parse_args(argv)

    if not args.user_agent:
        # 規約上は必須。続行も可能だが403の恐れが高いので警告。
        print(
            "[WARN] --user-agent is not set. Public Nominatim requires a unique UA with contact info; "
            "your requests may be blocked. See: https://operations.osmfoundation.org/policies/nominatim/",
            file=sys.stderr,
        )

    _process_file(
        args.input_sql,
        args.output_sql,
        user_agent=args.user_agent or "roro-geocode/0.0 (no-contact)",
        email=args.email,
        countrycodes=args.countrycodes,
        delay=max(1.0, args.delay or 1.0),  # 公開Nominatimは最小1秒を強制
        cache_path=args.cache,
    )
    print(f"[OK] Wrote: {args.output_sql}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
