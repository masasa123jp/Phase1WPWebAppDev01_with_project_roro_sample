/*
 * roro-favorites.js – お気に入り一覧表示
 */
document.addEventListener('DOMContentLoaded', () => {
  requireLogin();
  const listEl = document.getElementById('favorites-list');
  const noFav  = document.getElementById('no-favorites');

  let favorites = [];
  try {
    favorites = JSON.parse(localStorage.getItem('favorites')) || [];
  } catch(e) { favorites = []; }

  if (favorites.length === 0) {
    noFav.style.display = 'block';
    if (typeof applyTranslations === 'function') applyTranslations();
    return;
  }

  favorites.forEach((ev, index) => {
    const li = document.createElement('li');
    li.className = 'favorite-item';
    const details = document.createElement('div');
    details.className = 'details';

    const title = document.createElement('a');
    title.textContent = ev.name;
    title.href = ev.url || '#';
    title.target = '_blank';
    title.rel = 'noopener';

    const dateP = document.createElement('p');
    dateP.textContent = ev.date || '';
    dateP.style.margin = '0.2rem 0';

    const addressP = document.createElement('p');
    addressP.textContent = ev.address || '';
    addressP.style.margin = '0';

    // 種別バッジ
    if (ev.listType) {
      const badge = document.createElement('span');
      badge.style.marginRight = '0.4rem';
      badge.style.fontSize = '0.9rem';
      const t = (window.translations && window.translations[getUserLang()]) || {};
      const key = 'list_' + ev.listType;
      const baseLabel = t[key] || '';
      if (baseLabel) {
        let icon = '';
        switch (ev.listType) {
          case 'favorite': icon = '❤️'; break;
          case 'want': icon = ''; break;
          case 'plan': icon = ''; break;
          case 'star': icon = '⭐'; break;
        }
        badge.textContent = `${icon} ${baseLabel}`;
        details.appendChild(badge);
      }
    }

    details.appendChild(title);
    if (ev.date) details.appendChild(dateP);
    if (ev.address) details.appendChild(addressP);

    const removeBtn = document.createElement('button');
    removeBtn.className = 'remove-btn';
    const t2 = (window.translations && window.translations[getUserLang()]) || {};
    removeBtn.textContent = t2.delete || '削除';
    removeBtn.addEventListener('click', () => {
      favorites.splice(index, 1);
      localStorage.setItem('favorites', JSON.stringify(favorites));
      location.reload();
    });

    li.appendChild(details);
    li.appendChild(removeBtn);
    listEl.appendChild(li);
  });
});
