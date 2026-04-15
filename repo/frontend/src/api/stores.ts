import apiClient from './client';
import type { ApiResponse, Store } from './types';

/* ------------------------------------------------------------------ */
/*  Query params                                                      */
/* ------------------------------------------------------------------ */

export interface StoreListParams {
  page?: number;
  per_page?: number;
  region_id?: string;
  type?: string;
  status?: string;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                     */
/* ------------------------------------------------------------------ */

export async function listStores(
  params: StoreListParams = {},
): Promise<ApiResponse<Store[]>> {
  const res = await apiClient.get<ApiResponse<Store[]>>('/stores', { params });
  return res.data;
}

export async function getStore(id: string): Promise<ApiResponse<Store>> {
  const res = await apiClient.get<ApiResponse<Store>>(`/stores/${id}`);
  return res.data;
}

export async function createStore(data: {
  code: string;
  name: string;
  store_type: string;
  region_id: string;
  timezone?: string;
  address_line_1?: string;
  address_line_2?: string;
  city?: string;
  postal_code?: string;
  latitude?: number;
  longitude?: number;
}): Promise<ApiResponse<Store>> {
  const res = await apiClient.post<ApiResponse<Store>>('/stores', data);
  return res.data;
}

export async function updateStore(
  id: string,
  data: Partial<{
    code: string;
    name: string;
    store_type: string;
    region_id: string;
    timezone: string;
    status: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    postal_code: string;
    latitude: number;
    longitude: number;
  }>,
  version: number,
): Promise<ApiResponse<Store>> {
  const res = await apiClient.put<ApiResponse<Store>>(`/stores/${id}`, data, {
    headers: { 'If-Match': String(version) },
  });
  return res.data;
}

export async function getStoreVersions(
  id: string,
): Promise<ApiResponse<unknown[]>> {
  const res = await apiClient.get<ApiResponse<unknown[]>>(`/stores/${id}/versions`);
  return res.data;
}
