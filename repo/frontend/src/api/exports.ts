import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Canonical export contract — matches backend ExportController       */
/*  serializeExportJob() and ExportService::VALID_DATASETS exactly.    */
/* ------------------------------------------------------------------ */

/** Backend-canonical dataset values (ExportService::VALID_DATASETS). */
export const EXPORT_DATASETS = ['content_items', 'audit_events'] as const;
export type ExportDataset = (typeof EXPORT_DATASETS)[number];

/** Backend-canonical format values (ExportService::VALID_FORMATS). CSV only. */
export const EXPORT_FORMATS = ['CSV'] as const;
export type ExportFormat = (typeof EXPORT_FORMATS)[number];

/** Display labels for datasets. */
export const DATASET_LABELS: Record<ExportDataset, string> = {
  content_items: 'Content Items',
  audit_events: 'Audit Events',
};

/**
 * Canonical export job response shape.
 * Matches backend serializeExportJob() — 14 fields exactly.
 */
export interface ExportRecord {
  id: string;
  dataset: string;
  format: string;
  status: string;
  requested_by: string;
  authorized_by: string | null;
  filters: Record<string, unknown>;
  file_name: string | null;
  watermark_text: string | null;
  tamper_hash_sha256: string | null;
  requested_at: string;
  authorized_at: string | null;
  completed_at: string | null;
  expires_at: string | null;
}

/** Export creation request payload. */
export interface ExportRequest {
  dataset: ExportDataset;
  format: ExportFormat;
  filters?: Record<string, unknown>;
}

export interface ExportListParams {
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function requestExport(
  data: ExportRequest,
): Promise<ApiResponse<ExportRecord>> {
  const res = await apiClient.post<ApiResponse<ExportRecord>>('/exports', data);
  return res.data;
}

export async function authorizeExport(
  id: string,
): Promise<ApiResponse<ExportRecord>> {
  const res = await apiClient.post<ApiResponse<ExportRecord>>(
    `/exports/${id}/authorize`,
  );
  return res.data;
}

export async function getExport(
  id: string,
): Promise<ApiResponse<ExportRecord>> {
  const res = await apiClient.get<ApiResponse<ExportRecord>>(`/exports/${id}`);
  return res.data;
}

export async function downloadExport(id: string): Promise<Blob> {
  const res = await apiClient.get<Blob>(`/exports/${id}/download`, {
    responseType: 'blob',
  });
  return res.data;
}

export async function listExports(
  params: ExportListParams = {},
): Promise<ApiResponse<ExportRecord[]>> {
  const res = await apiClient.get<ApiResponse<ExportRecord[]>>('/exports', {
    params,
  });
  return res.data;
}
