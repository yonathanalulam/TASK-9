import { vi, describe, it, expect, beforeEach } from 'vitest';
import type { SearchResult } from '../types';
import type { SearchParams } from '../search';

/* ------------------------------------------------------------------ */
/*  Mock the API client (axios instance used by all API modules)       */
/* ------------------------------------------------------------------ */
const { mockGet } = vi.hoisted(() => ({
  mockGet: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet },
}));

import { search } from '../search';

describe('Search API contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

  it('SearchResult has required fields from backend', () => {
    const result: SearchResult = {
      id: 'uuid',
      content_type: 'JOB_POST',
      title: 'Software Engineer',
      author_name: 'John Doe',
      published_at: '2026-01-01T00:00:00Z',
      tags: ['engineering', 'remote'],
      view_count: 42,
      reply_count: 5,
      snippet: '...relevant text...',
      highlight_title: 'Software <mark>Engineer</mark>',
      relevance_score: 8.5,
    };
    expect(result.content_type).toBe('JOB_POST');
    expect(result.author_name).toBe('John Doe');
    expect(result.snippet).toBeDefined();
    expect(result.highlight_title).toContain('<mark>');
  });

  it('SearchParams uses correct backend parameter names', () => {
    const params: SearchParams = {
      q: 'test query',
      type: 'JOB_POST',
      date_from: '2026-01-01',
      date_to: '2026-12-31',
      sort: 'newest',
      page: 1,
      per_page: 25,
    };
    // Verify snake_case param names (NOT camelCase)
    expect(params.per_page).toBe(25);
    expect(params.date_from).toBe('2026-01-01');
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    expect((params as any).perPage).toBeUndefined();
    expect((params as any).dateFrom).toBeUndefined();
  });

  it('SearchParams sort values match backend enum', () => {
    const validSorts: SearchParams['sort'][] = ['newest', 'most_viewed', 'highest_reply'];
    validSorts.forEach(s => expect(typeof s).toBe('string'));
    // most_replies was the old incorrect name
  });

  /* ================================================================== */
  /*  Behavior tests — call real API functions with mocked HTTP          */
  /* ================================================================== */

  describe('search', () => {
    it('calls GET /search with query params and returns results', async () => {
      const searchResults: SearchResult[] = [
        {
          id: 'uuid-1', content_type: 'JOB_POST', title: 'Engineer',
          author_name: 'Alice', published_at: '2026-03-01T00:00:00Z',
          tags: ['tech'], view_count: 100, reply_count: 10,
          snippet: '...match...', highlight_title: '<mark>Engineer</mark>',
          relevance_score: 9.2,
        },
      ];
      const envelope = {
        data: searchResults,
        meta: { request_id: 'req-1', timestamp: '2026-03-01T00:00:00Z' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const params: SearchParams = {
        q: 'engineer',
        type: 'JOB_POST',
        date_from: '2026-01-01',
        sort: 'newest',
        page: 1,
        per_page: 25,
      };
      const result = await search(params);

      expect(mockGet).toHaveBeenCalledWith('/search', {
        params,
        signal: undefined,
      });
      expect(result.data).toHaveLength(1);
      expect(result.data[0].title).toBe('Engineer');
      expect(result.data[0].relevance_score).toBe(9.2);
      expect(result.data[0].highlight_title).toContain('<mark>');
    });

    it('passes snake_case params to the backend, not camelCase', async () => {
      const envelope = {
        data: [],
        meta: { request_id: 'req-2', timestamp: '2026-03-01T00:00:00Z' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const params: SearchParams = {
        q: 'test',
        date_from: '2026-01-01',
        date_to: '2026-12-31',
        per_page: 10,
      };
      await search(params);

      const sentParams = mockGet.mock.calls[0][1].params;
      expect(sentParams).toHaveProperty('date_from');
      expect(sentParams).toHaveProperty('date_to');
      expect(sentParams).toHaveProperty('per_page');
      expect(sentParams).not.toHaveProperty('dateFrom');
      expect(sentParams).not.toHaveProperty('dateTo');
      expect(sentParams).not.toHaveProperty('perPage');
    });

    it('forwards AbortSignal to the client', async () => {
      const envelope = {
        data: [],
        meta: { request_id: 'req-3', timestamp: '2026-03-01T00:00:00Z' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const controller = new AbortController();
      await search({ q: 'test' }, controller.signal);

      expect(mockGet).toHaveBeenCalledWith('/search', {
        params: { q: 'test' },
        signal: controller.signal,
      });
    });

    it('propagates HTTP errors from the client', async () => {
      mockGet.mockRejectedValueOnce({
        response: { status: 422, data: { error: { code: 'VALIDATION_ERROR' } } },
      });

      await expect(search({ q: '' })).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 422 }),
        }),
      );
    });
  });
});
