import apiClient from './client';
import type { ApiResponse, User } from './types';

export async function listUsers(
  page: number = 1,
  per_page: number = 20,
): Promise<ApiResponse<User[]>> {
  const res = await apiClient.get<ApiResponse<User[]>>('/users', {
    params: { page, per_page },
  });
  return res.data;
}

export async function getUser(id: string): Promise<ApiResponse<User>> {
  const res = await apiClient.get<ApiResponse<User>>(`/users/${id}`);
  return res.data;
}

export async function createUser(data: {
  username: string;
  display_name: string;
  password: string;
}): Promise<ApiResponse<User>> {
  const res = await apiClient.post<ApiResponse<User>>('/users', data);
  return res.data;
}

export async function updateUser(
  id: string,
  data: Partial<{
    display_name: string;
    status: string;
  }>,
  version: number,
): Promise<ApiResponse<User>> {
  const res = await apiClient.put<ApiResponse<User>>(`/users/${id}`, data, {
    headers: { 'If-Match': String(version) },
  });
  return res.data;
}

export async function deactivateUser(id: string): Promise<ApiResponse<User>> {
  const res = await apiClient.patch<ApiResponse<User>>(`/users/${id}/deactivate`);
  return res.data;
}
