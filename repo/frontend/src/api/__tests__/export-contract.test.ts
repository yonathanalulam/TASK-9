import { vi, describe, it, expect, beforeEach } from 'vitest';
import {
  EXPORT_DATASETS,
  EXPORT_FORMATS,
  DATASET_LABELS,
} from '../exports';
import type { ExportRecord, ExportRequest } from '../exports';

/* ------------------------------------------------------------------ */
/*  Mock the API client (axios instance used by all API modules)       */
/* ------------------------------------------------------------------ */
const { mockGet, mockPost } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost },
}));

import {
  requestExport,
  authorizeExport,
  getExport,
  downloadExport,
  listExports,
} from '../exports';

describe('Export API contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

  /* ---------------------------------------------------------------- */
  /*  Dataset enum alignment                                           */
  /* ---------------------------------------------------------------- */

  describe('Dataset enum', () => {
    it('contains exactly the backend-supported dataset values', () => {
      expect(EXPORT_DATASETS).toEqual(['content_items', 'audit_events']);
      expect(EXPORT_DATASETS).toHaveLength(2);
    });

    it('does NOT contain old invalid dataset values', () => {
      const invalid = ['content', 'users', 'regions', 'stores', 'delivery_zones', 'audit_logs'];
      for (const v of invalid) {
        expect(EXPORT_DATASETS as readonly string[]).not.toContain(v);
      }
    });

    it('has a display label for every dataset value', () => {
      for (const d of EXPORT_DATASETS) {
        expect(DATASET_LABELS[d]).toBeDefined();
        expect(typeof DATASET_LABELS[d]).toBe('string');
        expect(DATASET_LABELS[d].length).toBeGreaterThan(0);
      }
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Format enum alignment                                            */
  /* ---------------------------------------------------------------- */

  describe('Format enum', () => {
    it('contains only CSV — the only format with a real backend renderer', () => {
      expect(EXPORT_FORMATS).toEqual(['CSV']);
      expect(EXPORT_FORMATS).toHaveLength(1);
    });

    it('does NOT contain PDF or HTML (no backend renderer exists)', () => {
      expect(EXPORT_FORMATS as readonly string[]).not.toContain('PDF');
      expect(EXPORT_FORMATS as readonly string[]).not.toContain('HTML');
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Request payload contract                                         */
  /* ---------------------------------------------------------------- */

  describe('Export request payload', () => {
    it('sends dataset and format from the canonical enums', () => {
      const request: ExportRequest = {
        dataset: 'content_items',
        format: 'CSV',
      };
      expect(EXPORT_DATASETS as readonly string[]).toContain(request.dataset);
      expect(EXPORT_FORMATS as readonly string[]).toContain(request.format);
    });

    it('does NOT include a reason field', () => {
      const request: ExportRequest = {
        dataset: 'audit_events',
        format: 'CSV',
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((request as any).reason).toBeUndefined();
    });

    it('optionally includes filters', () => {
      const request: ExportRequest = {
        dataset: 'content_items',
        format: 'CSV',
        filters: { limit: 500 },
      };
      expect(request.filters).toEqual({ limit: 500 });
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Response DTO contract                                            */
  /* ---------------------------------------------------------------- */

  describe('Export response shape', () => {
    it('matches backend serializeExportJob() exactly — 14 fields', () => {
      const response: ExportRecord = {
        id: 'uuid',
        dataset: 'content_items',
        format: 'CSV',
        status: 'SUCCEEDED',
        requested_by: 'user-uuid',
        authorized_by: null,
        filters: {},
        file_name: 'content_items_admin_04-14-2026_10-00-AM.csv',
        watermark_text: 'admin 04/14/2026 10:00 AM',
        tamper_hash_sha256: 'abc123',
        requested_at: '2026-04-14T10:00:00+00:00',
        authorized_at: null,
        completed_at: '2026-04-14T10:01:00+00:00',
        expires_at: '2026-04-21T10:01:00+00:00',
      };
      expect(Object.keys(response)).toHaveLength(14);
    });

    it('does NOT contain old frontend-guessed fields', () => {
      const response: ExportRecord = {
        id: '1',
        dataset: 'content_items',
        format: 'CSV',
        status: 'REQUESTED',
        requested_by: '2',
        authorized_by: null,
        filters: {},
        file_name: null,
        watermark_text: null,
        tamper_hash_sha256: null,
        requested_at: '2026-01-01',
        authorized_at: null,
        completed_at: null,
        expires_at: null,
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const r = response as any;
      expect(r.reason).toBeUndefined();
      expect(r.file_size).toBeUndefined();
      expect(r.download_url).toBeUndefined();
      expect(r.created_at).toBeUndefined();
      expect(r.updated_at).toBeUndefined();
    });

    it('uses requested_at (not created_at) for the primary timestamp', () => {
      const response: ExportRecord = {
        id: '1',
        dataset: 'content_items',
        format: 'CSV',
        status: 'REQUESTED',
        requested_by: '2',
        authorized_by: null,
        filters: {},
        file_name: null,
        watermark_text: null,
        tamper_hash_sha256: null,
        requested_at: '2026-04-14T10:00:00+00:00',
        authorized_at: null,
        completed_at: null,
        expires_at: null,
      };
      expect(response.requested_at).toBeDefined();
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((response as any).created_at).toBeUndefined();
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Download contract                                                */
  /* ---------------------------------------------------------------- */

  describe('Download behavior (shape)', () => {
    it('download uses the export id, not a download_url field', () => {
      const record: ExportRecord = {
        id: 'test-export-id',
        dataset: 'content_items',
        format: 'CSV',
        status: 'SUCCEEDED',
        requested_by: '2',
        authorized_by: null,
        filters: {},
        file_name: 'export.csv',
        watermark_text: null,
        tamper_hash_sha256: null,
        requested_at: '2026-01-01',
        authorized_at: null,
        completed_at: null,
        expires_at: null,
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((record as any).download_url).toBeUndefined();
      expect(record.id).toBe('test-export-id');
    });
  });

  /* ================================================================== */
  /*  Behavior tests — call real API functions with mocked HTTP          */
  /* ================================================================== */

  const sampleExportRecord: ExportRecord = {
    id: 'export-uuid',
    dataset: 'content_items',
    format: 'CSV',
    status: 'REQUESTED',
    requested_by: 'user-uuid',
    authorized_by: null,
    filters: {},
    file_name: null,
    watermark_text: null,
    tamper_hash_sha256: null,
    requested_at: '2026-04-14T10:00:00+00:00',
    authorized_at: null,
    completed_at: null,
    expires_at: null,
  };

  describe('requestExport', () => {
    it('calls POST /exports with dataset and format', async () => {
      const envelope = {
        data: sampleExportRecord,
        meta: { request_id: 'req-1', timestamp: '2026-04-14T10:00:00+00:00' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const request: ExportRequest = { dataset: 'content_items', format: 'CSV' };
      const result = await requestExport(request);

      expect(mockPost).toHaveBeenCalledWith('/exports', request);
      expect(result.data.id).toBe('export-uuid');
      expect(result.data.dataset).toBe('content_items');
      expect(result.data.status).toBe('REQUESTED');
    });

    it('sends filters when provided', async () => {
      const envelope = {
        data: { ...sampleExportRecord, filters: { limit: 500 } },
        meta: { request_id: 'req-2', timestamp: '2026-04-14T10:00:00+00:00' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const request: ExportRequest = { dataset: 'content_items', format: 'CSV', filters: { limit: 500 } };
      await requestExport(request);

      const sentPayload = mockPost.mock.calls[0][1];
      expect(sentPayload.filters).toEqual({ limit: 500 });
    });

    it('propagates validation errors', async () => {
      mockPost.mockRejectedValueOnce({
        response: { status: 422, data: { error: { code: 'VALIDATION_ERROR' } } },
      });

      await expect(requestExport({ dataset: 'content_items', format: 'CSV' })).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 422 }),
        }),
      );
    });
  });

  describe('authorizeExport', () => {
    it('calls POST /exports/:id/authorize', async () => {
      const authorized = { ...sampleExportRecord, status: 'AUTHORIZED', authorized_by: 'admin-uuid' };
      const envelope = {
        data: authorized,
        meta: { request_id: 'req-3', timestamp: '2026-04-14T10:01:00+00:00' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await authorizeExport('export-uuid');

      expect(mockPost).toHaveBeenCalledWith('/exports/export-uuid/authorize');
      expect(result.data.status).toBe('AUTHORIZED');
      expect(result.data.authorized_by).toBe('admin-uuid');
    });
  });

  describe('getExport', () => {
    it('calls GET /exports/:id and returns export record', async () => {
      const envelope = {
        data: sampleExportRecord,
        meta: { request_id: 'req-4', timestamp: '2026-04-14T10:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getExport('export-uuid');

      expect(mockGet).toHaveBeenCalledWith('/exports/export-uuid');
      expect(result.data.id).toBe('export-uuid');
    });
  });

  describe('downloadExport', () => {
    it('calls GET /exports/:id/download with responseType blob', async () => {
      const blob = new Blob(['csv,data'], { type: 'text/csv' });
      mockGet.mockResolvedValueOnce({ data: blob });

      const result = await downloadExport('export-uuid');

      expect(mockGet).toHaveBeenCalledWith('/exports/export-uuid/download', {
        responseType: 'blob',
      });
      expect(result).toBeInstanceOf(Blob);
    });

    it('builds download URL from export id, not a download_url field', async () => {
      const blob = new Blob(['data']);
      mockGet.mockResolvedValueOnce({ data: blob });

      await downloadExport('my-export-id');

      expect(mockGet).toHaveBeenCalledWith(
        expect.stringContaining('/exports/my-export-id/download'),
        expect.anything(),
      );
    });
  });

  describe('listExports', () => {
    it('calls GET /exports with pagination params', async () => {
      const envelope = {
        data: [sampleExportRecord],
        meta: {
          request_id: 'req-5',
          timestamp: '2026-04-14T10:00:00+00:00',
          pagination: { page: 1, per_page: 10, total: 1, total_pages: 1 },
        },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listExports({ page: 1, per_page: 10 });

      expect(mockGet).toHaveBeenCalledWith('/exports', { params: { page: 1, per_page: 10 } });
      expect(result.data).toHaveLength(1);
      expect(result.data[0].id).toBe('export-uuid');
    });

    it('calls GET /exports with empty params by default', async () => {
      const envelope = {
        data: [],
        meta: { request_id: 'req-6', timestamp: '2026-04-14T10:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listExports();

      expect(mockGet).toHaveBeenCalledWith('/exports', { params: {} });
    });
  });
});
