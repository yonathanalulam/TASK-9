import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Import interfaces                                                  */
/* ------------------------------------------------------------------ */

export interface Import {
  id: string;
  filename: string;
  format: string;
  status: string;
  total_items: number;
  processed_items: number;
  duplicate_items: number;
  error_items: number;
  uploaded_by: string;
  created_at: string;
  updated_at: string;
}

export interface ImportItem {
  id: string;
  import_id: string;
  row_index: number;
  external_id: string | null;
  title: string;
  status: string;
  similarity_score: number | null;
  matched_content_id: string | null;
  error_message: string | null;
  raw_data: Record<string, unknown>;
  created_at: string;
}

export interface ImportListParams {
  page?: number;
  per_page?: number;
  status?: string;
}

export interface ImportItemListParams {
  page?: number;
  per_page?: number;
  status?: string;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function createImport(file: File): Promise<ApiResponse<Import>> {
  const formData = new FormData();
  formData.append('file', file);
  const res = await apiClient.post<ApiResponse<Import>>('/imports', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return res.data;
}

export async function listImports(
  params: ImportListParams = {},
): Promise<ApiResponse<Import[]>> {
  const res = await apiClient.get<ApiResponse<Import[]>>('/imports', { params });
  return res.data;
}

export async function getImport(id: string): Promise<ApiResponse<Import>> {
  const res = await apiClient.get<ApiResponse<Import>>(`/imports/${id}`);
  return res.data;
}

export async function getImportItems(
  id: string,
  params: ImportItemListParams = {},
): Promise<ApiResponse<ImportItem[]>> {
  const res = await apiClient.get<ApiResponse<ImportItem[]>>(
    `/imports/${id}/items`,
    { params },
  );
  return res.data;
}
