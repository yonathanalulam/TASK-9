import { vi, describe, it, expect, beforeEach } from 'vitest';

/* ------------------------------------------------------------------ */
/*  Mock the API client                                                */
/* ------------------------------------------------------------------ */
const { mockGet, mockPost, mockPut } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost, put: mockPut },
}));

import {
  listRegions,
  getRegion,
  createRegion,
  updateRegion,
  closeRegion,
  getRegionVersions,
} from '../regions';

describe('Regions API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const sampleRegion = {
    id: 'reg-1',
    code: 'US-CA',
    name: 'California',
    parent_id: null,
    effective_from: '2026-01-01',
    effective_until: null,
    version: 1,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  };

  describe('listRegions', () => {
    it('calls GET /regions with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listRegions();

      expect(mockGet).toHaveBeenCalledWith('/regions', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards query params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listRegions({ page: 2, active_only: true });

      expect(mockGet).toHaveBeenCalledWith('/regions', {
        params: { page: 2, active_only: true },
      });
    });
  });

  describe('getRegion', () => {
    it('calls GET /regions/:id', async () => {
      const envelope = { data: sampleRegion, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getRegion('reg-1');

      expect(mockGet).toHaveBeenCalledWith('/regions/reg-1');
      expect(result.data.code).toBe('US-CA');
    });
  });

  describe('createRegion', () => {
    it('calls POST /regions with payload', async () => {
      const envelope = { data: sampleRegion, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { code: 'US-CA', name: 'California', effective_from: '2026-01-01' };
      const result = await createRegion(payload);

      expect(mockPost).toHaveBeenCalledWith('/regions', payload);
      expect(result.data.id).toBe('reg-1');
    });
  });

  describe('updateRegion', () => {
    it('calls PUT /regions/:id with If-Match header', async () => {
      const updated = { ...sampleRegion, name: 'Cali', version: 2 };
      const envelope = { data: updated, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const result = await updateRegion('reg-1', { name: 'Cali' }, 1);

      expect(mockPut).toHaveBeenCalledWith(
        '/regions/reg-1',
        { name: 'Cali' },
        { headers: { 'If-Match': '1' } },
      );
      expect(result.data.name).toBe('Cali');
    });
  });

  describe('closeRegion', () => {
    it('calls POST /regions/:id/close with reassignment data', async () => {
      const closed = { ...sampleRegion, effective_until: '2026-06-01' };
      const envelope = { data: closed, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { child_reassignments: { 'reg-2': 'reg-3' }, reason: 'Merged' };
      const result = await closeRegion('reg-1', payload);

      expect(mockPost).toHaveBeenCalledWith('/regions/reg-1/close', payload);
      expect(result.data.effective_until).toBe('2026-06-01');
    });
  });

  describe('getRegionVersions', () => {
    it('calls GET /regions/:id/versions', async () => {
      const envelope = { data: [{ version: 1 }, { version: 2 }], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getRegionVersions('reg-1');

      expect(mockGet).toHaveBeenCalledWith('/regions/reg-1/versions');
      expect(result.data).toHaveLength(2);
    });
  });
});
