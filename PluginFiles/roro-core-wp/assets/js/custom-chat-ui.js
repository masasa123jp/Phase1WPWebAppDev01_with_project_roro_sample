// roro-custom-chat-ui.js – 独自のDify風チャットUI
export function mountCustomChat({ container, apiUrl='/api/chat' } = {}) {
  if (!container) throw new Error('container が未指定です');
  container.innerHTML = `
    <style>
      .cc-wrap{display:flex;flex-direction:column;height:60vh;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
      .cc-title{padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#fff;font-weight:700;color:#1F497D}
      .cc-msgs{flex:1;overflow:auto;padding:12px;background:#f9fafb;display:flex;flex-direction:column;gap:8px}
      .cc-msg{max-width:80%;padding:8px 12px;border-radius:14px;line-height:1.5}
      .cc-msg.user{align-self:flex-end;background:#FFC72C;color:#1F497D}
      .cc-msg.bot{align-self:flex-start;background:#1F497D;color:#fff}
      .cc-form{display:flex;gap:8px;border-top:1px solid #e5e7eb;padding:8px;background:#fff}
      .cc-form textarea{flex:1;resize:none;border:1px solid #ccc;border-radius:8px;padding:8px;min-height:40px;max-height:120px;font:inherit}
      .cc-form button{border:1px solid transparent;background:#1F497D;color:#fff;border-radius:8px;padding:8px 12px;cursor:pointer}
      .cc-form button[disabled]{background:#9ca3af}
      .cc-stop{background:#dc2626}
    </style>
    <div class="cc-wrap">
      <div class="cc-title">新しいチャット</div>
      <div class="cc-msgs" id="cc-msgs"></div>
      <form class="cc-form" id="cc-form">
        <textarea id="cc-input" placeholder="メッセージを入力..." rows="1"></textarea>
        <button id="cc-send" type="submit" title="送信">送信</button>
        <button id="cc-stop" type="button" class="cc-stop" hidden>停止</button>
      </form>
    </div>
  `;

  const $ = sel => container.querySelector(sel);
  const msgs = $('#cc-msgs');
  const form = $('#cc-form');
  const input= $('#cc-input');
  const send = $('#cc-send');
  const stop = $('#cc-stop');
  let aborter = null;

  function append(role, content) {
    const el = document.createElement('div');
    el.className = `cc-msg ${role}`;
    el.textContent = content;
    msgs.appendChild(el);
    msgs.scrollTop = msgs.scrollHeight;
    return el;
  }
  function setAssistant(content) {
    let last = msgs.lastElementChild;
    if (!last || !last.classList.contains('bot')) last = append('bot','');
    last.textContent = content;
    msgs.scrollTop = msgs.scrollHeight;
    return last;
  }

  async function sendMsg(text) {
    append('user', text);
    input.value = '';
    input.style.height = 'auto';
    send.disabled = true; stop.hidden = false;

    let collected = '';
    aborter = new AbortController();

    try {
      const res = await fetch(apiUrl, {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ query:text, conversationId:undefined, userId:'test-user' }),
        signal: aborter.signal
      });
      if (!res.ok || !res.body) throw new Error('API not reachable');

      const reader = res.body.getReader();
      const decoder= new TextDecoder();
      let buf='';
      while(true){
        const { value, done } = await reader.read();
        if (done) break;
        buf += decoder.decode(value, { stream:true });
        const lines = buf.split('\n'); buf = lines.pop();
        for (const line of lines) {
          if (!line.startsWith('data:')) continue;
          const json = line.replace(/^data:\s*/, '');
          if (json === '[DONE]') continue;
          try {
            const payload = JSON.parse(json);
            if (payload.answer) {
              collected += payload.answer;
              setAssistant(collected);
            }
          } catch {}
        }
      }
    } catch {
      const demo = 'これはデモ応答です。/api/chat を用意するとストリーミングで表示されます。';
      for (const ch of demo) {
        if (aborter?.signal.aborted) break;
        collected += ch; setAssistant(collected);
        await new Promise(r => setTimeout(r, 25));
      }
    } finally {
      send.disabled = false; stop.hidden = true;
    }
  }

  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120)+'px';
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (text) sendMsg(text);
  });

  stop.addEventListener('click', () => {
    if (aborter) aborter.abort();
    send.disabled = false; stop.hidden = true;
  });
}
