/**
 * マガジンスライダー（バニラJS）
 * - キーボード操作（←/→）・フォーカス管理は最低限
 */
(function(){
  'use strict';
  var conf = window.RORO_MAG_SLIDER || {slides:[],height:520};
  var slider = document.querySelector('.roro-mag-slider'); if (!slider) return;
  var track  = slider.querySelector('.roro-mag-track');
  var prevB  = slider.querySelector('.roro-mag-prev');
  var nextB  = slider.querySelector('.roro-mag-next');
  // Apply i18n labels when provided by the configuration.  This
  // allows the PHP layer to supply translated strings for the
  // navigation buttons.  Fallback to the existing button text
  // otherwise.  Prefix/suffix arrow characters to preserve the
  // direction indicators across languages.
  if (conf.i18n) {
    var prevLabel = conf.i18n.prev || prevB.textContent.trim();
    var nextLabel = conf.i18n.next || nextB.textContent.trim();
    prevB.textContent = '\u2190 ' + prevLabel;
    nextB.textContent = nextLabel + ' \u2192';
    prevB.setAttribute('aria-label', prevLabel);
    nextB.setAttribute('aria-label', nextLabel);
  }

  (conf.slides||[]).forEach(function(s){
    var d = document.createElement('div');
    d.className = 'roro-mag-slide';
    d.innerHTML = s.html || '';
    track.appendChild(d);
  });
  var total = track.children.length, idx = 0;
  function update(){
    track.style.transform = 'translate3d('+(-100*idx)+'%,0,0)';
    prevB.disabled = (idx<=0); nextB.disabled = (idx>=total-1);
  }
  prevB.addEventListener('click', function(){ if (idx>0){ idx--; update(); }});
  nextB.addEventListener('click', function(){ if (idx<total-1){ idx++; update(); }});
  slider.style.height = parseInt(conf.height||520,10)+'px';

  // キーボード
  slider.tabIndex = 0;
  slider.addEventListener('keydown', function(e){
    if (e.key==='ArrowLeft'){ if (idx>0){ idx--; update(); } }
    else if (e.key==='ArrowRight'){ if (idx<total-1){ idx++; update(); } }
  });

  update();
})();
