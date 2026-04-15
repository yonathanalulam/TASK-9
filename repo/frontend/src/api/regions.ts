import apiClient from './client';
import type { ApiResponse, Region } from './types';

/* ------------------------------------------------------------------ */
/*  Query params                                                      */
/* ------------------------------------------------------------------ */

export interface RegionListParams {
  page?: number;
  per_page?: number;
  active_only?: boolean;
  parent_id?: string;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                     */
/* ------------------------------------------------------------------ */

export async function listRegions(
  params: RegionListParams = {},
): Promise<ApiResponse<Region[]>> {
  const res = await apiClient.get<ApiResponse<Region[]>>('/regions', { params });
  return res.data;
}

export async function getRegion(id: string): Promise<ApiResponse<Region>> {
  const res = await apiClient.get<ApiResponse<Region>>(`/regions/${id}`);
  return res.data;
}

export async function createRegion(data: {
  code: string;
  name: string;
  parent_id?: string | null;
  effective_from: string;
  effective_until?: string | null;
}): Promise<ApiResponse<Region>> {
  const res = await apiClient.post<ApiResponse<Region>>('/regions', data);
  return res.data;
}

export async function updateRegion(
  id: string,
  data: Partial<{
    code: string;
    name: string;
    parent_id: string | null;
    effective_from: string;
    effective_until: string | null;
  }>,
  version: number,
): Promise<ApiResponse<Region>> {
  const res = await apiClient.put<ApiResponse<Region>>(`/regions/${id}`, data, {
    headers: { 'If-Match': String(version) },
  });
  return res.data;
}

export async function closeRegion(
  id: string,
  data: {
    child_reassignments: Record<string, string>;
    reason: string;
  },
): Promise<ApiResponse<Region>> {
  const res = await apiClient.post<ApiResponse<Region>>(`/regions/${id}/close`, data);
  return res.data;
}

export async function getRegionVersions(
  id: string,
): Promise<ApiResponse<unknown[]>> {
  const res = await apiClient.get<ApiResponse<unknown[]>>(`/regions/${id}/versions`);
  return res.data;
}
