/**
 * MapWithPhotos のサイドバー用 – 近隣施設を距離順に表示。
 * 検索は `/roro/v1/facilities?zipcode=...`.
 */
import { useFacilitySearch } from '@/hooks/useFacilitySearch';

export default function FacilityList({ zipcode }: { zipcode: string }) {
  const { data, isLoading } = useFacilitySearch(zipcode);
  if (isLoading) return <p>Loading…</p>;

  return (
    <ul className="space-y-2">
      {data?.map((f: any) => (
        <li key={f.ID} className="border p-2 rounded">
          <h3 className="font-bold">{f.post_title}</h3>
          <p>{f.dist_km.toFixed(1)} km</p>
          <p className="text-sm text-gray-500">{f.address}</p>
        </li>
      ))}
    </ul>
  );
}
