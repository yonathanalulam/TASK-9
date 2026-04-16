import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import { getReviewQueue, mergeItem, rejectItem, unmergeItem } from '../dedup';

describe('Dedup API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('getReviewQueue', () => {
    it('calls GET /dedup/review with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getReviewQueue();

      expect(mockGet).toHaveBeenCalledWith('/dedup/review', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await getReviewQueue({ page: 2, per_page: 10 });

      expect(mockGet).toHaveBeenCalledWith('/dedup/review', {
        params: { page: 2, per_page: 10 },
      });
    });
  });

  describe('mergeItem', () => {
    it('calls POST /dedup/review/:id/merge', async () => {
      const envelope = { data: { status: 'merged' }, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await mergeItem('item-1');

      expect(mockPost).toHaveBeenCalledWith('/dedup/review/item-1/merge');
      expect(result.data.status).toBe('merged');
    });
  });

  describe('rejectItem', () => {
    it('calls POST /dedup/review/:id/reject', async () => {
      const item = { id: 'item-1', status: 'rejected' };
      const envelope = { data: item, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await rejectItem('item-1');

      expect(mockPost).toHaveBeenCalledWith('/dedup/review/item-1/reject');
      expect(result.data.status).toBe('rejected');
    });
  });

  describe('unmergeItem', () => {
    it('calls POST /dedup/unmerge/:id', async () => {
      const envelope = { data: { status: 'unmerged' }, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await unmergeItem('item-1');

      expect(mockPost).toHaveBeenCalledWith('/dedup/unmerge/item-1');
      expect(result.data.status).toBe('unmerged');
    });
  });
});
