/*
 * Front‑end logic for the RORO Map plugin.  This script initialises a
 * Google map, populates it with markers returned from the REST API and
 * provides UI for searching by keyword, category, date range and
 * distance from the user’s location.  All display strings come from
 * the RORO_EVENTS_CFG.i18n object which is localised via PHP.
 */
(function() {
  // References to the map and any marker/infowindow we create.  These
  // variables are scoped to the closure so that other functions can
  // access them without polluting the global scope.
  let map;
  let markers = [];
  let infowin;

  // Maintain the current filter state.  This object is updated
  // whenever the user changes a filter or when geolocation is used.
  const state = {
    lat: null,
    lng: null,
    radius_km: RORO_EVENTS_CFG.defaults.radiusKm,
    q: '',
    categories: [],
    date_from: '',
    date_to: '',
    order_by: 'date'
  };

  /**
   * Convenience wrapper for document.getElementById.  Using a short
   * helper keeps the rest of the code terse and readable.
   *
   * @param {string} id
   * @returns {HTMLElement|null}
   */
  function $(id) {
    return document.getElementById(id);
  }

  /**
   * Initialise the Google map.  Centres on Tokyo by default but will
   * re‑centre if the user’s location is later used.  Also prepares the
   * infowindow used to display event details when markers are clicked.
   */
  function initMap() {
    map = new google.maps.Map(document.getElementById('roro-map'), {
      center: { lat: 35.681236, lng: 139.767125 },
      zoom: 10,
      mapTypeControl: false
    });
    infowin = new google.maps.InfoWindow();
  }

  /**
   * Remove all markers currently on the map.  Called before drawing new
   * results so that markers don’t accumulate.
   */
  function clearMarkers() {
    markers.forEach(m => m.setMap(null));
    markers = [];
  }

  /**
   * Add a marker to the map for a single event.  When clicked an
   * information window will show basic details about the event.  The
   * info is HTML-escaped to prevent injection.
   *
   * @param {Object} ev Event object returned from the REST API
   */
  function addMarker(ev) {
    const pos = { lat: parseFloat(ev.latitude), lng: parseFloat(ev.longitude) };
    const marker = new google.maps.Marker({ position: pos, map: map, title: ev.title });
    marker.addListener('click', function() {
      const html = '<div class="roro-infowin">' +
                   '<div class="roro-infowin-title">' + escapeHtml(ev.title) + '</div>' +
                   '<div class="roro-infowin-date">' + formatDate(ev.start_time) + '</div>' +
                   (ev.address ? '<div class="roro-infowin-addr">' + escapeHtml(ev.address) + '</div>' : '') +
                   (ev.distance_km != null ? '<div class="roro-infowin-dist">' + ev.distance_km.toFixed(1) + ' km</div>' : '') +
                   '</div>';
      infowin.setContent(html);
      infowin.open(map, marker);
    });
    markers.push(marker);
  }

  /**
   * Fit the map bounds to include all current markers.  Called after
   * markers are added so that the map view encompasses all events.
   */
  function fitBoundsToMarkers() {
    if (!markers.length) return;
    const bounds = new google.maps.LatLngBounds();
    markers.forEach(m => bounds.extend(m.getPosition()));
    map.fitBounds(bounds);
  }

  /**
   * Escape HTML special characters in a string to prevent HTML
   * injection when inserting user content into the DOM.
   *
   * @param {string} str
   * @returns {string}
   */
  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(c) {
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', '\'':'&#39;' }[c];
    });
  }

  /**
   * Format a date string in the user’s locale.  Expects ISO 8601
   * strings and falls back gracefully if parsing fails.
   *
   * @param {string} ds
   * @returns {string}
   */
  function formatDate(ds) {
    if (!ds) return '';
    try {
      const d = new Date(ds.replace(' ', 'T'));
      return d.toLocaleString();
    } catch (e) {
      return ds;
    }
  }

  /**
   * Fetch the list of event categories from the REST API and populate
   * the category select element.  Supports multi‑select.
   */
  function fetchCategories() {
    return fetch(RORO_EVENTS_CFG.restBase + '/event-categories', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(j => {
        const sel = $('roro-category');
        if (!sel) return;
        sel.innerHTML = '';
        (j.items || []).forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.code;
          opt.textContent = c.name;
          sel.appendChild(opt);
        });
      });
  }

  /**
   * Read the current values from the filter controls and store them on
   * the state object.  This method does not trigger a search itself;
   * call search() after updating the state.
   */
  function collectFilters() {
    state.q         = $('roro-q').value.trim();
    state.date_from = $('roro-date-from').value || '';
    state.date_to   = $('roro-date-to').value || '';
    state.radius_km = parseFloat($('roro-radius').value || RORO_EVENTS_CFG.defaults.radiusKm);
    const sel = $('roro-category');
    const cats = [];
    for (let i = 0; i < sel.options.length; i++) {
      if (sel.options[i].selected) cats.push(sel.options[i].value);
    }
    state.categories = cats;
  }

  /**
   * Perform a search by sending the current filters to the REST API and
   * rendering the results.  When lat/lng are set on the state the
   * search is sorted by distance and includes that in the response.
   */
  function search() {
    collectFilters();
    const params = new URLSearchParams();
    if (state.q) params.append('q', state.q);
    state.categories.forEach(c => params.append('categories[]', c));
    if (state.date_from) params.append('date_from', state.date_from);
    if (state.date_to)   params.append('date_to', state.date_to);
    if (state.lat != null && state.lng != null && state.radius_km > 0) {
      params.append('lat', state.lat);
      params.append('lng', state.lng);
      params.append('radius_km', state.radius_km);
      params.append('order_by', 'distance');
    } else {
      params.append('order_by', 'date');
    }
    fetch(RORO_EVENTS_CFG.restBase + '/events?' + params.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(renderResults)
      .catch(e => { console.error(e); });
  }

  /**
   * Render the results list and map markers from the JSON returned
   * by the REST API.  If no items are present a friendly message is
   * displayed instead.  After drawing markers the map bounds are
   * adjusted to show them all.
   *
   * @param {Object} j JSON response from the REST API
   */
  function renderResults(j) {
    const list = $('roro-list');
    list.innerHTML = '';
    clearMarkers();
    if (!j.items || !j.items.length) {
      list.innerHTML = '<div class="roro-empty">' + escapeHtml(RORO_EVENTS_CFG.i18n.no_results || 'No results') + '</div>';
      return;
    }
    j.items.forEach(ev => {
      addMarker(ev);
      const item = document.createElement('div');
      item.className = 'roro-item';
      item.innerHTML =
        '<div class="roro-item-title">' + escapeHtml(ev.title) + '</div>' +
        (ev.category ? '<div class="roro-item-cat">' + escapeHtml(ev.category) + '</div>' : '') +
        '<div class="roro-item-date">' + formatDate(ev.start_time) + '</div>' +
        (ev.address ? '<div class="roro-item-addr">' + escapeHtml(ev.address) + '</div>' : '') +
        (ev.distance_km != null ? '<div class="roro-item-dist">' + ev.distance_km.toFixed(1) + ' km</div>' : '');
      list.appendChild(item);
    });
    fitBoundsToMarkers();
  }

  /**
   * Update the geolocation status text in the UI.  Useful for showing
   * progress and error messages when attempting to get the user’s
   * location.
   *
   * @param {string} msg
   */
  function setGeoStatus(msg) {
    const el = $('roro-geo-status');
    if (el) el.textContent = msg || '';
  }

  /**
   * Attempt to use the browser’s geolocation API to determine the
   * user’s current position.  Updates the state with the latitude
   * and longitude and triggers a search.  If the API is unsupported
   * or permission is denied appropriate messages are shown.
   */
  function useMyLocation() {
    if (!navigator.geolocation) {
      setGeoStatus(RORO_EVENTS_CFG.i18n.geo_not_supported || 'Geolocation not supported');
      return;
    }
    setGeoStatus(RORO_EVENTS_CFG.i18n.geo_fetching || 'Fetching location…');
    navigator.geolocation.getCurrentPosition(
      pos => {
        state.lat = pos.coords.latitude;
        state.lng = pos.coords.longitude;
        setGeoStatus(RORO_EVENTS_CFG.i18n.geo_ok || 'Location set');
        if (map) map.setCenter({ lat: state.lat, lng: state.lng });
        search();
      },
      err => {
        console.warn(err);
        setGeoStatus(RORO_EVENTS_CFG.i18n.geo_denied || 'Permission denied');
      },
      { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 }
    );
  }

  // Initialise everything once the DOM is ready.  Fetch categories,
  // then perform an initial search so that the map is populated from
  // the outset.  Attach event listeners to buttons and inputs.
  document.addEventListener('DOMContentLoaded', function() {
    initMap();
    fetchCategories().then(search);
    $('roro-search').addEventListener('click', search);
    $('roro-reset').addEventListener('click', function() {
      $('roro-q').value = '';
      $('roro-date-from').value = '';
      $('roro-date-to').value = '';
      const sel = $('roro-category');
      for (let i = 0; i < sel.options.length; i++) {
        sel.options[i].selected = false;
      }
      state.lat = state.lng = null;
      $('roro-geo-status').textContent = '';
      search();
    });
    $('roro-use-geo').addEventListener('click', useMyLocation);
  });
})();