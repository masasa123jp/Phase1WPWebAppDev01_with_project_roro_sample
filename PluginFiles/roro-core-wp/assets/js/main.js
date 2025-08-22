(function(){
  if (!window.RORO) window.RORO = {};
  const CFG = window.RORO_BOOT || {};
  const REST = CFG.restUrl || (window.wpApiSettings && window.wpApiSettings.root + 'roro/v1/');

  function headers(extra) {
    const h = {'Content-Type':'application/json'};
    if (CFG.nonce) h['X-WP-Nonce'] = CFG.nonce;
    return Object.assign(h, extra||{});
  }

  async function get(path, params) {
    const url = new URL(REST + path, location.origin);
    if (params) Object.entries(params).forEach(([k,v])=> (v!==undefined && v!==null && v!=='') && url.searchParams.append(k,v));
    const res = await fetch(url.toString(), {credentials:'same-origin'});
    if (!res.ok) throw new Error(await res.text());
    return res.json();
  }

  async function post(path, body) {
    const res = await fetch(REST + path, {
      method:'POST',
      headers: headers(),
      credentials:'same-origin',
      body: JSON.stringify(body||{})
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
  }

  function el(tag, attrs={}, children=[]) {
    const e = document.createElement(tag);
    Object.entries(attrs).forEach(([k,v])=>{
      if (k==='class') e.className = v;
      else if (k==='html') e.innerHTML = v;
      else e.setAttribute(k,v);
    });
    (Array.isArray(children)?children:[children]).forEach(c=>{
      if (c==null) return;
      if (typeof c==='string') e.appendChild(document.createTextNode(c));
      else e.appendChild(c);
    });
    return e;
  }

  function toast(msg) {
    alert(msg);
  }

  window.RORO.api = {get, post};
  window.RORO.ui = {el, toast};
  window.RORO.cfg = CFG;
})();
