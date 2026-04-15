import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Source interfaces                                                  */
/* ------------------------------------------------------------------ */

export interface ScrapingSource {
  id: string;
  name: string;
  base_url: string;
  type: string;
  status: string;
  rate_limit: number;
  schedule: string | null;
  config: Record<string, unknown>;
  last_scrape_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateSourceRequest {
  name: string;
  base_url: string;
  type: string;
  rate_limit?: number;
  schedule?: string;
  config?: Record<string, unknown>;
}

export interface UpdateSourceRequest {
  name?: string;
  base_url?: string;
  type?: string;
  rate_limit?: number;
  schedule?: string;
  config?: Record<string, unknown>;
}

/* ------------------------------------------------------------------ */
/*  Health interfaces                                                  */
/* ------------------------------------------------------------------ */

export interface HealthEvent {
  id: string;
  source_id: string;
  type: string;
  message: string;
  detail: string | null;
  created_at: string;
}

export interface SourceHealth {
  source_id: string;
  source_name: string;
  status: string;
  uptime: number;
  avg_response_ms: number;
  error_rate: number;
  recent_events: HealthEvent[];
}

export interface HealthDashboardSummary {
  active: number;
  degraded: number;
  paused: number;
  disabled: number;
  sources: SourceHealth[];
  recent_events: HealthEvent[];
}

/* ------------------------------------------------------------------ */
/*  Scrape run interfaces                                              */
/* ------------------------------------------------------------------ */

export interface ScrapeRun {
  id: string;
  source_id: string;
  source_name: string;
  status: string;
  items_found: number;
  items_new: number;
  items_updated: number;
  items_failed: number;
  started_at: string;
  completed_at: string | null;
  duration_ms: number | null;
  error: string | null;
  created_at: string;
}

export interface SourceListParams {
  page?: number;
  per_page?: number;
}

export interface ScrapeRunListParams {
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  Source CRUD                                                         */
/* ------------------------------------------------------------------ */

export async function listSources(
  params: SourceListParams = {},
): Promise<ApiResponse<ScrapingSource[]>> {
  const res = await apiClient.get<ApiResponse<ScrapingSource[]>>('/sources', { params });
  return res.data;
}

export async function getSource(id: string): Promise<ApiResponse<ScrapingSource>> {
  const res = await apiClient.get<ApiResponse<ScrapingSource>>(`/sources/${id}`);
  return res.data;
}

export async function createSource(
  data: CreateSourceRequest,
): Promise<ApiResponse<ScrapingSource>> {
  const res = await apiClient.post<ApiResponse<ScrapingSource>>('/sources', data);
  return res.data;
}

export async function updateSource(
  id: string,
  data: UpdateSourceRequest,
): Promise<ApiResponse<ScrapingSource>> {
  const res = await apiClient.put<ApiResponse<ScrapingSource>>(`/sources/${id}`, data);
  return res.data;
}

/* ------------------------------------------------------------------ */
/*  Source actions                                                      */
/* ------------------------------------------------------------------ */

export async function pauseSource(id: string): Promise<ApiResponse<ScrapingSource>> {
  const res = await apiClient.post<ApiResponse<ScrapingSource>>(
    `/sources/${id}/pause`,
  );
  return res.data;
}

export async function resumeSource(id: string): Promise<ApiResponse<ScrapingSource>> {
  const res = await apiClient.post<ApiResponse<ScrapingSource>>(
    `/sources/${id}/resume`,
  );
  return res.data;
}

export async function disableSource(id: string): Promise<ApiResponse<ScrapingSource>> {
  const res = await apiClient.post<ApiResponse<ScrapingSource>>(
    `/sources/${id}/disable`,
  );
  return res.data;
}

/* ------------------------------------------------------------------ */
/*  Health                                                             */
/* ------------------------------------------------------------------ */

export async function getSourceHealth(id: string): Promise<ApiResponse<SourceHealth>> {
  const res = await apiClient.get<ApiResponse<SourceHealth>>(
    `/sources/${id}/health`,
  );
  return res.data;
}

export async function getHealthDashboard(): Promise<ApiResponse<HealthDashboardSummary>> {
  const res = await apiClient.get<ApiResponse<HealthDashboardSummary>>(
    '/sources/health/dashboard',
  );
  return res.data;
}

/* ------------------------------------------------------------------ */
/*  Scrape runs                                                        */
/* ------------------------------------------------------------------ */

export async function listScrapeRuns(
  params: ScrapeRunListParams = {},
): Promise<ApiResponse<ScrapeRun[]>> {
  const res = await apiClient.get<ApiResponse<ScrapeRun[]>>('/scrape-runs', { params });
  return res.data;
}

export async function getScrapeRun(id: string): Promise<ApiResponse<ScrapeRun>> {
  const res = await apiClient.get<ApiResponse<ScrapeRun>>(`/scrape-runs/${id}`);
  return res.data;
}

export async function triggerScrape(
  sourceId: string,
): Promise<ApiResponse<ScrapeRun>> {
  const res = await apiClient.post<ApiResponse<ScrapeRun>>(
    `/scrape-runs/trigger/${sourceId}`,
  );
  return res.data;
}
