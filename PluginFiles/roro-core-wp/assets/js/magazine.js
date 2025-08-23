/*
 * roro-magazine.js – 月刊雑誌閲覧機能
 * 表紙カードをクリックすると、ページめくりビューを表示します。
 */
document.addEventListener('DOMContentLoaded', () => {
  const viewer = document.getElementById('magazine-viewer');
  const book   = viewer ? viewer.querySelector('.book') : null;
  if (!viewer || !book) return;

  const issues = {
    '2025-06': [
      {
        html: `<div style="display:flex;flex-direction:column;height:100%;">
          <img src="${RORO_ENV.assetsUrl}images/magazine_cover1.png" style="width:100%;height:65%;object-fit:cover;border-radius:8px;" alt="cover">
          <div style="padding:0.3rem;text-align:center;">
            <h2 data-i18n-key="mag_issue_june" style="margin:0;color:#1F497D;">2025年6月号</h2>
            <h3 data-i18n-key="mag_theme_june" style="margin:0.2rem 0;color:#e67a8a;font-size:1.3rem;">犬と梅雨のおうち時間</h3>
            <p data-i18n-key="mag_desc_june" style="font-size:1rem;">雨の日でも犬と楽しく過ごせる特集</p>
          </div></div>`
      },
      {
        html: `<h3 style="color:#1F497D;" data-i18n-key="mag_event_title">今月のイベント</h3>
               <ul style="padding-left:1.2rem;"><li>6/12 ヨガ</li><li>6/26 室内ドッグラン</li><li>6/30 レインコートショー</li></ul>`
      },
      { html: `<h3 style="color:#1F497D;" data-i18n-key="mag_cafe_title">おすすめカフェ</h3><p>室内OKのカフェ紹介...</p>` },
      {
        html: `<h3 style="color:#1F497D;" data-i18n-key="mag_column_june">プチコラム：梅雨の運動不足解消法</h3><p>...</p>`
      },
      {
        html: `<h3 style="color:#1F497D;" data-i18n-key="mag_recommend_title">ちぃまめのおすすめ</h3>
               <div style="display:flex;gap:.5rem;">
                 <img src="${RORO_ENV.assetsUrl}images/product_raincoat_boots.png" style="width:40%;max-height:120px;object-fit:contain;border-radius:8px;" alt="">
                 <div><strong>防水レインコート</strong><br>¥3,500</div>
               </div>`
      },
      {
        html: `<h3 style="color:#1F497D;" data-i18n-key="mag_disaster_title">ちぃまめの防災アドバイス</h3>
               <img src="${RORO_ENV.assetsUrl}images/chiamame_disaster.png" style="width:100%;max-height:250px;object-fit:contain;" alt="">`
      },
      {
        html: `<h3 style="color:#1F497D;" data-i18n-key="mag_advice_title">ワンポイントアドバイス</h3>
               <ul><li>ニュースから考える飼い主の責任</li></ul>`
      },
      {
        html: `<div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;">
                 <div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D;">PROJECT RORO 2025年6月号</div>
               </div>`
      }
    ],
    '2025-07': [
      {
        html: `<div style="display:flex;flex-direction:column;height:100%;">
          <img src="${RORO_ENV.assetsUrl}images/magazine_cover2.png" style="width:100%;height:65%;object-fit:cover;border-radius:8px;" alt="cover">
          <div style="padding:0.4rem;text-align:center;">
            <h2 data-i18n-key="mag_issue_july" style="margin:0;color:#1F497D;">2025年7月号</h2>
            <h3 data-i18n-key="mag_theme_july" style="margin:.3rem 0;color:#e67a8a;font-size:1.3rem;">犬と夏のおでかけ × UVケア</h3>
            <p data-i18n-key="mag_desc_july" style="font-size:1rem;">紫外線対策とワンちゃんとのおでかけスポットをご紹介♪</p>
          </div></div>`
      },
      { html: `<h3 style="color:#1F497D;" data-i18n-key="mag_event_title">今月のイベント</h3><ul><li>7/12 サマーフェス</li><li>7/20 ビーチクリーン</li><li>7/28 ドッグヨガ</li></ul>` },
      { html: `<h3 style="color:#1F497D;" data-i18n-key="mag_cafe_title">おすすめカフェ</h3><p>代官山のカフェ紹介...</p>` },
      { html: `<h3 style="color:#1F497D;" data-i18n-key="mag_column_july">プチコラム：夏のUV対策</h3><p>...</p>` },
      { html: `<h3 style="color:#1F497D;" data-i18n-key="mag_recommend_title">ちぃまめのおすすめ</h3>
               <div style="display:flex;gap:.5rem;"><img src="${RORO_ENV.assetsUrl}images/product_uv_clothes.png" style="width:40%;max-height:120px;object-fit:contain;border-radius:8px;" alt=""><div><strong>UVカット冷感犬服</strong><br>¥2,980</div></div>` },
      { html: `<img src="${RORO_ENV.assetsUrl}images/pet_cafe.png" style="width:100%;max-height:250px;object-fit:cover;border-radius:8px;" alt="">` },
      { html: `<h3 style="color:#1F497D;" data-i18n-key="mag_advice_title">ワンポイントアドバイス</h3><ul><li>水分補給をしっかり</li></ul>` },
      {
        html: `<div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;">
                 <div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D;">PROJECT RORO 2025年7月号</div>
               </div>`
      }
    ],
  };

  let pages = [];
  let pageIndex = 0;

  function openIssue(issueId) {
    pages = issues[issueId] || [];
    pageIndex = 0;
    viewer.style.display = 'flex';
    renderPages();
  }

  function renderPages() {
    book.innerHTML = '';
    // ナビゲーション
    const close = document.createElement('div'); close.className='close-btn'; close.innerHTML='&times;';
    const prev  = document.createElement('div'); prev.className='nav-arrow prev'; prev.innerHTML='&#9664;';
    const next  = document.createElement('div'); next.className='nav-arrow next'; next.innerHTML='&#9654;';
    close.addEventListener('click', (e)=>{ e.stopPropagation(); viewer.style.display='none'; });
    prev.addEventListener('click',  (e)=>{ e.stopPropagation(); flipBack(); });
    next.addEventListener('click',  (e)=>{ e.stopPropagation(); flipNext(); });
    book.append(close, prev, next);

    const total = pages.length;
    for (let i=total-1; i>=0; i--) {
      const page = document.createElement('div');
      page.className='page'; page.dataset.index = i;
      page.style.zIndex = (total - i);
      const content = document.createElement('div');
      content.className = 'page-content';
      content.innerHTML = pages[i].html;
      page.appendChild(content);
      book.appendChild(page);
    }
    updateNav();
    if (typeof applyTranslations === 'function') { applyTranslations(); }
  }

  function updateNav() {
    const total = pages.length;
    book.querySelector('.nav-arrow.prev').style.display = pageIndex===0 ? 'none':'block';
    book.querySelector('.nav-arrow.next').style.display = pageIndex>=total-1 ? 'none':'block';
  }

  function flipNext() {
    const total = pages.length;
    if (pageIndex >= total) return;
    const target = book.querySelectorAll('.page')[pages.length-1-pageIndex];
    target.classList.add('flipped');
    pageIndex++; updateNav();
  }

  function flipBack() {
    if (pageIndex <= 0) return;
    pageIndex--;
    const target = book.querySelectorAll('.page')[pages.length-1-pageIndex];
    target.classList.remove('flipped');
    updateNav();
  }

  book.addEventListener('click', (e) => {
    if (e.target.classList.contains('nav-arrow') || e.target.classList.contains('close-btn')) {
      return;
    }
    const rect = book.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    if (clickX > rect.width / 2) flipNext(); else flipBack();
  });

  document.querySelectorAll('.magazine-card').forEach(card => {
    card.addEventListener('click', () => {
      openIssue(card.dataset.issue);
    });
  });
});
