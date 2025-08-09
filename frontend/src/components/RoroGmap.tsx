import React, { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

interface Marker {
  id: number;
  name: string;
  lat: string | number;
  lng: string | number;
  type: 'facility' | 'event' | 'material';
  species?: 'dog' | 'cat' | 'both';
}

/**
 * Google Maps 用コンポーネント。種別に応じたアイコンを表示し、情報ウィンドウのタイプ文言は翻訳します。
 */
export default function RoroGmap({ apiKey, markers }: { apiKey: string; markers: Marker[] }) {
  const { t } = useTranslation();
  useEffect(() => {
    if (!window.google || !apiKey) return;
    const map = new window.google.maps.Map(document.getElementById('roro-map') as HTMLElement, {
      center: { lat: 35.681236, lng: 139.767125 },
      zoom: 12,
    });
    const iconBase = '/wp-content/plugins/roro-core/assets/icons/';
    markers.forEach((m) => {
      const icon =
        m.type === 'facility'
          ? iconBase + (m.species === 'cat' ? 'cafe-cat.png' : 'cafe-dog.png')
          : m.type === 'event'
          ? iconBase + 'event.png'
          : iconBase + 'book.png';
      const marker = new window.google.maps.Marker({
        position: { lat: Number(m.lat), lng: Number(m.lng) },
        map,
        title: m.name,
        icon,
      });
      const infoWindow = new window.google.maps.InfoWindow({
        content: `<h4>${m.name}</h4><p>${t(m.type)}</p>`,
      });
      marker.addListener('click', () => {
        infoWindow.open({ map, anchor: marker });
      });
    });
  }, [apiKey, markers, t]);
  return <div id="roro-map" style={{ width: '100%', height: '400px' }} />;
}
