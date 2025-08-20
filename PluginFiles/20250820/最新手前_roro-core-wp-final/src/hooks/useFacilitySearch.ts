import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/apiClient';

export function useFacilitySearch(zipcode: string) {
  return useQuery(['facilities', zipcode], () =>
    api(`/roro/v1/facilities?zipcode=${zipcode}`)
  );
}
