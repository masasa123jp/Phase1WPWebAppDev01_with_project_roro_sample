/*
 * roro-map.js – Google Maps API でイベントマップを描画
 * data/events.js のイベントデータをロードし、マーカーを配置します。
 */
let map;
let infoWindow;
let markersList = [];
const selectedCategories = new Set();

document.addEventListener('DOMContentLoaded', () => {
  requireLogin();

  // Google Maps 読込
  const lang = getUserLang();
  const regionMap = { ja:'JP', en:'US', zh:'JP', ko:'KR' };
  const region = regionMap[lang] || 'JP';
  const langParam = (lang === 'zh' ? 'zh-CN' : lang);
  const key = RORO_ENV.opts.map_api_key || '';

  const script = document.createElement('script');
  script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=initMap&language=${encodeURIComponent(langParam)}&region=${encodeURIComponent(region)}&loading=async`;
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);

  // カテゴリボタン生成
  createCategoryButtons();
});

// Google Maps 初期化
window.initMap = () => {
  if (typeof google === 'undefined' || !google.maps) {
    console.error('Google Maps API not loaded');
    return;
  }
  requireLogin();
  const defaultCenter = { lat: 35.681236, lng: 139.767125 };
  const styles = [
    { elementType:'geometry', stylers:[{ color:'#F5F5F5' }] },
    { elementType:'labels.icon', stylers:[{ visibility:'off' }] },
    { elementType:'labels.text.fill', stylers:[{ color:'#616161' }] },
    { elementType:'labels.text.stroke', stylers:[{ color:'#F5F5F5' }] },
    { featureType:'poi', elementType:'geometry', stylers:[{ color:'#eeeeee' }] },
    { featureType:'poi', elementType:'labels.text.fill', stylers:[{ color:'#757575' }] },
    { featureType:'road', elementType:'geometry', stylers:[{ color:'#ffffff' }] },
    { featureType:'road.highway', elementType:'geometry', stylers:[{ color:'#dadada' }] },
    { featureType:'water', elementType:'geometry', stylers:[{ color:'#cddffb' }] },
    { featureType:'water', elementType:'labels.text.fill', stylers:[{ color:'#9e9e9e' }] }
  ];
  map = new google.maps.Map(document.getElementById('map'), {
    center: defaultCenter,
    zoom: 6,
    styles: styles,
    mapTypeControl: false,
    fullscreenControl: false
  });
  infoWindow = new google.maps.InfoWindow();

  // eventsData は data/events.js で定義されているグローバル変数
  const localEvents = Array.isArray(window.eventsData) ? window.eventsData.slice() : [];
  // ダミー施設を追加 (generateDummyEvents は下で定義)
  localEvents.push(...generateDummyEvents(200));
  if (localEvents.length === 0) {
    console.warn('イベントデータが空です');
    return;
  }
  const bounds = new google.maps.LatLngBounds();
  // マーカー描画
  localEvents.forEach((eventItem, index) => {
    const position = { lat: eventItem.lat, lng: eventItem.lon };
    if (!eventItem.category) {
      if (index < (window.eventsData ? window.eventsData.length : 0)) {
        eventItem.category = 'event';
      } else {
        const catOptions = ['restaurant','hotel','activity','museum','facility'];
        eventItem.category = catOptions[Math.floor(Math.random()*catOptions.length)];
      }
    }
    const categoryColors = {
      event:'#FFC72C', restaurant:'#E74C3C', hotel:'#8E44AD', activity:'#3498DB', museum:'#27AE60', facility:'#95A5A6'
    };
    const color = categoryColors[eventItem.category] || '#FFC72C';
    const marker = new google.maps.Marker({
      position: position,
      map: map,
      title: eventItem.name,
      icon: makeMarkerIcon(color)
    });
    bounds.extend(position);
    markersList.push({ marker, category:eventItem.category });
    // InfoWindow
    marker.addListener('click', () => {
      const dateStr    = eventItem.date && eventItem.date !== 'nan'    ? `<p>${eventItem.date}</p>`    : '';
      const addressStr = eventItem.address && eventItem.address !== 'nan'? `<p>${eventItem.address}</p>`: '';
      const linkStr    = eventItem.url && eventItem.url !== 'nan'       ? `<p><a href="${eventItem.url}" target="_blank" rel="noopener">詳細を見る</a></p>`:'';
      const t  = (window.translations && window.translations[getUserLang()]) || {};
      const saveLabel = t.save || '保存';
      const content = `
        <div class="info-content" style="position:relative;">
          <h3 style="margin:0 0 0.2rem 0;">${eventItem.name}</h3>
          ${dateStr}${addressStr}${linkStr}
          <div class="save-wrapper" style="position:relative;display:inline-block;margin-top:0.5rem;">
            <button class="save-btn" data-index="${index}" style="background-color:transparent;border:none;color:#1F497D;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
              <span class="save-icon"></span><span>${saveLabel}</span>
            </button>
            <div class="save-menu" style="display:none;position:absolute;top:110%;left:0;background:#fff;border:1px solid #ccc;border-radius:6px;padding:0.4rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);width:130px;font-size:0.8rem;">
              <div class="save-option" data-list="favorite" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>❤️</span><span>${t.save_favorite||'お気に入り'}</span></div>
              <div class="save-option" data-list="want" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span></span><span>${t.save_want||'行ってみたい'}</span></div>
              <div class="save-option" data-list="plan" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span></span><span>${t.save_plan||'旅行プラン'}</span></div>
              <div class="save-option" data-list="star" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>⭐</span><span>${t.save_star||'スター付き'}</span></div>
            </div>
          </div>
        </div>`;
      infoWindow.setContent(content);
      infoWindow.open(map, marker);
      google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
        const saveBtn = document.querySelector('.save-btn');
        const saveMenu = document.querySelector('.save-menu');
        saveBtn?.addEventListener('click', (e) => {
          e.stopPropagation();
          saveMenu.style.display = saveMenu.style.display === 'none' ? 'block' : 'none';
        });
        saveMenu?.querySelectorAll('.save-option').forEach(opt => {
          opt.addEventListener('click', () => {
            addToFavorites(eventItem, opt.getAttribute('data-list') || 'favorite');
            saveMenu.style.display = 'none';
          });
        });
        if (typeof applyTranslations === 'function') applyTranslations();
      });
    });
  });
  map.fitBounds(bounds);
  // 周辺表示ボタン
  document.getElementById('reset-view-btn')?.addEventListener('click', () => {
    let center = null; let zoomLevel = 11;
    try {
      const u = JSON.parse(sessionStorage.getItem('user')) || {};
      if (u.address && (u.address.includes('池袋') || u.address.includes('豊島区'))) {
        center = { lat: 35.7303, lng: 139.7099 };
        zoomLevel = 11;
      }
    } catch (e) {}
    if (center) { map.setCenter(center); map.setZoom(zoomLevel); }
    else { map.setCenter(defaultCenter); map.setZoom(6); }
  });

  createCategoryButtons();
  updateMarkerVisibility();
};

// カテゴリボタン生成
function createCategoryButtons() {
  const bar = document.getElementById('category-bar');
  if (!bar) return;
  const cats = [
    { key:'event', emoji:'' },
    { key:'restaurant', emoji:'' },
    { key:'hotel', emoji:'' },
    { key:'activity', emoji:'' },
    { key:'museum', emoji:'' },
    { key:'facility', emoji:'' }
  ];
  cats.forEach(cat => {
    const btn = document.createElement('button');
    btn.className = 'filter-btn';
    btn.setAttribute('data-category', cat.key);
    const label = document.createElement('span');
    label.setAttribute('data-i18n-key', 'cat_' + cat.key);
    label.textContent = (window.translations && window.translations[getUserLang()] && window.translations[getUserLang()]['cat_'+cat.key]) || cat.key;
    btn.appendChild(label);
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-category');
      if (btn.classList.contains('active')) {
        btn.classList.remove('active');
        selectedCategories.delete(key);
      } else {
        btn.classList.add('active');
        selectedCategories.add(key);
      }
      updateMarkerVisibility();
    });
    bar.appendChild(btn);
  });
  if (typeof applyTranslations === 'function') applyTranslations();
}

// マーカー表示/非表示更新
function updateMarkerVisibility() {
  markersList.forEach(item => {
    const visible = selectedCategories.size === 0 || selectedCategories.has(item.category);
    if (item.marker && typeof item.marker.setVisible === 'function') {
      item.marker.setVisible(visible);
    }
  });
}

// お気に入り保存
function addToFavorites(eventItem, listType='favorite') {
  let favorites = [];
  try { favorites = JSON.parse(localStorage.getItem('favorites')) || []; } catch(e) {}
  const exists = favorites.some(f => f.name === eventItem.name && f.lat === eventItem.lat && f.lon === eventItem.lon && f.listType === listType);
  const t = (window.translations && window.translations[getUserLang()]) || {};
  if (!exists) {
    favorites.push({ ...eventItem, listType });
    localStorage.setItem('favorites', JSON.stringify(favorites));
    alert(t.saved_msg || 'リストに保存しました');
  } else {
    alert(t.already_saved_msg || '既にこのリストに登録済みです');
  }
}

// ダミーイベント生成
function generateDummyEvents(count) {
  const results = [];
  const baseLat=35.7303, baseLng=139.7099;
  function gaussianRandom() {
    let u=0,v=0; while(u===0) u=Math.random(); while(v===0) v=Math.random();
    return Math.sqrt(-2.0 * Math.log(u)) * Math.cos(2.0 * Math.PI * v);
  }
  for (let i=0; i<count; i++) {
    let lat = baseLat + gaussianRandom()*0.05;
    let lng = baseLng + gaussianRandom()*0.06;
    results.push({
      name: `ペット関連施設 ${i+1}`,
      date: '',
      location: 'dummy',
      venue: 'dummy',
      address: '東京都近郊のペット施設',
      prefecture: '東京都',
      city: '',
      lat: lat,
      lon: lng,
      source: 'Dummy',
      url: '#'
    });
  }
  return results;
}
