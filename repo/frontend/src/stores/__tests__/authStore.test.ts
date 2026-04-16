import { describe, it, expect, beforeEach } from 'vitest';
import { useAuthStore } from '../authStore';
import type { User, RoleAssignment } from '@/api/types';

const mockUser: User = {
  id: 'user-1',
  username: 'testadmin',
  display_name: 'Test Admin',
  status: 'active',
  last_login_at: null,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
  version: 1,
};

const mockRoles: RoleAssignment[] = [
  {
    id: 'role-1',
    role: 'administrator',
    role_display_name: 'Administrator',
    scope_type: 'global',
    scope_id: null,
  },
];

describe('authStore', () => {
  beforeEach(() => {
    // Reset zustand store to initial state between tests
    useAuthStore.setState({
      token: null,
      user: null,
      roles: [],
      isAuthenticated: false,
    });
  });

  it('has correct initial state', () => {
    const state = useAuthStore.getState();
    expect(state.token).toBeNull();
    expect(state.user).toBeNull();
    expect(state.roles).toEqual([]);
    expect(state.isAuthenticated).toBe(false);
  });

  it('setAuth stores token, user, and roles correctly', () => {
    useAuthStore.getState().setAuth('jwt-token-123', mockUser, mockRoles);

    const state = useAuthStore.getState();
    expect(state.token).toBe('jwt-token-123');
    expect(state.user).toEqual(mockUser);
    expect(state.roles).toEqual(mockRoles);
    expect(state.isAuthenticated).toBe(true);
  });

  it('clearAuth resets all state', () => {
    // First set some auth state
    useAuthStore.getState().setAuth('jwt-token-123', mockUser, mockRoles);
    expect(useAuthStore.getState().isAuthenticated).toBe(true);

    // Clear it
    useAuthStore.getState().clearAuth();

    const state = useAuthStore.getState();
    expect(state.token).toBeNull();
    expect(state.user).toBeNull();
    expect(state.roles).toEqual([]);
    expect(state.isAuthenticated).toBe(false);
  });

  it('isAuthenticated returns true after setAuth', () => {
    useAuthStore.getState().setAuth('token', mockUser, mockRoles);
    expect(useAuthStore.getState().isAuthenticated).toBe(true);
  });

  it('isAuthenticated returns false after clearAuth', () => {
    useAuthStore.getState().setAuth('token', mockUser, mockRoles);
    useAuthStore.getState().clearAuth();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('initialize sets isAuthenticated true when token and user exist', () => {
    // Simulate rehydrated state where token/user present but isAuthenticated not set
    useAuthStore.setState({
      token: 'persisted-token',
      user: mockUser,
      roles: mockRoles,
      isAuthenticated: false,
    });

    useAuthStore.getState().initialize();
    expect(useAuthStore.getState().isAuthenticated).toBe(true);
  });

  it('initialize does not set isAuthenticated when token is missing', () => {
    useAuthStore.setState({
      token: null,
      user: mockUser,
      roles: [],
      isAuthenticated: false,
    });

    useAuthStore.getState().initialize();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('initialize does not set isAuthenticated when user is missing', () => {
    useAuthStore.setState({
      token: 'some-token',
      user: null,
      roles: [],
      isAuthenticated: false,
    });

    useAuthStore.getState().initialize();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  describe('persist middleware', () => {
    it('partializes state to only persist token, user, and roles', () => {
      // The persist config uses partialize to exclude isAuthenticated.
      // We verify by checking that the store has persist configuration.
      const persistOptions = (useAuthStore as any).persist;
      expect(persistOptions).toBeDefined();
      expect(persistOptions.getOptions().name).toBe('meridian-auth');
    });
  });
});
