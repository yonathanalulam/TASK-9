import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { createElement } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { useAuth } from '../useAuth';
import { useAuthStore } from '@/stores/authStore';
import type { User, RoleAssignment } from '@/api/types';

// Track navigate calls
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock('@/api/auth', () => ({
  login: vi.fn(),
  logout: vi.fn(),
}));

import * as authApi from '@/api/auth';

const mockUser: User & { roles: RoleAssignment[] } = {
  id: 'user-1',
  username: 'admin',
  display_name: 'Admin User',
  status: 'active',
  last_login_at: null,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
  version: 1,
  roles: [
    {
      id: 'role-1',
      role: 'administrator',
      scope_type: 'global',
      scope_id: null,
    },
  ],
};

const wrapper = ({ children }: { children: React.ReactNode }) =>
  createElement(MemoryRouter, null, children);

describe('useAuth', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthStore.setState({
      token: null,
      user: null,
      roles: [],
      isAuthenticated: false,
    });
  });

  it('returns user and isAuthenticated from the store', () => {
    const { result } = renderHook(() => useAuth(), { wrapper });

    expect(result.current.user).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
  });

  it('reflects store state when auth is set', () => {
    useAuthStore.getState().setAuth('token-123', mockUser, mockUser.roles);

    const { result } = renderHook(() => useAuth(), { wrapper });

    expect(result.current.user).toEqual(mockUser);
    expect(result.current.isAuthenticated).toBe(true);
  });

  it('login calls the API and sets auth state', async () => {
    const loginResponse = {
      data: {
        token: 'jwt-token',
        user: mockUser,
      },
      meta: { request_id: 'req-1', timestamp: '2026-04-15T00:00:00Z' },
      error: null,
    };

    vi.mocked(authApi.login).mockResolvedValue(loginResponse);

    const { result } = renderHook(() => useAuth(), { wrapper });

    await act(async () => {
      await result.current.login('admin', 'password123');
    });

    expect(authApi.login).toHaveBeenCalledWith('admin', 'password123');
    expect(useAuthStore.getState().token).toBe('jwt-token');
    expect(useAuthStore.getState().user).toEqual(mockUser);
    expect(useAuthStore.getState().isAuthenticated).toBe(true);
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('logout clears state and navigates to /login', async () => {
    // Set up authenticated state first
    useAuthStore.getState().setAuth('token', mockUser, mockUser.roles);
    vi.mocked(authApi.logout).mockResolvedValue({
      data: null,
      meta: { request_id: 'req-2', timestamp: '2026-04-15T00:00:00Z' },
      error: null,
    });

    const { result } = renderHook(() => useAuth(), { wrapper });

    await act(async () => {
      await result.current.logout();
    });

    expect(authApi.logout).toHaveBeenCalled();
    expect(useAuthStore.getState().token).toBeNull();
    expect(useAuthStore.getState().user).toBeNull();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
    expect(mockNavigate).toHaveBeenCalledWith('/login');
  });

  it('logout clears state even if API call fails', async () => {
    useAuthStore.getState().setAuth('token', mockUser, mockUser.roles);
    vi.mocked(authApi.logout).mockRejectedValue(new Error('Network error'));

    const { result } = renderHook(() => useAuth(), { wrapper });

    await act(async () => {
      await result.current.logout();
    });

    // State should still be cleared
    expect(useAuthStore.getState().token).toBeNull();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
    expect(mockNavigate).toHaveBeenCalledWith('/login');
  });
});
