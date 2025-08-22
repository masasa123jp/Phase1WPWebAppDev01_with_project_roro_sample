// roro-map/assets/js/cluster-sort.js
// 距離計算とナイーブなグリッドクラスタリング（マップライブラリ非依存）
(function(global){
  function haversine(lat1,lng1,lat2,lng2){
    const R=6371; const toRad=x=>x*Math.PI/180;
    const dLat=toRad(lat2-lat1), dLng=toRad(lng2-lng1);
    const a=Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)**2;
    return 2*R*Math.asin(Math.sqrt(a));
  }
  function sortByDistance(spots, center){
    return spots.map(s => ({...s, distance_km: haversine(center.lat, center.lng, s.lat, s.lng)}))
                .sort((a,b)=>a.distance_km-b.distance_km);
  }
  function cluster(spots, cellKm){
    const grid = new Map();
    function key(lat,lng){ return Math.round(lat/cellKm)+':'+Math.round(lng/cellKm); }
    for (const s of spots){
      const k = key(s.lat, s.lng);
      if(!grid.has(k)) grid.set(k, []);
      grid.get(k).push(s);
    }
    return Array.from(grid.values()).map(items => ({ count: items.length, items }));
  }
  global.RoroClusterSort = { haversine, sortByDistance, cluster };
})(window);
