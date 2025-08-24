/* Minimal i18n helper for RORO app */
(function () {
  'use strict';
  window.RORO_I18N = {
    t: function (key) {
      try {
        var locale = (window.RORO_CORE_BOOT && window.RORO_CORE_BOOT.locale) || 'ja';
        var dict = (window.RORO_CORE_BOOT && window.RORO_CORE_BOOT.i18n && window.RORO_CORE_BOOT.i18n[locale]) || {};
        return dict[key] || key;
      } catch (e) { return key; }
    },
    applyDom: function (root) {
      var elms = (root || document).querySelectorAll('[data-i18n]');
      elms.forEach(function (el) {
        var k = el.getAttribute('data-i18n');
        el.textContent = RORO_I18N.t(k);
      });
      var phs = (root || document).querySelectorAll('[data-ph]');
      phs.forEach(function (el) {
        var k = el.getAttribute('data-ph');
        el.setAttribute('placeholder', RORO_I18N.t(k));
      });
    }
  };
})();
