import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Mutation interfaces                                                */
/* ------------------------------------------------------------------ */

export interface MutationLog {
  id: string;
  mutation_id: string;
  entity_type: string;
  entity_id: string | null;
  operation: string;
  status: string;
  received_at: string;
  processed_at: string | null;
  error_detail: string | null;
}

export interface MutationLogListParams {
  page?: number;
  per_page?: number;
}

export interface QueuedMutation {
  id: string;
  entity_type: string;
  entity_id?: string;
  operation: string;
  payload: Record<string, unknown>;
}

export interface MutationResult {
  mutation_id: string;
  status: string;
  error_detail?: string | null;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function replayMutations(
  mutations: QueuedMutation[],
): Promise<ApiResponse<MutationResult[]>> {
  const res = await apiClient.post<ApiResponse<MutationResult[]>>(
    '/mutations/replay',
    { mutations },
  );
  return res.data;
}

export async function listMutationLogs(
  params: MutationLogListParams = {},
): Promise<ApiResponse<MutationLog[]>> {
  const res = await apiClient.get<ApiResponse<MutationLog[]>>('/mutations', { params });
  return res.data;
}
