// roro-dify-switch.js – Dify (公式)とカスタムUIを切り替えるロジック
import { mountDifyIframe, mountDifyScript } from './roro-dify-embed.js';
import { mountCustomChat } from './roro-custom-chat-ui.js';

const DEFAULT_USE_DIFY = true;
const EMBED_METHOD = 'iframe';
const DIFY_APP_URL = 'https://udify.app/chat/XDfUxgca6nAZWhdv';
const DIFY_BASE_URL= 'https://udify.app';
const EMBED_TOKEN  = ''; // script方式のとき

const SYSTEM_VARIABLES = { user_id:'123' };
const USER_VARIABLES   = { user_name:'ユーザー名' };
const LOCAL_CHAT_API   = '/api/chat';

function getEmbedFlagFromQuery() {
  const v = new URL(location.href).searchParams.get('embed');
  if (v == null) return null;
  return /^(1|true|yes|on)$/i.test(v);
}

document.addEventListener('DOMContentLoaded', async () => {
  const override = getEmbedFlagFromQuery();
  const USE_DIFY = (override === null) ? DEFAULT_USE_DIFY : override;

  const embedArea  = document.getElementById('embed-area');
  const embedHost  = document.getElementById('embed-host');
  const customArea = document.getElementById('custom-area');
  const customHost = document.getElementById('custom-host');

  embedArea.style.display  = USE_DIFY ? 'block' : 'none';
  customArea.style.display = USE_DIFY ? 'none'  : 'block';

  if (USE_DIFY) {
    if (EMBED_METHOD === 'script' && EMBED_TOKEN) {
      await mountDifyScript({
        baseUrl:DIFY_BASE_URL,
        token:EMBED_TOKEN,
        systemVariables:SYSTEM_VARIABLES,
        userVariables:USER_VARIABLES,
      });
    } else {
      mountDifyIframe({ container: embedHost, appUrl: DIFY_APP_URL, height:'100%' });
    }
  } else {
    mountCustomChat({ container: customHost, apiUrl: LOCAL_CHAT_API });
  }
});
