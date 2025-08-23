/*
 * roro-lang.js – 多言語翻訳辞書と言語切替処理
 */
const translations = {
  ja: {
    nav_map:'マップ', nav_ai:'AI', nav_favorites:'お気に入り', nav_magazine:'雑誌', nav_profile:'マイページ',
    profile_title:'マイページ', magazine_title:'月間雑誌', map_title:'おでかけマップ', ai_title:'AIアシスタント', favorites_title:'お気に入り',
    profile_edit:'プロフィール編集', label_name:'お名前', label_furigana:'ふりがな', label_email:'メールアドレス', label_phone:'電話番号', label_address:'住所', label_language:'言語',
    pet_info:'ペット情報', add_pet:'ペットを追加', save:'保存', logout:'ログアウト', no_favorites:'お気に入りがまだありません。', delete:'削除',
    ai_intro:'AIアシスタントにペットの気になることを気軽に質問してみましょう。', reset_view:'周辺表示',
    cat_event:'イベント', cat_restaurant:'レストラン', cat_hotel:'ホテル', cat_activity:'アクティビティ', cat_museum:'美術館・博物館', cat_facility:'施設',
    save_favorite:'お気に入り', save_want:'行ってみたい', save_plan:'旅行プラン', save_star:'スター付き',
    list_favorite:'お気に入り', list_want:'行ってみたい', list_plan:'旅行プラン', list_star:'スター付き',
    view_details:'詳細を見る', saved_msg:'リストに保存しました', already_saved_msg:'既にこのリストに登録済みです',
    mag_issue_june:'2025年6月号', mag_theme_june:'犬と梅雨のおうち時間', mag_desc_june:'雨の日でも犬と楽しく過ごせる特集',
    mag_issue_july:'2025年7月号', mag_theme_july:'犬と夏のおでかけ × UVケア', mag_desc_july:'紫外線対策とワンちゃんとのおでかけスポットをご紹介♪',
    mag_event_title:'今月のイベント', mag_cafe_title:'おすすめカフェ', mag_column_june:'プチコラム：梅雨の運動不足解消法', mag_column_july:'プチコラム：夏のUV対策',
    mag_recommend_title:'ちぃまめのおすすめ', mag_disaster_title:'ちぃまめの防災アドバイス', mag_advice_title:'ワンポイントアドバイス', mag_relax_cafe_title:'ワンちゃんとくつろげるカフェ',
    login_greeting:'こんにちは！', login_email:'メールアドレス', login_password:'パスワード', login_submit:'ログイン',
    login_google:'Googleでログイン', login_line:'LINEでログイン', login_no_account:'アカウントをお持ちでない場合は', login_register_link:'こちらから新規登録'
  },
  // ... 以下、en/zh/ko の辞書を続けて同じ形式で定義 ...
};

// 言語設定の取得・保存
function getUserLang() {
  return localStorage.getItem('userLang') || 'ja';
}
function setUserLang(lang) {
  localStorage.setItem('userLang', lang);
}

// 翻訳適用
function applyTranslations() {
  const lang = getUserLang();
  document.querySelectorAll('[data-i18n-key]').forEach(el => {
    const key = el.getAttribute('data-i18n-key');
    if (translations[lang] && translations[lang][key]) {
      el.textContent = translations[lang][key];
    }
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.getAttribute('data-i18n-placeholder');
    if (translations[lang] && translations[lang][key]) {
      el.setAttribute('placeholder', translations[lang][key]);
    }
  });
  if (typeof updateCategoryLabels === 'function') {
    updateCategoryLabels();
  }
}

// 言語切替 (順回転)
function cycleLang() {
  const order = ['ja','en','zh','ko'];
  const current = getUserLang();
  const idx = order.indexOf(current);
  const next = order[(idx + 1) % order.length];
  setUserLang(next);
  try {
    const userStr = sessionStorage.getItem('user');
    if (userStr) {
      const userObj = JSON.parse(userStr);
      userObj.language = next;
      sessionStorage.setItem('user', JSON.stringify(userObj));
    }
  } catch (e){}
  applyTranslations();
  if (location.pathname.endsWith('/map')) {
    setTimeout(() => { location.reload(); }, 0);
  }
}

// 言語切替ボタン初期化
function initLangToggle() {
  const btn = document.getElementById('lang-toggle-btn');
  if (btn) {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      cycleLang();
    });
  }
}

// カテゴリラベル更新
function updateCategoryLabels() {
  const lang = getUserLang();
  const t = translations[lang] || {};
  document.querySelectorAll('#category-bar .filter-btn span[data-i18n-key]').forEach(span => {
    const key = span.getAttribute('data-i18n-key');
    if (t[key]) {
      span.textContent = t[key];
    }
  });
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
  // ユーザー言語設定を反映
  try {
    const userStr = sessionStorage.getItem('user');
    if (userStr) {
      const userObj = JSON.parse(userStr);
      if (userObj.language) {
        setUserLang(userObj.language);
      }
    }
  } catch (e){}
  const langSelect = document.getElementById('profile-language');
  if (langSelect) {
    langSelect.value = getUserLang();
    langSelect.addEventListener('change', () => {
      const selected = langSelect.value;
      setUserLang(selected);
      try {
        const userStr2 = sessionStorage.getItem('user');
        if (userStr2) {
          const userObj2 = JSON.parse(userStr2);
          userObj2.language = selected;
          sessionStorage.setItem('user', JSON.stringify(userObj2));
        }
      } catch (e){}
      applyTranslations();
    });
  }
  applyTranslations();
  initLangToggle();
});
