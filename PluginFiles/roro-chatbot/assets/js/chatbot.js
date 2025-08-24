(function(){
  // 互換ローダ。既存の最小プレースホルダから置換し、必要なら roro-chatbot.js を動的読込。
  document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('roro-chatbot');
    if (!el) return;

    // すでに本体が読み込まれていれば何もしない
    if (window.RORO_CHATBOT_LOADED) return;

    // 明示的にスクリプトURLが指定されていればそれを使う
    var scriptUrl = (window.RORO_CHATBOT_BOOT && window.RORO_CHATBOT_BOOT.scriptUrl) || null;
    // 未指定ならデフォルト推定（プラグイン配置を想定）
    if (!scriptUrl) {
      // 直近の script タグの data-roro-chatbot-src 属性があれば採用
      var scripts = document.getElementsByTagName('script');
      for (var i = scripts.length - 1; i >= 0; i--) {
        var s = scripts[i];
        var data = s.getAttribute && s.getAttribute('data-roro-chatbot-src');
        if (data) { scriptUrl = data; break; }
      }
    }

    if (!scriptUrl) {
      // 何も指定がない場合はコンソール警告のみに留める（静かに失敗）
      console.warn('[roro-chatbot] scriptUrl が未設定のため本体をロードしません。');
      return;
    }

    var tag = document.createElement('script');
    tag.src = scriptUrl;
    tag.async = true;
    tag.onload = function(){ window.RORO_CHATBOT_LOADED = true; };
    document.head.appendChild(tag);
  });
})();
