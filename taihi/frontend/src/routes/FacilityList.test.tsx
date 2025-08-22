import { render, screen } from '@testing-library/react';
import FacilityList from './FacilityList';

test('renders loading state', () => {
  render(<FacilityList />);
  expect(screen.getByText(/Loading/)).toBeInTheDocument();
});
