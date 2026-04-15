import { useState, useEffect, useRef, useCallback } from 'react';
import { search, type SearchParams } from '@/api/search';
import type { SearchResult } from '@/api/types';

export interface SearchFilters {
  store?: string;
  region?: string;
  dateFrom?: string;
  dateTo?: string;
  contentTypes?: string[];
}

export type SearchSortMode = 'relevance' | 'newest' | 'most_viewed' | 'highest_reply';

interface UseSearchReturn {
  query: string;
  setQuery: (q: string) => void;
  filters: SearchFilters;
  setFilters: (f: SearchFilters) => void;
  sort: SearchSortMode;
  setSort: (s: SearchSortMode) => void;
  results: SearchResult[] | null;
  isLoading: boolean;
  error: string | null;
  page: number;
  setPage: (p: number) => void;
  total: number;
}

export default function useSearch(debounceMs = 300): UseSearchReturn {
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<SearchFilters>({});
  const [sort, setSort] = useState<SearchSortMode>('relevance');
  const [page, setPage] = useState(1);
  const [results, setResults] = useState<SearchResult[] | null>(null);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const abortRef = useRef<AbortController | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const executeSearch = useCallback(
    async (q: string, f: SearchFilters, s: SearchSortMode, p: number) => {
      // Cancel any in-flight request
      if (abortRef.current) {
        abortRef.current.abort();
      }

      if (!q.trim()) {
        setResults(null);
        setTotal(0);
        setIsLoading(false);
        setError(null);
        return;
      }

      const controller = new AbortController();
      abortRef.current = controller;

      setIsLoading(true);
      setError(null);

      try {
        const sortParam = s === 'relevance' ? undefined : s;
        const params: SearchParams = {
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
        const envelope = await search(params, controller.signal);
        // Only update if this request wasn't aborted
        if (!controller.signal.aborted) {
          setResults(envelope.data);
          setTotal(envelope.meta.pagination?.total ?? 0);
          setIsLoading(false);
        }
      } catch (err: unknown) {
        if (err instanceof DOMException && err.name === 'AbortError') {
          // Request was cancelled — ignore
          return;
        }
        if (!controller.signal.aborted) {
          setError(err instanceof Error ? err.message : 'Search failed');
          setIsLoading(false);
        }
      }
    },
    [],
  );

  // Debounce query changes
  useEffect(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
    }

    timerRef.current = setTimeout(() => {
      executeSearch(query, filters, sort, page);
    }, debounceMs);

    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, [query, filters, sort, page, debounceMs, executeSearch]);

  // Reset page when query or filters change
  useEffect(() => {
    setPage(1);
  }, [query, filters, sort]);

  // Cleanup abort controller on unmount
  useEffect(() => {
    return () => {
      if (abortRef.current) {
        abortRef.current.abort();
      }
    };
  }, []);

  return {
    query,
    setQuery,
    filters,
    setFilters,
    sort,
    setSort,
    results,
    isLoading,
    error,
    page,
    setPage,
    total,
  };
}
