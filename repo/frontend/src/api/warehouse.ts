import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Warehouse interfaces                                               */
/* ------------------------------------------------------------------ */

export interface LoadRun {
  id: string;
  load_type: string;
  status: string;
  rows_extracted: number;
  rows_loaded: number;
  rows_rejected: number;
  rejected_details: RejectedRow[];
  started_at: string;
  completed_at: string | null;
  duration_ms: number | null;
  triggered_by: string;
  error: string | null;
  created_at: string;
  updated_at: string;
}

export interface RejectedRow {
  row_index: number;
  reason: string;
  data: Record<string, unknown>;
}

export interface LoadRunListParams {
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function listLoadRuns(
  params: LoadRunListParams = {},
): Promise<ApiResponse<LoadRun[]>> {
  const res = await apiClient.get<ApiResponse<LoadRun[]>>('/warehouse/loads', {
    params,
  });
  return res.data;
}

export async function getLoadRun(id: string): Promise<ApiResponse<LoadRun>> {
  const res = await apiClient.get<ApiResponse<LoadRun>>(`/warehouse/loads/${id}`);
  return res.data;
}

export async function triggerLoad(): Promise<ApiResponse<LoadRun>> {
  const res = await apiClient.post<ApiResponse<LoadRun>>('/warehouse/loads/trigger');
  return res.data;
}
