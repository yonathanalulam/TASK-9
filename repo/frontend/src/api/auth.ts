import apiClient from './client';
import type { ApiResponse, LoginResponse, User, RoleAssignment } from './types';

export async function login(
  username: string,
  password: string,
): Promise<ApiResponse<LoginResponse>> {
  const res = await apiClient.post<ApiResponse<LoginResponse>>('/auth/login', {
    username,
    password,
  });
  return res.data;
}

export async function logout(): Promise<ApiResponse<null>> {
  const res = await apiClient.post<ApiResponse<null>>('/auth/logout');
  return res.data;
}

export async function getMe(): Promise<ApiResponse<User & { roles: RoleAssignment[] }>> {
  const res = await apiClient.get<ApiResponse<User & { roles: RoleAssignment[] }>>('/auth/me');
  return res.data;
}

export async function changePassword(
  current_password: string,
  new_password: string,
): Promise<ApiResponse<null>> {
  const res = await apiClient.post<ApiResponse<null>>('/auth/change-password', {
    current_password,
    new_password,
  });
  return res.data;
}
