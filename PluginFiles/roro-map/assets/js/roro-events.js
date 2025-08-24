(function(){
  var map, markers=[], infowin;
  var state = {
    lat: null, lng: null, radius_km: RORO_EVENTS_CFG.defaults.radiusKm,
    q: '', categories: [], date_from: '', date_to: '', order_by: 'date'
  };

  function $(id){ return document.getElementById(id); }

  function initMap(){
    map = new google.maps.Map(document.getElementById('roro-map'), {
      center: {lat: 35.681236, lng: 139.767125}, // Tokyo Station
      zoom: 10, mapTypeControl: false
    });
    infowin = new google.maps.InfoWindow();
  }

  function clearMarkers(){
    markers.forEach(function(m){ m.setMap(null); });
    markers = [];
  }

  function addMarker(ev){
    var pos = {lat: parseFloat(ev.latitude), lng: parseFloat(ev.longitude)};
    var m = new google.maps.Marker({position: pos, map: map, title: ev.title});
    m.addListener('click', function(){
      var html = '<div class="roro-infowin">'
               + '<div class="roro-infowin-title">'+ escapeHtml(ev.title) +'</div>'
               + '<div class="roro-infowin-date">'+ formatDate(ev.start_time) +'</div>'
               + (ev.address ? '<div class="roro-infowin-addr">'+ escapeHtml(ev.address) +'</div>' : '')
               + (ev.distance_km ? '<div class="roro-infowin-dist">'+ Number(ev.distance_km).toFixed(1) +' km</div>' : '')
               + '</div>';
      infowin.setContent(html);
      infowin.open(map, m);
    });
    markers.push(m);
  }

  function fitBoundsToMarkers(){
    if (!markers.length) return;
    var bounds = new google.maps.LatLngBounds();
    markers.forEach(function(m){ bounds.extend(m.getPosition()); });
    map.fitBounds(bounds);
  }

  function escapeHtml(str){
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function formatDate(ds){
    if (!ds) return '';
    try{
      var d = new Date(ds.replace(' ', 'T'));
      return d.toLocaleString();
    }catch(e){ return ds; }
  }

  function fetchCategories(){
    return fetch(RORO_EVENTS_CFG.restBase + 'event-categories', {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(j){
        var sel = $('roro-category');
        sel.innerHTML = '';
        j.items.forEach(function(c){
          var opt = document.createElement('option');
          opt.value = c.code; opt.textContent = c.name;
          sel.appendChild(opt);
        });
      });
  }

  function collectFilters(){
    state.q = $('roro-q').value.trim();
    state.date_from = $('roro-date-from').value || '';
    state.date_to   = $('roro-date-to').value || '';
    state.radius_km = parseFloat($('roro-radius').value || RORO_EVENTS_CFG.defaults.radiusKm);

    var sel = $('roro-category'); var cats=[];
    for (var i=0; i<sel.options.length; i++){
      if (sel.options[i].selected) cats.push(sel.options[i].value);
    }
    state.categories = cats;
  }

  function search(){
    collectFilters();
    var params = new URLSearchParams();
    if (state.q) params.append('q', state.q);
    state.categories.forEach(function(c){ params.append('categories[]', c); });
    if (state.date_from) params.append('date_from', state.date_from);
    if (state.date_to)   params.append('date_to', state.date_to);
    if (state.lat != null && state.lng != null && state.radius_km > 0){
      params.append('lat', state.lat); params.append('lng', state.lng); params.append('radius_km', state.radius_km);
      params.append('order_by', 'distance');
    } else {
      params.append('order_by', 'date');
    }
    fetch(RORO_EVENTS_CFG.restBase + 'events?' + params.toString(), {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(renderResults)
      .catch(function(e){ console.error(e); });
  }

  function renderResults(j){
    var list = $('roro-list');
    list.innerHTML = '';
    clearMarkers();
    if (!j.items || !j.items.length){
      list.innerHTML = '<div class="roro-empty">'+ RORO_EVENTS_CFG.i18n.no_results +'</div>';
      return;
    }
    j.items.forEach(function(ev){
      addMarker(ev);
      var item = document.createElement('div');
      item.className = 'roro-item';
      item.innerHTML = '<div class="roro-item-title">'+ escapeHtml(ev.title) +'</div>'
        + (ev.category ? '<div class="roro-item-cat">'+ escapeHtml(ev.category) +'</div>' : '')
        + '<div class="roro-item-date">'+ formatDate(ev.start_time) +'</div>'
        + (ev.address ? '<div class="roro-item-addr">'+ escapeHtml(ev.address) +'</div>' : '')
        + (ev.distance_km ? '<div class="roro-item-dist">' + Number(ev.distance_km).toFixed(1) + ' km</div>' : '');
      list.appendChild(item);
    });
    fitBoundsToMarkers();
  }

  function setGeoStatus(msg){ var el=$('roro-geo-status'); if (el) el.textContent = msg || ''; }

  function useMyLocation(){
    if (!navigator.geolocation){
      setGeoStatus(RORO_EVENTS_CFG.i18n.geo_not_supported);
      return;
    }
    setGeoStatus(RORO_EVENTS_CFG.i18n.geo_fetching);
    navigator.geolocation.getCurrentPosition(function(pos){
      state.lat = pos.coords.latitude; state.lng = pos.coords.longitude;
      setGeoStatus(RORO_EVENTS_CFG.i18n.geo_ok);
      if (map) map.setCenter({lat: state.lat, lng: state.lng});
      search();
    }, function(err){
      console.warn(err);
      setGeoStatus(RORO_EVENTS_CFG.i18n.geo_denied);
    }, {enableHighAccuracy:true, maximumAge:30000, timeout:10000});
  }

  document.addEventListener('DOMContentLoaded', function(){
    initMap();
    fetchCategories().then(search);
    $('roro-search').addEventListener('click', search);
    $('roro-reset').addEventListener('click', function(){
      $('roro-q').value=''; $('roro-date-from').value=''; $('roro-date-to').value='';
      var sel=$('roro-category'); for (var i=0;i<sel.options.length;i++){ sel.options[i].selected = false; }
      state.lat = state.lng = null; $('roro-geo-status').textContent = '';
      search();
    });
    $('roro-use-geo').addEventListener('click', useMyLocation);
  });
})();
