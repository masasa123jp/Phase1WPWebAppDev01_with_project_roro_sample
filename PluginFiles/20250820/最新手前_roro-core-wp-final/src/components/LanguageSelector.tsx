import React from 'react';

/**
 * Language selector component.
 *
 * Provides a simple drop‑down control that allows the user to choose
 * between the supported languages (Japanese, English, Chinese and
 * Korean).  The selected language is stored in localStorage so that
 * subsequent page loads can pick up the preference.  Changing the
 * language triggers a full reload because WordPress determines the
 * locale on the server during bootstrap.
 */
const LanguageSelector: React.FC = () => {
  const [lang, setLang] = React.useState<string>(() => {
    return localStorage.getItem('roro_locale') || 'ja';
  });

  const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newLang = e.target.value;
    setLang(newLang);
    localStorage.setItem('roro_locale', newLang);
    // Reload the page so that WordPress picks up the new locale via the
    // determine_locale filter in User_Locale_Manager.
    window.location.reload();
  };

  return (
    <select value={lang} onChange={handleChange} className="border rounded px-2 py-1">
      <option value="ja">日本語</option>
      <option value="en_US">English</option>
      <option value="zh_CN">中文</option>
      <option value="ko">한국어</option>
    </select>
  );
};

export default LanguageSelector;
