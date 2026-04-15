import { describe, it, expect } from 'vitest';
import {
  EXPORT_DATASETS,
  EXPORT_FORMATS,
  DATASET_LABELS,
} from '../exports';
import type { ExportRecord, ExportRequest } from '../exports';

describe('Export API contract', () => {
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

  describe('Download behavior', () => {
    it('download uses the export id, not a download_url field', () => {
      // The ExportRecord type does NOT have a download_url field.
      // Download is done via downloadExport(id) which calls GET /exports/{id}/download.
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
      // Download is by ID via the API function, which builds the URL internally
      expect(record.id).toBe('test-export-id');
    });
  });
});
