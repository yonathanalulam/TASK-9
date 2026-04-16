import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import { replayMutations, listMutationLogs } from '../mutations';

describe('Mutations API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('replayMutations', () => {
    it('calls POST /mutations/replay with mutations array', async () => {
      const results = [{ mutation_id: 'm-1', status: 'applied', error_detail: null }];
      const envelope = { data: results, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const mutations = [
        { id: 'm-1', entity_type: 'content', operation: 'create', payload: { title: 'New' } },
      ];
      const result = await replayMutations(mutations);

      expect(mockPost).toHaveBeenCalledWith('/mutations/replay', { mutations });
      expect(result.data[0].status).toBe('applied');
    });
  });

  describe('listMutationLogs', () => {
    it('calls GET /mutations with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listMutationLogs();

      expect(mockGet).toHaveBeenCalledWith('/mutations', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listMutationLogs({ page: 3, per_page: 50 });

      expect(mockGet).toHaveBeenCalledWith('/mutations', {
        params: { page: 3, per_page: 50 },
      });
    });
  });
});
