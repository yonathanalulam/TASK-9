import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Dedup review interfaces                                            */
/* ------------------------------------------------------------------ */

export interface DedupReviewItem {
  id: string;
  import_item: {
    id: string;
    title: string;
    body: string;
    raw_data: Record<string, unknown>;
  };
  existing_content: {
    id: string;
    title: string;
    body: string;
    content_type: string;
    status: string;
  };
  similarity_score: number;
  status: string;
  reviewed_by: string | null;
  reviewed_at: string | null;
  created_at: string;
}

export interface DedupReviewParams {
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function getReviewQueue(
  params: DedupReviewParams = {},
): Promise<ApiResponse<DedupReviewItem[]>> {
  const res = await apiClient.get<ApiResponse<DedupReviewItem[]>>(
    '/dedup/review',
    { params },
  );
  return res.data;
}

export async function mergeItem(id: string): Promise<ApiResponse<unknown>> {
  const res = await apiClient.post<ApiResponse<unknown>>(`/dedup/review/${id}/merge`);
  return res.data;
}

export async function rejectItem(id: string): Promise<ApiResponse<DedupReviewItem>> {
  const res = await apiClient.post<ApiResponse<DedupReviewItem>>(
    `/dedup/review/${id}/reject`,
  );
  return res.data;
}

export async function unmergeItem(id: string): Promise<ApiResponse<unknown>> {
  const res = await apiClient.post<ApiResponse<unknown>>(`/dedup/unmerge/${id}`);
  return res.data;
}
