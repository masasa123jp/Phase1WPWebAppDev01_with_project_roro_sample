import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import RoroGmap from '../components/RoroGmap';

interface EventItem {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  lat: string;
  lng: string;
}

export default function EventListPage() {
  const { t } = useTranslation();
  const [events, setEvents] = useState<EventItem[]>([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/wp-json/roro/v1/facilities?species=both&category=A')
      .then((res) => res.json())
      .then((data) => {
        setEvents(data.events);
        setLoading(false);
      });
  }, []);
  return (
    <div>
      <h1>{t('Event List')}</h1>
      {loading ? (
        <p>{t('Loading...')}</p>
      ) : (
        <>
          <ul>
            {events.map((ev) => (
              <li key={ev.id}>
                {ev.name}（{new Date(ev.start_time).toLocaleString()}）
              </li>
            ))}
          </ul>
          <RoroGmap
            apiKey={process.env.NEXT_PUBLIC_GOOGLE_API_KEY as string}
            markers={events.map((ev) => ({ id: ev.id, name: ev.name, lat: ev.lat, lng: ev.lng, type: 'event' }))}
          />
        </>
      )}
    </div>
  );
}
