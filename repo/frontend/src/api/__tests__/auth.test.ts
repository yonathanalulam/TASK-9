import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import { login, logout, getMe, changePassword } from '../auth';

describe('Auth API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('login', () => {
    it('calls POST /auth/login with credentials', async () => {
      const loginResp = { token: 'jwt-token', user: { id: 'u-1', username: 'alice' } };
      const envelope = { data: loginResp, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await login('alice', 'secret');

      expect(mockPost).toHaveBeenCalledWith('/auth/login', {
        username: 'alice',
        password: 'secret',
      });
      expect(result.data.token).toBe('jwt-token');
    });
  });

  describe('logout', () => {
    it('calls POST /auth/logout', async () => {
      const envelope = { data: null, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await logout();

      expect(mockPost).toHaveBeenCalledWith('/auth/logout');
      expect(result.data).toBeNull();
    });
  });

  describe('getMe', () => {
    it('calls GET /auth/me', async () => {
      const user = { id: 'u-1', username: 'alice', roles: [] };
      const envelope = { data: user, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getMe();

      expect(mockGet).toHaveBeenCalledWith('/auth/me');
      expect(result.data.username).toBe('alice');
    });
  });

  describe('changePassword', () => {
    it('calls POST /auth/change-password with current and new passwords', async () => {
      const envelope = { data: null, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await changePassword('oldpass', 'newpass');

      expect(mockPost).toHaveBeenCalledWith('/auth/change-password', {
        current_password: 'oldpass',
        new_password: 'newpass',
      });
      expect(result.data).toBeNull();
    });
  });
});
