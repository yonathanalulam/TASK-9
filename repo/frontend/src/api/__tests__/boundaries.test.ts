import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import {
  uploadBoundary,
  listBoundaries,
  getBoundary,
  validateBoundary,
  applyBoundary,
} from '../boundaries';

describe('Boundaries API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const sampleBoundary = {
    id: 'bnd-1',
    filename: 'zones.geojson',
    type: 'geojson',
    size: 1024,
    hash: 'abc123',
    status: 'pending',
    uploaded_by: 'admin',
    area_count: null,
    errors: null,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  };

  describe('uploadBoundary', () => {
    it('calls POST /boundaries/upload with multipart form data', async () => {
      const envelope = { data: sampleBoundary, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const file = new File(['geojson-data'], 'zones.geojson', { type: 'application/json' });
      const result = await uploadBoundary(file);

      expect(mockPost).toHaveBeenCalledWith(
        '/boundaries/upload',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      expect(result.data.id).toBe('bnd-1');
    });
  });

  describe('listBoundaries', () => {
    it('calls GET /boundaries with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listBoundaries();

      expect(mockGet).toHaveBeenCalledWith('/boundaries', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards status filter', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listBoundaries({ status: 'validated', page: 2 });

      expect(mockGet).toHaveBeenCalledWith('/boundaries', {
        params: { status: 'validated', page: 2 },
      });
    });
  });

  describe('getBoundary', () => {
    it('calls GET /boundaries/:id', async () => {
      const envelope = { data: sampleBoundary, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getBoundary('bnd-1');

      expect(mockGet).toHaveBeenCalledWith('/boundaries/bnd-1');
      expect(result.data.filename).toBe('zones.geojson');
    });
  });

  describe('validateBoundary', () => {
    it('calls POST /boundaries/:id/validate', async () => {
      const validated = { ...sampleBoundary, status: 'validated', area_count: 5 };
      const envelope = { data: validated, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await validateBoundary('bnd-1');

      expect(mockPost).toHaveBeenCalledWith('/boundaries/bnd-1/validate');
      expect(result.data.status).toBe('validated');
    });
  });

  describe('applyBoundary', () => {
    it('calls POST /boundaries/:id/apply', async () => {
      const applied = { ...sampleBoundary, status: 'applied' };
      const envelope = { data: applied, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await applyBoundary('bnd-1');

      expect(mockPost).toHaveBeenCalledWith('/boundaries/bnd-1/apply');
      expect(result.data.status).toBe('applied');
    });
  });
});
