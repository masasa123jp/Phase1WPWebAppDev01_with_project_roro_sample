// Dify の埋め込み（iframe or script）を行う最小ラッパ
(function(){
  if (!window.RORO_DIFY) window.RORO_DIFY = {};
  const cfg = (window.RORO && RORO.cfg && RORO.cfg.settings) || {};

  function mount(container) {
    const el = (typeof container === 'string') ? document.querySelector(container) : container;
    if (!el) return;
    el.innerHTML = '';
    if (!cfg.dify_enabled) {
      el.innerHTML = '<div class="muted">Dify は無効化されています（管理画面で設定してください）。</div>';
      return;
    }
    // シンプルに iframe 埋め込み
    const url = (cfg.dify_base_url || '') + '/apps/' + (cfg.dify_app_id || '');
    const ifr = document.createElement('iframe');
    ifr.src = url;
    ifr.width = '100%';
    ifr.height = '560';
    ifr.setAttribute('frameborder','0');
    el.appendChild(ifr);
  }

  window.RORO_DIFY.mount = mount;
})();
