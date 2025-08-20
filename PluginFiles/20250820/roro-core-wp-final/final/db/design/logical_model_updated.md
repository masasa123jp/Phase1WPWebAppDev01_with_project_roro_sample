# 論理データモデル (顧客情報テーブルを除外)

このドキュメントでは、提供されたマスタデータファイルの各シートを整理し、顧客情報テーブルを除外した論理データモデルを示します。データ辞書とER図を合わせて参照することで、データベース設計の基盤となる論理構造が理解できます。

## GMAPM
施設マスタ (Google Map master data)

|列名|データ型|PK|説明|
|---|---|---|---|
|GMAPM_ID|VARCHAR|Y|施設ID（一意の識別子）|
|name|VARCHAR|N|施設名（店舗名）|
|prefecture|VARCHAR|N|都道府県|
|region|VARCHAR|N|地域・区|
|genre|VARCHAR|N|ジャンル|
|postal_code|VARCHAR|N|郵便番号|
|address|VARCHAR|N|住所|
|phone|VARCHAR|N|電話番号|
|opening_time|VARCHAR|N|営業開始時間|
|closing_time|VARCHAR|N|営業終了時間|
|latitude|DECIMAL|N|緯度|
|longitude|DECIMAL|N|経度|
|google_rating|DECIMAL|N|Google 口コミ評価|
|google_review_count|INT|N|Google 口コミ件数|
|pet_allowed|BOOLEAN|N|ペット同伴可フラグ|
|description|TEXT|N|施設の概要・補足情報|

## OPAM
ワンポイントアドバイスマスタ (One point advice master)

|列名|データ型|PK|説明|
|---|---|---|---|
|OPAM_ID|VARCHAR|Y|記事ID（一意の識別子）|
|pet_type|ENUM|N|ペット区分（DOGまたはCAT）|
|category_code|CHAR(1)|N|カテゴリコード|
|title|VARCHAR|N|タイトル|
|body|TEXT|N|本文内容|
|url|VARCHAR|N|外部リンクURL|

## PET_MASTER
ペットマスタ（犬種マスタ）

|列名|データ型|PK|説明|
|---|---|---|---|
|PETM_ID|VARCHAR|Y|犬種ID（一意の識別子）|
|pet_type|ENUM|N|ペット区分（DOGまたはCAT）|
|breed_name|VARCHAR|N|犬種名|
|category_code|CHAR(1)|N|カテゴリコード|
|population|INT|N|飼育数|
|population_rate|DECIMAL|N|飼育率（飼育数の割合）|
|old_category|CHAR(1)|N|旧カテゴリ|

## CATEGORY_LINK
カテゴリ連携テーブル（犬A～H・猫テーブルの統合）

|列名|データ型|PK|説明|
|---|---|---|---|
|CATEGORY_ID|VARCHAR|Y|カテゴリ連携ID|
|pet_type|ENUM|N|ペット区分（DOGまたはCAT）|
|category_code|CHAR(1)|N|カテゴリコード|
|OPAM_ID|VARCHAR|N|ワンポイントアドバイスID (FK to OPAM.OPAM_ID)|
|GMAPM_ID|VARCHAR|N|施設ID (FK to GMAPM.GMAPM_ID)|

## TRAVEL_SPOT
観光スポットマスタデータ

|列名|データ型|PK|説明|
|---|---|---|---|
|TSM_ID|VARCHAR|Y|観光スポットID|
|branch_no|INT|Y|枝番（複合主キー構成要素）|
|prefecture|VARCHAR|N|都道府県|
|region|VARCHAR|N|地方|
|spot_area|VARCHAR|N|地点（エリア名称）|
|genre|VARCHAR|N|ジャンル|
|name|VARCHAR|N|施設名/スポット名|
|phone|VARCHAR|N|電話番号|
|address|VARCHAR|N|住所|
|opening_time|VARCHAR|N|営業開始時間|
|closing_time|VARCHAR|N|営業終了時間|
|url|VARCHAR|N|外部リンクURL|
|latitude|DECIMAL|N|緯度|
|longitude|DECIMAL|N|経度|
|google_rating|DECIMAL|N|Google 口コミ評価|
|google_review_count|INT|N|Google 口コミ件数|
|english_support|BOOLEAN|N|英語対応可否|
|category_code|CHAR(1)|N|カテゴリコード|

