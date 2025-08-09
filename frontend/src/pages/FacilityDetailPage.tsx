import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api/client';
import { ReviewForm } from '../components/ReviewForm';

export default function FacilityDetailPage() {
  const { id = '' } = useParams();
  const { data } = useQuery({
    queryKey: ['facility', id],
    queryFn: () => apiClient.get(`/facilities/${id}`).then((r) => r.data),
  });

  if (!data) return <p>Loading…</p>;

  return (
    <div>
      <h2>{data.name}</h2>
      <p>{data.address}</p>
      <ReviewForm facilityId={data.id} />
      <h3>Reviews</h3>
      <ul>
        {data.reviews?.map((r) => (
          <li key={r.id}>
            {r.rating}★ – {r.comment}
          </li>
        ))}
      </ul>
    </div>
  );
}
