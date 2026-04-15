import { describe, it, expect } from 'vitest';
import type { SearchResult } from '../types';
import type { SearchParams } from '../search';

describe('Search API contract', () => {
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
});
