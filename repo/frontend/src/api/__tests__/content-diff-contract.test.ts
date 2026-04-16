import { vi, describe, it, expect, beforeEach } from 'vitest';
import type { VersionDiff, DiffChange } from '../content';

/* ------------------------------------------------------------------ */
/*  Mock the API client (axios instance used by all API modules)       */
/* ------------------------------------------------------------------ */
const { mockGet } = vi.hoisted(() => ({
  mockGet: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet },
}));

import { diffVersions, getContentVersions, getContentVersion } from '../content';

describe('Content diff contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

  it('VersionDiff type has v1, v2, and changes fields', () => {
    const mockBackendResponse: VersionDiff = {
      v1: { id: 'uuid-1', version_number: 1 },
      v2: { id: 'uuid-2', version_number: 2 },
      changes: [
        { field: 'title', before: 'Old Title', after: 'New Title' },
        { field: 'body', before: 'Old body', after: 'New body' },
      ],
    };

    expect(mockBackendResponse.v1.id).toBe('uuid-1');
    expect(mockBackendResponse.v2.version_number).toBe(2);
    expect(mockBackendResponse.changes).toHaveLength(2);
    expect(mockBackendResponse.changes[0].field).toBe('title');
  });

  it('DiffChange has field, before, after', () => {
    const change: DiffChange = {
      field: 'tags',
      before: ['old-tag'],
      after: ['new-tag'],
    };
    expect(change.field).toBe('tags');
    expect(Array.isArray(change.before)).toBe(true);
  });

  // Regression test: VersionDiff must NOT have a changed_fields property
  it('VersionDiff uses changes (not changed_fields)', () => {
    const diff: VersionDiff = {
      v1: { id: '1', version_number: 1 },
      v2: { id: '2', version_number: 2 },
      changes: [],
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    expect((diff as any).changed_fields).toBeUndefined();
    expect(diff.changes).toBeDefined();
  });

  /* ================================================================== */
  /*  Behavior tests — call real API functions with mocked HTTP          */
  /* ================================================================== */

  describe('diffVersions', () => {
    it('calls GET /content/:contentId/versions/:v1Id/diff/:v2Id', async () => {
      const diffData: VersionDiff = {
        v1: { id: 'ver-1', version_number: 1 },
        v2: { id: 'ver-2', version_number: 2 },
        changes: [
          { field: 'title', before: 'Old Title', after: 'New Title' },
          { field: 'body', before: 'Old body', after: 'New body' },
        ],
      };
      const envelope = {
        data: diffData,
        meta: { request_id: 'req-1', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await diffVersions('content-123', 'ver-1', 'ver-2');

      expect(mockGet).toHaveBeenCalledWith('/content/content-123/versions/ver-1/diff/ver-2');
      expect(result.data.v1.id).toBe('ver-1');
      expect(result.data.v2.version_number).toBe(2);
      expect(result.data.changes).toHaveLength(2);
      expect(result.data.changes[0].field).toBe('title');
      expect(result.data.changes[0].before).toBe('Old Title');
      expect(result.data.changes[0].after).toBe('New Title');
    });

    it('returns response with changes array (not changed_fields)', async () => {
      const diffData: VersionDiff = {
        v1: { id: 'v1', version_number: 1 },
        v2: { id: 'v2', version_number: 2 },
        changes: [{ field: 'tags', before: ['old'], after: ['new'] }],
      };
      const envelope = {
        data: diffData,
        meta: { request_id: 'req-2', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await diffVersions('c-1', 'v1', 'v2');

      expect(result.data).toHaveProperty('changes');
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((result.data as any).changed_fields).toBeUndefined();
    });

    it('propagates 404 when content or versions not found', async () => {
      mockGet.mockRejectedValueOnce({
        response: { status: 404, data: { error: { code: 'NOT_FOUND' } } },
      });

      await expect(diffVersions('bad-id', 'v1', 'v2')).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 404 }),
        }),
      );
    });
  });

  describe('getContentVersions', () => {
    it('calls GET /content/:contentId/versions', async () => {
      const versions = [
        {
          id: 'ver-1', content_item_id: 'c-1', version_number: 1,
          title: 'Title', body: 'Body', tags: [], content_type: 'ARTICLE',
          status_at_creation: 'DRAFT', change_reason: null, is_rollback: false,
          rolled_back_to_version_id: null, created_by: 'user-1',
          created_at: '2026-01-01T00:00:00Z',
        },
      ];
      const envelope = {
        data: versions,
        meta: { request_id: 'req-3', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getContentVersions('c-1');

      expect(mockGet).toHaveBeenCalledWith('/content/c-1/versions');
      expect(result.data).toHaveLength(1);
    });
  });

  describe('getContentVersion', () => {
    it('calls GET /content/:contentId/versions/:versionId', async () => {
      const version = {
        id: 'ver-1', content_item_id: 'c-1', version_number: 1,
        title: 'Title', body: 'Body', tags: [], content_type: 'ARTICLE',
        status_at_creation: 'DRAFT', change_reason: null, is_rollback: false,
        rolled_back_to_version_id: null, created_by: 'user-1',
        created_at: '2026-01-01T00:00:00Z',
      };
      const envelope = {
        data: version,
        meta: { request_id: 'req-4', timestamp: '2026-04-14T00:00:00+00:00' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getContentVersion('c-1', 'ver-1');

      expect(mockGet).toHaveBeenCalledWith('/content/c-1/versions/ver-1');
      expect(result.data.id).toBe('ver-1');
      expect(result.data.version_number).toBe(1);
    });
  });
});
