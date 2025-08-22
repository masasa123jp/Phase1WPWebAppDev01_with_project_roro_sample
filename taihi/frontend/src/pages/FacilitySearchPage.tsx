import { useState } from 'react';
import { useFacilities } from '../hooks/useApi';
import { MapView } from '../components/MapView';
import { Link } from 'react-router-dom';

export default function FacilitySearchPage() {
  const [lat, setLat] = useState(35.6895);
  const [lng, setLng] = useState(139.6917);
  const { data, isLoading } = useFacilities(lat, lng);

  return (
    <main>
      <h2>Nearby Facilities</h2>
      <MapView lat={lat} lng={lng} markers={data?.map((f) => ({ lat: f.lat, lng: f.lng })) ?? []} />
      {isLoading && <p>Loading…</p>}
      <ul>
        {data?.map((f) => (
          <li key={f.id}>
            <Link to={`/facility/${f.id}`}>{f.name}</Link> – {Math.round(f.distance)} m
          </li>
        ))}
      </ul>
    </main>
  );
}
