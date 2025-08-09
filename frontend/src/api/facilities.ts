/**
 * 施設検索APIヘルパー。
 * species・category・位置情報を指定して施設を取得します。
 * 緯度・経度が与えられなければ距離計算は行われません。
 */
import { apiClient } from './client';

export interface Facility {
  id: number;
  name: string;
  category: string;
  lat: string | number;
  lng: string | number;
  distance: number;
  avg_rating?: number;
}

export async function searchFacilities(
  species: string,
  category: string,
  lat?: number,
  lng?: number,
  radius = 3000,
  limit = 20
): Promise<Facility[]> {
  const params: Record<string, any> = { species, category, radius, limit };
  if (lat != null && lng != null) {
    params.lat = lat;
    params.lng = lng;
  }
  const { data } = await apiClient.get('/facilities', { params });
  return data.facilities ?? [];
}
