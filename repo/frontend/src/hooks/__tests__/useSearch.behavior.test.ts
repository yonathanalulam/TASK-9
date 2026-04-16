import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';

/* ------------------------------------------------------------------ */
/*  Mock the search API at the boundary                                */
/* ------------------------------------------------------------------ */
const mockSearch = vi.fn();
vi.mock('@/api/search', () => ({
  search: (...args: unknown[]) => mockSearch(...args),
}));

import useSearch from '../useSearch';

function makeEnvelope(results: unknown[], total = 1) {
  return {
    data: results,
    meta: {
      request_id: 'req-1',
      timestamp: '2026-04-15T00:00:00Z',
      pagination: { page: 1, per_page: 20, total, total_pages: 1 },
    },
    error: null,
  };
}

const sampleResult = {
  id: 'uuid-1',
  content_type: 'JOB_POST',
  title: 'Engineer',
  author_name: 'Alice',
  published_at: '2026-03-01T00:00:00Z',
  tags: ['tech'],
  view_count: 100,
  reply_count: 10,
  snippet: '...match...',
  highlight_title: '<mark>Engineer</mark>',
  relevance_score: 9.2,
};

// Use a very short debounce (10ms) so real timers work within test timeouts
const DEBOUNCE_MS = 10;

describe('useSearch — behavior tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('starts with empty query, null results, no loading, no error', () => {
    mockSearch.mockResolvedValue(makeEnvelope([]));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    expect(result.current.query).toBe('');
    expect(result.current.results).toBeNull();
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('triggers debounced search after setting a query', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([sampleResult], 1));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('engineer');
    });

    await waitFor(() => {
      expect(mockSearch).toHaveBeenCalled();
    });

    // Verify the query was passed
    const sentParams = mockSearch.mock.calls[0][0];
    expect(sentParams.q).toBe('engineer');
  });

  it('stores search results in state after successful search', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([sampleResult], 1));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('engineer');
    });

    await waitFor(() => {
      expect(result.current.results).not.toBeNull();
      expect(result.current.results).toHaveLength(1);
      expect(result.current.results![0].title).toBe('Engineer');
    });
  });

  it('sets total from pagination metadata', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([sampleResult], 42));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('test');
    });

    await waitFor(() => {
      expect(result.current.total).toBe(42);
    });
  });

  it('sets isLoading=true while search is in-flight then false when done', async () => {
    let resolveSearch!: (value: unknown) => void;
    mockSearch.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveSearch = resolve;
        }),
    );

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('loading test');
    });

    // Wait for loading to become true
    await waitFor(() => {
      expect(result.current.isLoading).toBe(true);
    });

    // Resolve the search
    await act(async () => {
      resolveSearch(makeEnvelope([sampleResult]));
    });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });
  });

  it('sets error state when search fails', async () => {
    mockSearch.mockRejectedValue(new Error('Network failure'));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('failing query');
    });

    await waitFor(() => {
      expect(result.current.error).toBe('Network failure');
      expect(result.current.isLoading).toBe(false);
    });
  });

  it('clears results when query is emptied', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([sampleResult], 1));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    // First search with results
    act(() => {
      result.current.setQuery('test');
    });

    await waitFor(() => {
      expect(result.current.results).toHaveLength(1);
    });

    // Clear the query
    act(() => {
      result.current.setQuery('');
    });

    await waitFor(() => {
      expect(result.current.results).toBeNull();
      expect(result.current.total).toBe(0);
    });
  });

  it('resets page to 1 when query changes', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([sampleResult], 1));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    // Set initial query and manually set page > 1
    act(() => {
      result.current.setQuery('initial');
    });

    await waitFor(() => {
      expect(result.current.results).not.toBeNull();
    });

    act(() => {
      result.current.setPage(3);
    });

    // Change query which should reset page
    act(() => {
      result.current.setQuery('new query');
    });

    await waitFor(() => {
      expect(result.current.page).toBe(1);
    });
  });

  it('passes snake_case parameters to the search API', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([], 0));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setFilters({
        dateFrom: '2026-01-01',
        dateTo: '2026-12-31',
        store: 'store-1',
        region: 'region-1',
      });
      result.current.setQuery('test');
    });

    await waitFor(() => {
      expect(mockSearch).toHaveBeenCalled();
    });

    const sentParams = mockSearch.mock.calls[mockSearch.mock.calls.length - 1][0];
    expect(sentParams).toHaveProperty('date_from', '2026-01-01');
    expect(sentParams).toHaveProperty('date_to', '2026-12-31');
    expect(sentParams).toHaveProperty('store', 'store-1');
    expect(sentParams).toHaveProperty('region', 'region-1');
    expect(sentParams).toHaveProperty('per_page', 20);
  });

  it('passes AbortSignal to the search function', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([], 0));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('test');
    });

    await waitFor(() => {
      expect(mockSearch).toHaveBeenCalled();
    });

    // Second argument should be an AbortSignal
    const signal = mockSearch.mock.calls[0][1];
    expect(signal).toBeInstanceOf(AbortSignal);
  });

  it('omits sort param when sort mode is relevance (default)', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([], 0));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setQuery('test');
    });

    await waitFor(() => {
      expect(mockSearch).toHaveBeenCalled();
    });

    const sentParams = mockSearch.mock.calls[0][0];
    expect(sentParams.sort).toBeUndefined();
  });

  it('sends sort param when a non-default sort is selected', async () => {
    mockSearch.mockResolvedValue(makeEnvelope([], 0));

    const { result } = renderHook(() => useSearch(DEBOUNCE_MS));

    act(() => {
      result.current.setSort('newest');
      result.current.setQuery('test');
    });

    await waitFor(() => {
      expect(mockSearch).toHaveBeenCalled();
    });

    // Find the call where sort=newest (may be a later call due to re-renders)
    const callWithSort = mockSearch.mock.calls.find(
      (c: unknown[]) => (c[0] as Record<string, unknown>).sort === 'newest',
    );
    expect(callWithSort).toBeDefined();
  });
});
