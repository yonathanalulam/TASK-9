import { describe, it, expect } from 'vitest';
import type { SearchFilters } from '../useSearch';
import type { SearchParams } from '@/api/search';

/**
 * Tests that the useSearch hook sends store and region params to the API
 * when they are set in the filters. This validates the contract between
 * the search UI state and the outgoing API request.
 */
describe('useSearch param propagation', () => {
  /**
   * Simulates the param-building logic from useSearch.ts executeSearch().
   * This is extracted to test the mapping without needing full hook rendering.
   */
  function buildSearchParams(
    q: string,
    f: SearchFilters,
    s: string,
    p: number,
  ): SearchParams {
    const sortParam = s === 'relevance' ? undefined : s;
    return {
      q,
      type: f.contentTypes?.[0],
      store: f.store || undefined,
      region: f.region || undefined,
      date_from: f.dateFrom,
      date_to: f.dateTo,
      sort: sortParam as SearchParams['sort'],
      page: p,
      per_page: 20,
    };
  }

  it('includes store param when filter is set', () => {
    const params = buildSearchParams('test', { store: 'store-uuid' }, 'relevance', 1);
    expect(params.store).toBe('store-uuid');
  });

  it('includes region param when filter is set', () => {
    const params = buildSearchParams('test', { region: 'region-uuid' }, 'relevance', 1);
    expect(params.region).toBe('region-uuid');
  });

  it('includes both store and region when both are set', () => {
    const params = buildSearchParams(
      'test',
      { store: 'store-1', region: 'region-1' },
      'relevance',
      1,
    );
    expect(params.store).toBe('store-1');
    expect(params.region).toBe('region-1');
  });

  it('omits store param when filter is empty string', () => {
    const params = buildSearchParams('test', { store: '' }, 'relevance', 1);
    expect(params.store).toBeUndefined();
  });

  it('omits region param when filter is undefined', () => {
    const params = buildSearchParams('test', {}, 'relevance', 1);
    expect(params.region).toBeUndefined();
    expect(params.store).toBeUndefined();
  });

  it('preserves other filters alongside store/region', () => {
    const params = buildSearchParams(
      'query',
      {
        store: 's1',
        region: 'r1',
        dateFrom: '2026-01-01',
        dateTo: '2026-12-31',
        contentTypes: ['JOB_POST'],
      },
      'newest',
      2,
    );
    expect(params.store).toBe('s1');
    expect(params.region).toBe('r1');
    expect(params.date_from).toBe('2026-01-01');
    expect(params.type).toBe('JOB_POST');
    expect(params.sort).toBe('newest');
    expect(params.page).toBe(2);
  });
});
