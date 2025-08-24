// RORO Chatbot front script (enhanced)
// - フロントUIの送受信と描画、WP AJAX/REST 送信、送信多重防止、簡易履歴
(function(){
  "use strict";

  var boot = window.RORO_CHATBOT_BOOT || {};
  var id   = boot.id || 'roro-chatbot';
  var root = document.getElementById(id);
  if (!root) return;

  var messages = boot.messages || {
    bot: 'Bot',
    you: 'You',
    loading: '...',
    error: 'Failed.'
  };

  var body   = root.querySelector('.roro-chatbot-body');
  var input  = root.querySelector('.roro-chatbot-text');
  var sendBtn= root.querySelector('.roro-chatbot-send');

  var sending = false;
  var storeKey = 'roro.chat.history';

  function saveHistory(line){
    try{
      var arr = JSON.parse(localStorage.getItem(storeKey) || '[]');
      arr.push({t: Date.now(), who: line.who, text: line.text});
      if (arr.length > 100) arr = arr.slice(-100);
      localStorage.setItem(storeKey, JSON.stringify(arr));
    }catch(e){}
  }

  function loadHistory(){
    try{
      var arr = JSON.parse(localStorage.getItem(storeKey) || '[]');
      arr.forEach(function(l){ addMessage(l.who, l.text, false); });
    }catch(e){}
  }

  function escapeHtml(s){
    var div = document.createElement('div'); div.textContent = s; return div.innerHTML;
  }

  function addMessage(who, text, save){
    if (save === undefined) save = true;
    var div = document.createElement('div');
    div.className = 'roro-chatbot-msg roro-'+(who === (messages.you||'You') ? 'you':'bot');
    var whoSpan = document.createElement('span');
    whoSpan.className = 'who';
    whoSpan.textContent = who + ': ';
    var msgSpan = document.createElement('span');
    msgSpan.className = 'text';
    msgSpan.innerHTML = escapeHtml(String(text));
    div.appendChild(whoSpan);
    div.appendChild(msgSpan);
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    if (save) saveHistory({who: who, text: String(text)});
  }

  function setSending(state){
    sending = !!state;
    if (sendBtn) {
      sendBtn.disabled = sending;
      sendBtn.setAttribute('aria-busy', sending ? 'true' : 'false');
    }
  }

  function postAjax(text){
    var fd = new FormData();
    fd.append('action', 'roro_chatbot_send');
    fd.append('nonce', boot.nonce || '');
    fd.append('text', text);
    return fetch(boot.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(function(r){ return r.json(); });
  }

  function postRest(text){
    return fetch(boot.restUrl, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({nonce: boot.nonce || '', text: text})
    }).then(function(r){ return r.json(); });
  }

  function send(){
    if (sending) return;
    var text = (input && input.value || '').trim();
    if (!text) return;
    addMessage(messages.you || 'You', text);
    input.value = '';

    // ローディング
    var loader = messages.loading || '...';
    addMessage(messages.bot || 'Bot', loader, false);
    setSending(true);

    var sender = (boot.restUrl ? postRest : postAjax);
    sender(text)
    .then(function(json){
      // 直前のローディングメッセージを実質置換の意
      if (json && json.success && json.data && json.data.reply) {
        addMessage(messages.bot || 'Bot', json.data.reply);
      } else if (json && json.reply) {
        addMessage(messages.bot || 'Bot', json.reply);
      } else {
        addMessage(messages.bot || 'Bot', messages.error || 'Failed.');
      }
    })
    .catch(function(err){
      console.error('Chat error:', err);
      addMessage(messages.bot || 'Bot', messages.error || 'Failed.');
    })
    .finally(function(){ setSending(false); });
  }

  if (sendBtn) sendBtn.addEventListener('click', send);
  if (input) {
    input.addEventListener('keydown', function(e){
      // Shift+Enterで改行、Enterで送信
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    });
  }

  // 初期履歴描画
  loadHistory();
})();
