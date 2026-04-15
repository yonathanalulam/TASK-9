import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Classification interfaces                                          */
/* ------------------------------------------------------------------ */

export interface DataClassification {
  id: string;
  entity_type: string;
  entity_id: string;
  entity_name: string;
  classification: string;
  justification: string | null;
  classified_by: string;
  created_at: string;
  updated_at: string;
}

export interface ClassificationListParams {
  page?: number;
  per_page?: number;
  entity_type?: string;
  classification?: string;
}

export interface CreateClassificationData {
  entity_type: string;
  entity_id: string;
  classification: string;
  justification?: string;
}

/* ------------------------------------------------------------------ */
/*  Consent interfaces                                                 */
/* ------------------------------------------------------------------ */

export interface ConsentRecord {
  id: string;
  user_id: string;
  user_name: string;
  purpose: string;
  status: string;
  granted_at: string;
  revoked_at: string | null;
  expires_at: string | null;
  created_at: string;
}

export interface CreateConsentData {
  user_id: string;
  purpose: string;
  expires_at?: string;
}

/* ------------------------------------------------------------------ */
/*  Retention interfaces                                               */
/* ------------------------------------------------------------------ */

export interface RetentionCase {
  id: string;
  entity_type: string;
  entity_id: string;
  entity_name: string;
  reason: string;
  status: string;
  scheduled_for: string | null;
  executed_at: string | null;
  executed_by: string | null;
  created_by: string;
  created_at: string;
  updated_at: string;
}

export interface RetentionListParams {
  page?: number;
  per_page?: number;
  status?: string;
}

export interface RetentionStats {
  total_cases: number;
  pending_count: number;
  scheduled_count: number;
  executing_count: number;
  completed_count: number;
  failed_count: number;
  next_scheduled_deletion: string | null;
}

/* ------------------------------------------------------------------ */
/*  API functions — Classifications                                    */
/* ------------------------------------------------------------------ */

export async function listClassifications(
  params: ClassificationListParams = {},
): Promise<ApiResponse<DataClassification[]>> {
  const res = await apiClient.get<ApiResponse<DataClassification[]>>(
    '/classifications',
    { params },
  );
  return res.data;
}

export async function createClassification(
  data: CreateClassificationData,
): Promise<ApiResponse<DataClassification>> {
  const res = await apiClient.post<ApiResponse<DataClassification>>(
    '/classifications',
    data,
  );
  return res.data;
}

export async function updateClassification(
  id: string,
  data: Partial<CreateClassificationData>,
): Promise<ApiResponse<DataClassification>> {
  const res = await apiClient.put<ApiResponse<DataClassification>>(
    `/classifications/${id}`,
    data,
  );
  return res.data;
}

/* ------------------------------------------------------------------ */
/*  API functions — Consent                                            */
/* ------------------------------------------------------------------ */

export async function createConsent(
  data: CreateConsentData,
): Promise<ApiResponse<ConsentRecord>> {
  const res = await apiClient.post<ApiResponse<ConsentRecord>>('/consent', data);
  return res.data;
}

export async function getUserConsent(
  userId: string,
): Promise<ApiResponse<ConsentRecord[]>> {
  const res = await apiClient.get<ApiResponse<ConsentRecord[]>>(
    `/consent/user/${userId}`,
  );
  return res.data;
}

/* ------------------------------------------------------------------ */
/*  API functions — Retention                                          */
/* ------------------------------------------------------------------ */

export async function listRetentionCases(
  params: RetentionListParams = {},
): Promise<ApiResponse<RetentionCase[]>> {
  const res = await apiClient.get<ApiResponse<RetentionCase[]>>(
    '/retention/cases',
    { params },
  );
  return res.data;
}

export async function scheduleRetention(
  id: string,
): Promise<ApiResponse<RetentionCase>> {
  const res = await apiClient.post<ApiResponse<RetentionCase>>(
    `/retention/cases/${id}/schedule`,
  );
  return res.data;
}

export async function getRetentionStats(): Promise<ApiResponse<RetentionStats>> {
  const res = await apiClient.get<ApiResponse<RetentionStats>>('/retention/stats');
  return res.data;
}
