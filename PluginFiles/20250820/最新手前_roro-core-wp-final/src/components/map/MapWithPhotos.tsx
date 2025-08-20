/**
 * Google Maps JavaScript API を使い、写真投稿をマーカー付きで表示。
 * - props.zipcode に合わせて中心座標をジオコーディング
 * - /roro/v1/photo?zipcode=xxx から最大 50 件を取得し、InfoWindow にサムネイル
 */
import { useEffect, useRef } from 'react';
import { api } from '@/services/apiClient';

declare const google: typeof window.google;

export default function MapWithPhotos({ zipcode }: { zipcode: string }) {
  const mapRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!mapRef.current) return;
    (async () => {
      /* 1) 住所を座標へ */
      const { results } = await api(`/roro/v1/geocode?zipcode=${zipcode}`);
      const center = results[0].location; // {lat,lng}

      /* 2) 地図初期化 */
      const map = new google.maps.Map(mapRef.current!, { center, zoom: 13 });

      /* 3) 写真取得 & マーカー配置 */
      const photos = await api(`/roro/v1/photo?zipcode=${zipcode}`);
      for (const p of photos) {
        const marker = new google.maps.Marker({
          position: { lat: p.lat, lng: p.lng },
          map,
          title: p.breed ?? 'Photo',
        });
        const iw = new google.maps.InfoWindow({
          content: `<img src="${p.thumb_url}" width="120" />`,
        });
        marker.addListener('click', () => iw.open({ anchor: marker, map }));
      }
    })();
  }, [zipcode]);

  return <div ref={mapRef} className="w-full h-96 rounded-lg" />;
}
