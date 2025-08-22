/******************************************************
 * useApi.ts – Axios + TanStack Query + Persistence
 *****************************************************/

import axios from 'axios';
import { QueryClient, QueryCache, useQuery, useMutation } from '@tanstack/react-query';
import { persistQueryClient } from '@tanstack/react-query-persist-client';
import { createSyncStoragePersister } from '@tanstack/query-sync-storage-persister';
import { useAuthStore } from '../store/useAuthStore';

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE ?? '/wp-json/roro/v1',
  timeout: 8000,
});

apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

export const queryClient = new QueryClient({
  queryCache: new QueryCache({
    onError: (error) => console.error('Query Error:', error),
  }),
});

const persister = createSyncStoragePersister({
  storage: window.indexedDB,
  encode: (data) => data,
  decode: (data) => data,
});

persistQueryClient({
  queryClient,
  persister,
  maxAge: 1000 * 60 * 60 * 24,
});

/** 近隣施設取得Hook。種別・カテゴリ・座標を指定して施設を取得します。 */
export const useFacilities = (lat: number, lng: number) =>
  useQuery({
    queryKey: ['facilities', lat, lng],
    queryFn: () =>
      apiClient
        .get('/facilities', {
          params: { species: 'both', category: 'cafe', lat, lng, radius: 3000 },
        })
        .then((r) => r.data.facilities),
    staleTime: 1000 * 60 * 5,
  });

/** レビュー投稿 */
export const useSubmitReview = () =>
  useMutation({
    mutationFn: (payload: { facility_id: number; rating: number; comment: string }) =>
      apiClient.post('/reviews', payload).then((r) => r.data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['reviews'] }),
  });
