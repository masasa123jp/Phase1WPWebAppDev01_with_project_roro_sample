// Dify と独自UIを切り替えるスイッチャ
(function(){
  const {cfg} = window.RORO || {cfg:{}};

  function mount(selector) {
    const host = (typeof selector==='string') ? document.querySelector(selector) : selector;
    if (!host) return;
    host.innerHTML = '';

    const bar = document.createElement('div');
    bar.className = 'switcher';
    const btnD = document.createElement('button'); btnD.textContent = 'Dify';
    const btnC = document.createElement('button'); btnC.textContent = 'Custom';
    const pane = document.createElement('div'); pane.className = 'pane';
    bar.appendChild(btnD); bar.appendChild(btnC);
    host.appendChild(bar); host.appendChild(pane);

    function showDify(){ window.RORO_DIFY.mount(pane); }
    function showCustom(){ window.RORO_CHAT.mount(pane); }

    btnD.addEventListener('click', showDify);
    btnC.addEventListener('click', showCustom);

    // 既定は Dify（有効時）、なければ Custom
    if (cfg.settings && cfg.settings.dify_enabled) showDify(); else showCustom();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    const el = document.getElementById('roro-ai-switch');
    if (el) mount(el);
  });

  window.RORO_SWITCH = { mount };
})();
