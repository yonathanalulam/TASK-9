import apiClient from './client';
import type { ApiResponse, SearchResult } from './types';

/* ------------------------------------------------------------------ */
/*  Search params                                                      */
/* ------------------------------------------------------------------ */

export interface SearchParams {
  q: string;
  type?: string;
  store?: string;
  region?: string;
  date_from?: string;
  date_to?: string;
  sort?: 'newest' | 'most_viewed' | 'highest_reply';
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  API function                                                       */
/* ------------------------------------------------------------------ */

export async function search(
  params: SearchParams,
  signal?: AbortSignal,
): Promise<ApiResponse<SearchResult[]>> {
  const res = await apiClient.get<ApiResponse<SearchResult[]>>('/search', {
    params,
    signal,
  });
  return res.data;
}
