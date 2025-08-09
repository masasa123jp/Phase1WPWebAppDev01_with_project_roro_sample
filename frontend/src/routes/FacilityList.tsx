import React, { useEffect, useState } from 'react';
import { searchFacilities, Facility } from '../api/facilities';

export default function FacilityList() {
  const [facilities, setFacilities] = useState<Facility[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    navigator.geolocation.getCurrentPosition(async (pos) => {
      setLoading(true);
      try {
        const { latitude, longitude } = pos.coords;
        setFacilities(await searchFacilities(latitude, longitude));
      } finally {
        setLoading(false);
      }
    });
  }, []);

  if (loading) return <p>Loading…</p>;
  if (!facilities.length) return <p>No facilities found.</p>;

  return (
    <ul>
      {facilities.map((f) => (
        <li key={f.id}>
          {f.name} – {(f.dist / 1000).toFixed(1)} km
        </li>
      ))}
    </ul>
  );
}
