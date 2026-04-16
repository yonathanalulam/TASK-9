import { vi, describe, it, expect, beforeEach } from 'vitest';
import { REPORT_TYPES } from '../complianceReports';
import type { ComplianceReport, GenerateReportData } from '../complianceReports';

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
  generateReport,
  listReports,
  getReport,
  downloadReport,
} from '../complianceReports';

describe('Compliance report API contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

  describe('Report type enum', () => {
    it('includes exactly the 5 backend-canonical report types', () => {
      expect(REPORT_TYPES).toEqual([
        'RETENTION_SUMMARY',
        'CONSENT_AUDIT',
        'DATA_CLASSIFICATION',
        'EXPORT_LOG',
        'ACCESS_AUDIT',
      ]);
      expect(REPORT_TYPES).toHaveLength(5);
    });

    it('does NOT include old invalid types that backend rejects', () => {
      const invalidTypes = ['AUDIT_LOG', 'DATA_ACCESS', 'CONSENT_SUMMARY'];
      for (const invalid of invalidTypes) {
        expect(REPORT_TYPES).not.toContain(invalid);
      }
    });

    it('GenerateReportData requires report_type from the canonical enum', () => {
      const validRequest: GenerateReportData = {
        report_type: 'RETENTION_SUMMARY',
        parameters: {},
      };
      expect(validRequest.report_type).toBe('RETENTION_SUMMARY');
    });

    it('GenerateReportData does NOT require a title field', () => {
      const request: GenerateReportData = {
        report_type: 'ACCESS_AUDIT',
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((request as any).title).toBeUndefined();
    });
  });

  describe('ComplianceReport response shape', () => {
    it('matches backend serializeReport() output exactly', () => {
      const mockResponse: ComplianceReport = {
        id: 'uuid',
        report_type: 'RETENTION_SUMMARY',
        generated_by: 'user-uuid',
        parameters: { date_from: '2026-01-01' },
        download_url: '/api/v1/compliance-reports/uuid/download',
        tamper_hash_sha256: 'abc123...',
        previous_report_id: null,
        previous_report_hash: null,
        generated_at: '2026-04-14T00:00:00+00:00',
      };

      const keys = Object.keys(mockResponse);
      expect(keys).toHaveLength(9);
      expect(keys).toContain('download_url');
      expect(keys).toContain('tamper_hash_sha256');
      expect(keys).toContain('generated_at');
    });

    it('does NOT contain old frontend-guessed fields', () => {
      const response: ComplianceReport = {
        id: '1', report_type: 'ACCESS_AUDIT', generated_by: '2',
        parameters: {}, download_url: '/download/1',
        tamper_hash_sha256: 'hash', previous_report_id: null,
        previous_report_hash: null, generated_at: '2026-01-01',
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const r = response as any;
      expect(r.title).toBeUndefined();
      expect(r.status).toBeUndefined();
      expect(r.file_size).toBeUndefined();
      expect(r.sha256_hash).toBeUndefined();
      expect(r.expires_at).toBeUndefined();
      expect(r.created_at).toBeUndefined();
      expect(r.updated_at).toBeUndefined();
      expect(r.file_path).toBeUndefined();
    });

    it('download_url points to the correct route pattern', () => {
      const id = 'a1b2c3d4';
      const url = `/api/v1/compliance-reports/${id}/download`;
      expect(url).toMatch(/^\/api\/v1\/compliance-reports\/[^/]+\/download$/);
    });
  });

  /* ================================================================== */
  /*  Behavior tests — call real API functions with mocked HTTP          */
  /* ================================================================== */

  const sampleReport: ComplianceReport = {
    id: 'report-uuid',
    report_type: 'RETENTION_SUMMARY',
    generated_by: 'user-uuid',
    parameters: { date_from: '2026-01-01' },
    download_url: '/api/v1/compliance-reports/report-uuid/download',
    tamper_hash_sha256: 'abc123hash',
    previous_report_id: null,
    previous_report_hash: null,
    generated_at: '2026-04-14T00:00:00+00:00',
  };

  describe('generateReport', () => {
    it('calls POST /compliance-reports with report_type and parameters', async () => {
      const envelope = {
        data: sampleReport,
        meta: { request_id: 'req-1', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const requestData: GenerateReportData = {
        report_type: 'RETENTION_SUMMARY',
        parameters: { date_from: '2026-01-01' },
      };
      const result = await generateReport(requestData);

      expect(mockPost).toHaveBeenCalledWith('/compliance-reports', requestData);
      expect(result.data.id).toBe('report-uuid');
      expect(result.data.report_type).toBe('RETENTION_SUMMARY');
      expect(result.data.tamper_hash_sha256).toBe('abc123hash');
      expect(result.data.download_url).toContain('/compliance-reports/');
    });

    it('sends report_type (not title) in the request', async () => {
      const envelope = {
        data: sampleReport,
        meta: { request_id: 'req-2', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      await generateReport({ report_type: 'ACCESS_AUDIT' });

      const sentPayload = mockPost.mock.calls[0][1];
      expect(sentPayload).toHaveProperty('report_type');
      expect(sentPayload).not.toHaveProperty('title');
    });

    it('propagates validation errors', async () => {
      mockPost.mockRejectedValueOnce({
        response: { status: 422, data: { error: { code: 'VALIDATION_ERROR' } } },
      });

      await expect(generateReport({ report_type: 'RETENTION_SUMMARY' })).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 422 }),
        }),
      );
    });
  });

  describe('listReports', () => {
    it('calls GET /compliance-reports with pagination params', async () => {
      const envelope = {
        data: [sampleReport],
        meta: {
          request_id: 'req-3',
          timestamp: '2026-04-14T00:00:00+00:00',
          pagination: { page: 1, per_page: 10, total: 1, total_pages: 1 },
        },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listReports({ page: 1, per_page: 10 });

      expect(mockGet).toHaveBeenCalledWith('/compliance-reports', { params: { page: 1, per_page: 10 } });
      expect(result.data).toHaveLength(1);
      expect(result.data[0].report_type).toBe('RETENTION_SUMMARY');
    });

    it('calls GET /compliance-reports with empty params by default', async () => {
      const envelope = {
        data: [],
        meta: { request_id: 'req-4', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listReports();

      expect(mockGet).toHaveBeenCalledWith('/compliance-reports', { params: {} });
    });
  });

  describe('getReport', () => {
    it('calls GET /compliance-reports/:id and returns report with hash_verification', async () => {
      const detailReport = {
        ...sampleReport,
        hash_verification: {
          tamper_hash_sha256: 'abc123hash',
          previous_report_id: null,
          previous_report_hash: null,
          chain_intact: true,
          file_exists: true,
        },
      };
      const envelope = {
        data: detailReport,
        meta: { request_id: 'req-5', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getReport('report-uuid');

      expect(mockGet).toHaveBeenCalledWith('/compliance-reports/report-uuid');
      expect(result.data.id).toBe('report-uuid');
      expect(result.data.hash_verification.chain_intact).toBe(true);
    });

    it('propagates 404 for unknown report', async () => {
      mockGet.mockRejectedValueOnce({
        response: { status: 404, data: { error: { code: 'NOT_FOUND' } } },
      });

      await expect(getReport('nonexistent')).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 404 }),
        }),
      );
    });
  });

  describe('downloadReport', () => {
    it('calls GET /compliance-reports/:id/download with responseType blob', async () => {
      const blob = new Blob(['pdf-content'], { type: 'application/pdf' });
      mockGet.mockResolvedValueOnce({ data: blob });

      const result = await downloadReport('report-uuid');

      expect(mockGet).toHaveBeenCalledWith('/compliance-reports/report-uuid/download', {
        responseType: 'blob',
      });
      expect(result).toBeInstanceOf(Blob);
    });

    it('builds download URL from report id matching the correct route pattern', async () => {
      const blob = new Blob(['data']);
      mockGet.mockResolvedValueOnce({ data: blob });

      await downloadReport('a1b2c3d4');

      const calledUrl = mockGet.mock.calls[0][0];
      expect(calledUrl).toBe('/compliance-reports/a1b2c3d4/download');
    });
  });
});
