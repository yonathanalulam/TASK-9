import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import { listLoadRuns, getLoadRun, triggerLoad } from '../warehouse';

describe('Warehouse API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const sampleLoad = {
    id: 'load-1',
    load_type: 'full',
    status: 'COMPLETED',
    rows_extracted: 500,
    rows_loaded: 498,
    rows_rejected: 2,
    rejected_details: [],
    started_at: '2026-01-01T00:00:00Z',
    completed_at: '2026-01-01T00:05:00Z',
    duration_ms: 300000,
    triggered_by: 'admin',
    error: null,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  };

  describe('listLoadRuns', () => {
    it('calls GET /warehouse/loads with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listLoadRuns();

      expect(mockGet).toHaveBeenCalledWith('/warehouse/loads', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listLoadRuns({ page: 2, per_page: 10 });

      expect(mockGet).toHaveBeenCalledWith('/warehouse/loads', {
        params: { page: 2, per_page: 10 },
      });
    });
  });

  describe('getLoadRun', () => {
    it('calls GET /warehouse/loads/:id', async () => {
      const envelope = { data: sampleLoad, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getLoadRun('load-1');

      expect(mockGet).toHaveBeenCalledWith('/warehouse/loads/load-1');
      expect(result.data.rows_loaded).toBe(498);
    });
  });

  describe('triggerLoad', () => {
    it('calls POST /warehouse/loads/trigger', async () => {
      const newLoad = { ...sampleLoad, id: 'load-new', status: 'PENDING' };
      const envelope = { data: newLoad, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await triggerLoad();

      expect(mockPost).toHaveBeenCalledWith('/warehouse/loads/trigger');
      expect(result.data.status).toBe('PENDING');
    });
  });
});
