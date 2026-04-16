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
  listContent,
  getContent,
  createContent,
  updateContent,
  publishContent,
  archiveContent,
  getContentVersions,
  getContentVersion,
  diffVersions,
  rollbackContent,
} from '../content';

describe('Content API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  const sampleContent = {
    id: 'ct-1',
    title: 'Test Article',
    body: 'Body text',
    content_type: 'article',
    author_name: 'Alice',
    status: 'draft',
    tags: ['test'],
    store_id: null,
    region_id: null,
    version: 1,
    created_at: '2026-01-01',
    updated_at: '2026-01-01',
  };

  /* ================================================================ */
  /*  CRUD                                                             */
  /* ================================================================ */

  describe('listContent', () => {
    it('calls GET /content with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listContent();

      expect(mockGet).toHaveBeenCalledWith('/content', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards filter params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listContent({ content_type: 'article', status: 'published', page: 2 });

      expect(mockGet).toHaveBeenCalledWith('/content', {
        params: { content_type: 'article', status: 'published', page: 2 },
      });
    });
  });

  describe('getContent', () => {
    it('calls GET /content/:id', async () => {
      const envelope = { data: sampleContent, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getContent('ct-1');

      expect(mockGet).toHaveBeenCalledWith('/content/ct-1');
      expect(result.data.title).toBe('Test Article');
    });
  });

  describe('createContent', () => {
    it('calls POST /content with payload', async () => {
      const envelope = { data: sampleContent, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = {
        title: 'Test Article',
        body: 'Body text',
        content_type: 'article',
        author_name: 'Alice',
      };
      const result = await createContent(payload);

      expect(mockPost).toHaveBeenCalledWith('/content', payload);
      expect(result.data.id).toBe('ct-1');
    });
  });

  describe('updateContent', () => {
    it('calls PUT /content/:id with If-Match header', async () => {
      const updated = { ...sampleContent, title: 'Updated Title', version: 2 };
      const envelope = { data: updated, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const result = await updateContent('ct-1', { title: 'Updated Title' }, 1);

      expect(mockPut).toHaveBeenCalledWith(
        '/content/ct-1',
        { title: 'Updated Title' },
        { headers: { 'If-Match': '1' } },
      );
      expect(result.data.title).toBe('Updated Title');
    });
  });

  /* ================================================================ */
  /*  Actions                                                          */
  /* ================================================================ */

  describe('publishContent', () => {
    it('calls POST /content/:id/publish', async () => {
      const published = { ...sampleContent, status: 'published' };
      const envelope = { data: published, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await publishContent('ct-1');

      expect(mockPost).toHaveBeenCalledWith('/content/ct-1/publish');
      expect(result.data.status).toBe('published');
    });
  });

  describe('archiveContent', () => {
    it('calls POST /content/:id/archive', async () => {
      const archived = { ...sampleContent, status: 'archived' };
      const envelope = { data: archived, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await archiveContent('ct-1');

      expect(mockPost).toHaveBeenCalledWith('/content/ct-1/archive');
      expect(result.data.status).toBe('archived');
    });
  });

  /* ================================================================ */
  /*  Versions                                                         */
  /* ================================================================ */

  describe('getContentVersions', () => {
    it('calls GET /content/:id/versions', async () => {
      const versions = [{ id: 'v-1', version_number: 1 }, { id: 'v-2', version_number: 2 }];
      const envelope = { data: versions, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getContentVersions('ct-1');

      expect(mockGet).toHaveBeenCalledWith('/content/ct-1/versions');
      expect(result.data).toHaveLength(2);
    });
  });

  describe('getContentVersion', () => {
    it('calls GET /content/:contentId/versions/:versionId', async () => {
      const version = { id: 'v-1', version_number: 1, title: 'V1 Title' };
      const envelope = { data: version, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getContentVersion('ct-1', 'v-1');

      expect(mockGet).toHaveBeenCalledWith('/content/ct-1/versions/v-1');
      expect(result.data.version_number).toBe(1);
    });
  });

  describe('diffVersions', () => {
    it('calls GET /content/:contentId/versions/:v1Id/diff/:v2Id', async () => {
      const diff = {
        v1: { id: 'v-1', version_number: 1 },
        v2: { id: 'v-2', version_number: 2 },
        changes: [{ field: 'title', before: 'Old', after: 'New' }],
      };
      const envelope = { data: diff, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await diffVersions('ct-1', 'v-1', 'v-2');

      expect(mockGet).toHaveBeenCalledWith('/content/ct-1/versions/v-1/diff/v-2');
      expect(result.data.changes).toHaveLength(1);
      expect(result.data.changes[0].field).toBe('title');
    });
  });

  describe('rollbackContent', () => {
    it('calls POST /content/:contentId/rollback with target version and reason', async () => {
      const rolledBack = { ...sampleContent, version: 3 };
      const envelope = { data: rolledBack, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await rollbackContent('ct-1', 'v-1', 'Reverting bad change');

      expect(mockPost).toHaveBeenCalledWith('/content/ct-1/rollback', {
        target_version_id: 'v-1',
        reason: 'Reverting bad change',
      });
      expect(result.data.version).toBe(3);
    });
  });
});
