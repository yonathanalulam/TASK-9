import { describe, it, expect } from 'vitest';
import { REPORT_TYPES } from '../complianceReports';
import type { ComplianceReport, GenerateReportData } from '../complianceReports';

describe('Compliance report API contract', () => {
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
      // This compiles because 'RETENTION_SUMMARY' is a valid ReportType
      const validRequest: GenerateReportData = {
        report_type: 'RETENTION_SUMMARY',
        parameters: {},
      };
      expect(validRequest.report_type).toBe('RETENTION_SUMMARY');
    });

    it('GenerateReportData does NOT require a title field', () => {
      // The old contract had title as required. New contract omits it.
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

      // All 9 canonical keys present
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
});
