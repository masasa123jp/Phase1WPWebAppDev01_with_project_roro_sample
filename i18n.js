// i18n.js（修正版）
const i18n = require('i18next');
const Backend = require('i18next-fs-backend');
const middleware = require('i18next-http-middleware');

i18n
  .use(Backend)
  .use(middleware.LanguageDetector)
  .init({
    fallbackLng: 'ja',
    preload: ['ja', 'en', 'zh', 'ko'], // 言語を追加
    backend: {
      loadPath: __dirname + '/locales/{{lng}}/translation.json'
    }
  });

module.exports = {
  i18n,
  i18nMiddleware: middleware.handle(i18n)
};
