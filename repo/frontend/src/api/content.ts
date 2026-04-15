import apiClient from './client';
import type { ApiResponse, ContentItem, ContentVersion } from './types';

/* ------------------------------------------------------------------ */
/*  Content list params                                                */
/* ------------------------------------------------------------------ */

export interface ContentListParams {
  page?: number;
  per_page?: number;
  content_type?: string;
  store_id?: string;
  region_id?: string;
  status?: string;
}

/* ------------------------------------------------------------------ */
/*  Version diff                                                       */
/* ------------------------------------------------------------------ */

export interface DiffChange {
  field: string;
  before: string | string[];
  after: string | string[];
}

export interface VersionDiff {
  v1: { id: string; version_number: number };
  v2: { id: string; version_number: number };
  changes: DiffChange[];
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function listContent(
  params: ContentListParams = {},
): Promise<ApiResponse<ContentItem[]>> {
  const res = await apiClient.get<ApiResponse<ContentItem[]>>('/content', { params });
  return res.data;
}

export async function getContent(id: string): Promise<ApiResponse<ContentItem>> {
  const res = await apiClient.get<ApiResponse<ContentItem>>(`/content/${id}`);
  return res.data;
}

export async function createContent(data: {
  title: string;
  body: string;
  content_type: string;
  author_name: string;
  tags?: string[];
  store_id?: string | null;
  region_id?: string | null;
}): Promise<ApiResponse<ContentItem>> {
  const res = await apiClient.post<ApiResponse<ContentItem>>('/content', data);
  return res.data;
}

export async function updateContent(
  id: string,
  data: Partial<{
    title: string;
    body: string;
    content_type: string;
    author_name: string;
    tags: string[];
    store_id: string | null;
    region_id: string | null;
    change_reason: string;
  }>,
  version: number,
): Promise<ApiResponse<ContentItem>> {
  const res = await apiClient.put<ApiResponse<ContentItem>>(`/content/${id}`, data, {
    headers: { 'If-Match': String(version) },
  });
  return res.data;
}

export async function publishContent(id: string): Promise<ApiResponse<ContentItem>> {
  const res = await apiClient.post<ApiResponse<ContentItem>>(`/content/${id}/publish`);
  return res.data;
}

export async function archiveContent(id: string): Promise<ApiResponse<ContentItem>> {
  const res = await apiClient.post<ApiResponse<ContentItem>>(`/content/${id}/archive`);
  return res.data;
}

export async function getContentVersions(
  contentId: string,
): Promise<ApiResponse<ContentVersion[]>> {
  const res = await apiClient.get<ApiResponse<ContentVersion[]>>(
    `/content/${contentId}/versions`,
  );
  return res.data;
}

export async function getContentVersion(
  contentId: string,
  versionId: string,
): Promise<ApiResponse<ContentVersion>> {
  const res = await apiClient.get<ApiResponse<ContentVersion>>(
    `/content/${contentId}/versions/${versionId}`,
  );
  return res.data;
}

export async function diffVersions(
  contentId: string,
  v1Id: string,
  v2Id: string,
): Promise<ApiResponse<VersionDiff>> {
  const res = await apiClient.get<ApiResponse<VersionDiff>>(
    `/content/${contentId}/versions/${v1Id}/diff/${v2Id}`,
  );
  return res.data;
}

export async function rollbackContent(
  contentId: string,
  target_version_id: string,
  reason: string,
): Promise<ApiResponse<ContentItem>> {
  const res = await apiClient.post<ApiResponse<ContentItem>>(
    `/content/${contentId}/rollback`,
    { target_version_id, reason },
  );
  return res.data;
}
