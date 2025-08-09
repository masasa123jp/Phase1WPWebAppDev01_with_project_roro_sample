import { render, screen } from '@testing-library/react';
import FacilitySearchPage from '../src/pages/FacilitySearchPage';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { vi } from 'vitest';

vi.mock('../src/hooks/useApi', () => ({
  useFacilities: () => ({ data: [{ id: 1, name: 'Cafe', lat: 0, lng: 0, distance: 10 }], isLoading: false }),
}));

test('renders facility list', async () => {
  render(
    <QueryClientProvider client={new QueryClient()}>
      <FacilitySearchPage />
    </QueryClientProvider>
  );
  expect(await screen.findByRole('link', { name: /cafe/i })).toBeInTheDocument();
});
