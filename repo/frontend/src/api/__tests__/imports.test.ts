import { vi, describe, it, expect, beforeEach } from 'vitest';

/* ------------------------------------------------------------------ */
/*  Mock the API client                                                */
/* ------------------------------------------------------------------ */
const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import {
  createImport,
  listImports,
  getImport,
  getImportItems,
} from '../imports';

describe('Imports API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const sampleImport = {
    id: 'imp-1',
    filename: 'data.csv',
    format: 'csv',
    status: 'completed',
    total_items: 100,
    processed_items: 98,
    duplicate_items: 2,
    error_items: 0,
    uploaded_by: 'user-1',
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  };

  describe('createImport', () => {
    it('calls POST /imports with multipart form data', async () => {
      const envelope = { data: sampleImport, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const file = new File(['csv,data'], 'data.csv', { type: 'text/csv' });
      const result = await createImport(file);

      expect(mockPost).toHaveBeenCalledWith(
        '/imports',
        expect.any(FormData),
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      expect(result.data.id).toBe('imp-1');
    });

    it('appends the file under the "file" key', async () => {
      const envelope = { data: sampleImport, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const file = new File(['csv,data'], 'data.csv', { type: 'text/csv' });
      await createImport(file);

      const sentFormData: FormData = mockPost.mock.calls[0][1];
      expect(sentFormData.get('file')).toBeTruthy();
    });
  });

  describe('listImports', () => {
    it('calls GET /imports with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listImports();

      expect(mockGet).toHaveBeenCalledWith('/imports', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards status and pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listImports({ page: 2, status: 'completed' });

      expect(mockGet).toHaveBeenCalledWith('/imports', {
        params: { page: 2, status: 'completed' },
      });
    });
  });

  describe('getImport', () => {
    it('calls GET /imports/:id', async () => {
      const envelope = { data: sampleImport, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getImport('imp-1');

      expect(mockGet).toHaveBeenCalledWith('/imports/imp-1');
      expect(result.data.filename).toBe('data.csv');
    });
  });

  describe('getImportItems', () => {
    it('calls GET /imports/:id/items with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getImportItems('imp-1');

      expect(mockGet).toHaveBeenCalledWith('/imports/imp-1/items', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards status filter for items', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await getImportItems('imp-1', { status: 'error', page: 2 });

      expect(mockGet).toHaveBeenCalledWith('/imports/imp-1/items', {
        params: { status: 'error', page: 2 },
      });
    });
  });
});
