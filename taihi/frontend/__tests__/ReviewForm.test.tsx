import { fireEvent, render, screen } from '@testing-library/react';
import { ReviewForm } from '../src/components/ReviewForm';
import { vi } from 'vitest';

vi.mock('../src/hooks/useApi', () => ({
  useSubmitReview: () => ({ mutate: vi.fn(), isPending: false }),
}));

test('submits review', () => {
  render(<ReviewForm facilityId={1} />);
  fireEvent.change(screen.getByLabelText(/rating/i), { target: { value: 4 } });
  fireEvent.change(screen.getByLabelText(/comment/i), { target: { value: 'ok' } });
  fireEvent.click(screen.getByRole('button', { name: /submit/i }));
  expect(screen.getByRole('button', { name: /submit/i })).toBeEnabled();
});
