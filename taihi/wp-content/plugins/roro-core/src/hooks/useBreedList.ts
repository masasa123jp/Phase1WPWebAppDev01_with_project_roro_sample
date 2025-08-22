/**
 * 犬種マスター (CPT `dog_breed`) を取得し、オートコンプリート等で利用。
 * fetch 後は React Query のキャッシュに保持する。
 */
import { useQuery } from '@tanstack/react-query';
import { api } from '@/services/apiClient';

export interface DogBreed { id: number; name: string; size: string; origin?: string }

export function useBreedList() {
  return useQuery<DogBreed[]>(['breed-list'], () => api('/roro/v1/breeds'));
}
