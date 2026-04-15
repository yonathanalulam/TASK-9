import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Boundary interfaces                                                */
/* ------------------------------------------------------------------ */

export interface BoundaryImport {
  id: string;
  filename: string;
  type: string;
  size: number;
  hash: string;
  status: string;
  uploaded_by: string;
  area_count: number | null;
  errors: string[] | null;
  created_at: string;
  updated_at: string;
}

export interface BoundaryListParams {
  page?: number;
  per_page?: number;
  status?: string;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function uploadBoundary(file: File): Promise<ApiResponse<BoundaryImport>> {
  const formData = new FormData();
  formData.append('file', file);

  const res = await apiClient.post<ApiResponse<BoundaryImport>>(
    '/boundaries/upload',
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  );
  return res.data;
}

export async function listBoundaries(
  params: BoundaryListParams = {},
): Promise<ApiResponse<BoundaryImport[]>> {
  const res = await apiClient.get<ApiResponse<BoundaryImport[]>>('/boundaries', {
    params,
  });
  return res.data;
}

export async function getBoundary(id: string): Promise<ApiResponse<BoundaryImport>> {
  const res = await apiClient.get<ApiResponse<BoundaryImport>>(`/boundaries/${id}`);
  return res.data;
}

export async function validateBoundary(
  id: string,
): Promise<ApiResponse<BoundaryImport>> {
  const res = await apiClient.post<ApiResponse<BoundaryImport>>(
    `/boundaries/${id}/validate`,
  );
  return res.data;
}

export async function applyBoundary(
  id: string,
): Promise<ApiResponse<BoundaryImport>> {
  const res = await apiClient.post<ApiResponse<BoundaryImport>>(
    `/boundaries/${id}/apply`,
  );
  return res.data;
}
