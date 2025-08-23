// roro-dify-embed.js – 公式Dify画面の iframe/script 埋め込み
export function mountDifyIframe({ container, appUrl, height='100%' }) {
  if (!container) throw new Error('container が未指定です');
  container.innerHTML = '';
  const iframe = document.createElement('iframe');
  iframe.src = appUrl;
  iframe.title = 'Dify Chat';
  iframe.style.width  = '100%';
  iframe.style.height = height;
  iframe.style.border = '0';
  iframe.style.borderRadius = 'var(--border-radius)';
  iframe.allow = 'clipboard-read; clipboard-write; microphone; camera; fullscreen';
  container.appendChild(iframe);
}

export function mountDifyScript({ baseUrl='https://udify.app', token, systemVariables={}, userVariables={} }) {
  if (!token) throw new Error('script 方式にはトークンが必要です');
  window.difyChatbotConfig = {
    token,
    baseUrl,
    systemVariables,
    userVariables
  };
  return new Promise((resolve, reject) => {
    document.querySelectorAll('script[data-dify-embed="true"]').forEach(s => s.remove());
    const s = document.createElement('script');
    s.src = `${baseUrl.replace(/\/$/,'')}/embed.min.js`;
    s.async = true;
    s.dataset.difyEmbed = 'true';
    s.onload = () => resolve(true);
    s.onerror = () => reject(new Error('embed.min.js の読み込み失敗'));
    document.head.appendChild(s);
  });
}
