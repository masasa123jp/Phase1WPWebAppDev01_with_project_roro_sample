/******************************************************
 * MapView.tsx – Google Maps + Marker Clusterer wrapper
 * © RoRo Dev Team 2025
 *****************************************************/

import { useEffect, useRef } from 'react';
import { Loader } from '@googlemaps/js-api-loader';
import { MarkerClusterer } from '@googlemaps/markerclusterer';

interface LatLng { lat: number; lng: number }
interface MapViewProps {
  lat: number;
  lng: number;
  zoom?: number;
  markers?: LatLng[];
  height?: number;    // px
  width?: string;     // css
}

export default function MapView({
  lat,
  lng,
  zoom = 14,
  markers = [],
  height = 400,
  width = '100%',
}: MapViewProps) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    /* --- ① Google Maps JS API ロード --------------------------- */
    const loader = new Loader({
      apiKey: import.meta.env.VITE_GMAPS_KEY,
      version: 'weekly',
    });

    let map: google.maps.Map | undefined;

    loader.load().then((google) => {
      if (!ref.current) return;

      map = new google.maps.Map(ref.current, {
        center: { lat, lng },
        zoom,
        mapId: 'RORO_MAP_ID', // Optional Styled MapId
      });

      /* --- ② Markers + Clusterer ------------------------------- */
      const gMarkers = markers.map(
        (p) => new google.maps.Marker({ position: p }),
      );
      new MarkerClusterer({ map, markers: gMarkers });
    });

    /* --- ③ クリーンアップ ------------------------------------- */
    return () => { map = undefined; };
  }, [lat, lng, zoom, markers]);

  return (
    <div
      ref={ref}
      style={{ width, height, borderRadius: 8, overflow: 'hidden' }}
    />
  );
}
