```mermaid
erDiagram
  GMAPM ||--o{ CATEGORY_LINK : "1対多"
  OPAM ||--o{ CATEGORY_LINK : "1対多"
  PET_MASTER }o..o{ TRAVEL_SPOT : "任意の関連 (カテゴリコード)"
  PET_MASTER }o--o{ CATEGORY_LINK : "多対多 (カテゴリコード)"
  CATEGORY_LINK {
    VARCHAR CATEGORY_ID PK "カテゴリ連携ID"
    ENUM pet_type "ペット区分"
    CHAR(1) category_code "カテゴリコード"
    VARCHAR OPAM_ID FK "アドバイスID"
    VARCHAR GMAPM_ID FK "施設ID"
  }
  GMAPM {
    VARCHAR GMAPM_ID PK "施設ID"
    VARCHAR name "施設名"
    VARCHAR prefecture "都道府県"
    VARCHAR region "地域"
    VARCHAR genre "ジャンル"
    VARCHAR postal_code "郵便番号"
    VARCHAR address "住所"
    VARCHAR phone "電話番号"
    VARCHAR opening_time "営業開始"
    VARCHAR closing_time "営業終了"
    DECIMAL latitude "緯度"
    DECIMAL longitude "経度"
    DECIMAL google_rating "口コミ点数"
    INT google_review_count "口コミ件数"
    BOOLEAN pet_allowed "ペット可"
    TEXT description "概要"
  }
  OPAM {
    VARCHAR OPAM_ID PK "記事ID"
    ENUM pet_type "ペット区分"
    CHAR(1) category_code "カテゴリコード"
    VARCHAR title "タイトル"
    TEXT body "本文"
    VARCHAR url "URL"
  }
  PET_MASTER {
    VARCHAR PETM_ID PK "犬種ID"
    ENUM pet_type "ペット区分"
    VARCHAR breed_name "犬種名"
    CHAR(1) category_code "カテゴリコード"
    INT population "飼育数"
    DECIMAL population_rate "飼育率"
    CHAR(1) old_category "旧カテゴリ"
  }
  TRAVEL_SPOT {
    VARCHAR TSM_ID PK "スポットID"
    INT branch_no PK "枝番"
    VARCHAR prefecture "都道府県"
    VARCHAR region "地方"
    VARCHAR spot_area "地点"
    VARCHAR genre "ジャンル"
    VARCHAR name "施設名"
    VARCHAR phone "電話番号"
    VARCHAR address "住所"
    VARCHAR opening_time "営業開始"
    VARCHAR closing_time "営業終了"
    VARCHAR url "URL"
    DECIMAL latitude "緯度"
    DECIMAL longitude "経度"
    DECIMAL google_rating "口コミ点数"
    INT google_review_count "口コミ件数"
    BOOLEAN english_support "英語対応"
    CHAR(1) category_code "カテゴリコード"
  }
  ```