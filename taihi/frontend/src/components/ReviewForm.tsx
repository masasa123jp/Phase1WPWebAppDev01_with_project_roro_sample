import { useState } from 'react';
import { useSubmitReview } from '../hooks/useApi';

export const ReviewForm = ({ facilityId }: { facilityId: number }) => {
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const { mutate, isPending } = useSubmitReview();

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        mutate({ facility_id: facilityId, rating, comment });
        setComment('');
      }}
    >
      <label>
        Rating:
        <input
          type="number"
          min={1}
          max={5}
          value={rating}
          onChange={(e) => setRating(+e.target.value)}
        />
      </label>
      <label>
        Comment:
        <textarea value={comment} onChange={(e) => setComment(e.target.value)} />
      </label>
      <button disabled={isPending}>Submit</button>
    </form>
  );
};
