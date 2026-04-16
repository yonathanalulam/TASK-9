import { vi, describe, it, expect, beforeEach } from 'vitest';

/* ------------------------------------------------------------------ */
/*  Mock the API client                                                */
/* ------------------------------------------------------------------ */
const { mockGet, mockPost, mockPut, mockPatch } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
  mockPatch: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost, put: mockPut, patch: mockPatch },
}));

import {
  listUsers,
  getUser,
  createUser,
  updateUser,
  deactivateUser,
} from '../users';

describe('Users API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const sampleUser = {
    id: 'user-1',
    username: 'alice',
    display_name: 'Alice',
    status: 'active',
    version: 1,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  };

  describe('listUsers', () => {
    it('calls GET /users with default pagination', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listUsers();

      expect(mockGet).toHaveBeenCalledWith('/users', {
        params: { page: 1, per_page: 20 },
      });
      expect(result.data).toEqual([]);
    });

    it('forwards custom page and per_page', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listUsers(3, 50);

      expect(mockGet).toHaveBeenCalledWith('/users', {
        params: { page: 3, per_page: 50 },
      });
    });
  });

  describe('getUser', () => {
    it('calls GET /users/:id', async () => {
      const envelope = { data: sampleUser, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getUser('user-1');

      expect(mockGet).toHaveBeenCalledWith('/users/user-1');
      expect(result.data.username).toBe('alice');
    });
  });

  describe('createUser', () => {
    it('calls POST /users with payload', async () => {
      const envelope = { data: sampleUser, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { username: 'alice', display_name: 'Alice', password: 'secret123' };
      const result = await createUser(payload);

      expect(mockPost).toHaveBeenCalledWith('/users', payload);
      expect(result.data.id).toBe('user-1');
    });
  });

  describe('updateUser', () => {
    it('calls PUT /users/:id with If-Match header', async () => {
      const updated = { ...sampleUser, display_name: 'Alice B.', version: 2 };
      const envelope = { data: updated, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const result = await updateUser('user-1', { display_name: 'Alice B.' }, 1);

      expect(mockPut).toHaveBeenCalledWith(
        '/users/user-1',
        { display_name: 'Alice B.' },
        { headers: { 'If-Match': '1' } },
      );
      expect(result.data.display_name).toBe('Alice B.');
    });
  });

  describe('deactivateUser', () => {
    it('calls PATCH /users/:id/deactivate', async () => {
      const deactivated = { ...sampleUser, status: 'inactive' };
      const envelope = { data: deactivated, meta: {}, error: null };
      mockPatch.mockResolvedValueOnce({ data: envelope });

      const result = await deactivateUser('user-1');

      expect(mockPatch).toHaveBeenCalledWith('/users/user-1/deactivate');
      expect(result.data.status).toBe('inactive');
    });
  });
});
