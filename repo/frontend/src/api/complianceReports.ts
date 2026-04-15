import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Types — matches backend ComplianceReportController::serializeReport */
/* ------------------------------------------------------------------ */

/** Backend-canonical report type enum values. */
export const REPORT_TYPES = [
  'RETENTION_SUMMARY',
  'CONSENT_AUDIT',
  'DATA_CLASSIFICATION',
  'EXPORT_LOG',
  'ACCESS_AUDIT',
] as const;

export type ReportType = (typeof REPORT_TYPES)[number];

/** Matches serializeReport() output exactly. */
export interface ComplianceReport {
  id: string;
  report_type: string;
  generated_by: string;
  parameters: Record<string, unknown>;
  download_url: string;
  tamper_hash_sha256: string;
  previous_report_id: string | null;
  previous_report_hash: string | null;
  generated_at: string;
}

/** Hash verification block returned by the show endpoint. */
export interface HashVerification {
  tamper_hash_sha256: string;
  previous_report_id: string | null;
  previous_report_hash: string | null;
  chain_intact: boolean;
  file_exists?: boolean;
}

export interface ComplianceReportDetail extends ComplianceReport {
  hash_verification: HashVerification;
}

/* ------------------------------------------------------------------ */
/*  Request types                                                      */
/* ------------------------------------------------------------------ */

export interface GenerateReportData {
  report_type: ReportType;
  parameters?: Record<string, unknown>;
}

export interface ReportListParams {
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function generateReport(
  data: GenerateReportData,
): Promise<ApiResponse<ComplianceReport>> {
  const res = await apiClient.post<ApiResponse<ComplianceReport>>(
    '/compliance-reports',
    data,
  );
  return res.data;
}

export async function listReports(
  params: ReportListParams = {},
): Promise<ApiResponse<ComplianceReport[]>> {
  const res = await apiClient.get<ApiResponse<ComplianceReport[]>>(
    '/compliance-reports',
    { params },
  );
  return res.data;
}

export async function getReport(
  id: string,
): Promise<ApiResponse<ComplianceReportDetail>> {
  const res = await apiClient.get<ApiResponse<ComplianceReportDetail>>(
    `/compliance-reports/${id}`,
  );
  return res.data;
}

export async function downloadReport(id: string): Promise<Blob> {
  const res = await apiClient.get(`/compliance-reports/${id}/download`, {
    responseType: 'blob',
  });
  return res.data as Blob;
}
