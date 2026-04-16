import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import SearchPage from '../SearchPage';

// Mock the useSearch hook
vi.mock('@/hooks/useSearch', () => ({
  default: vi.fn(),
}));

// Mock child components to isolate SearchPage behavior
vi.mock('@/components/search/SearchResultCard', () => ({
  default: ({ item }: { item: { id: string; title: string } }) => (
    <div data-testid={`result-${item.id}`}>{item.title}</div>
  ),
}));

vi.mock('@/components/search/SearchFilters', () => ({
  default: () => <div data-testid="search-filters">Filters</div>,
}));

vi.mock('@/components/common/LoadingSpinner', () => ({
  default: ({ message }: { message?: string }) => (
    <div data-testid="loading-spinner">{message}</div>
  ),
}));

import useSearch from '@/hooks/useSearch';

const defaultSearchReturn = {
  query: '',
  setQuery: vi.fn(),
  filters: {},
  setFilters: vi.fn(),
  sort: 'relevance' as const,
  setSort: vi.fn(),
  results: null,
  isLoading: false,
  error: null,
  page: 1,
  setPage: vi.fn(),
  total: 0,
};

function renderSearchPage() {
  return render(
    <MemoryRouter>
      <SearchPage />
    </MemoryRouter>,
  );
}

describe('SearchPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useSearch).mockReturnValue({ ...defaultSearchReturn });
  });

  it('renders without crashing', () => {
    renderSearchPage();
    expect(screen.getByText('Search')).toBeDefined();
  });

  it('renders search input', () => {
    renderSearchPage();
    const input = screen.getByPlaceholderText('Search for job posts, notices, and bulletins...');
    expect(input).toBeDefined();
  });

  it('renders the filter panel', () => {
    renderSearchPage();
    expect(screen.getByTestId('search-filters')).toBeDefined();
  });

  it('shows loading spinner when isLoading is true', () => {
    vi.mocked(useSearch).mockReturnValue({
      ...defaultSearchReturn,
      isLoading: true,
    });

    renderSearchPage();
    expect(screen.getByTestId('loading-spinner')).toBeDefined();
  });

  it('shows empty state when no query is entered', () => {
    renderSearchPage();
    expect(screen.getByText('Search for job posts, notices, and bulletins')).toBeDefined();
  });

  it('shows error state when error is present', () => {
    vi.mocked(useSearch).mockReturnValue({
      ...defaultSearchReturn,
      error: 'Something went wrong',
    });

    renderSearchPage();
    expect(screen.getByText('Something went wrong')).toBeDefined();
  });

  it('shows no results message when query has no matches', () => {
    vi.mocked(useSearch).mockReturnValue({
      ...defaultSearchReturn,
      query: 'nonexistent',
      results: [],
    });

    renderSearchPage();
    // The component uses &lsquo; and &rsquo; for quotes
    expect(screen.getByText(/No results found for/)).toBeDefined();
  });

  it('renders search results when results are returned', () => {
    vi.mocked(useSearch).mockReturnValue({
      ...defaultSearchReturn,
      query: 'test',
      results: [
        {
          id: 'result-1',
          content_type: 'JOB_POST',
          title: 'Test Job Post',
          author_name: 'admin',
          published_at: '2026-04-01T00:00:00Z',
          tags: [],
          view_count: 10,
          reply_count: 2,
          snippet: 'A snippet',
          highlight_title: '<mark>Test</mark> Job Post',
          relevance_score: 1.5,
        },
      ],
      total: 1,
    });

    renderSearchPage();
    expect(screen.getByTestId('result-result-1')).toBeDefined();
    expect(screen.getByText('1 result found')).toBeDefined();
  });

  it('renders pagination when there are multiple pages of results', () => {
    const results = Array.from({ length: 20 }, (_, i) => ({
      id: `r-${i}`,
      content_type: 'JOB_POST',
      title: `Result ${i}`,
      author_name: 'admin',
      published_at: null,
      tags: [],
      view_count: 0,
      reply_count: 0,
      snippet: '',
      highlight_title: `Result ${i}`,
      relevance_score: 1,
    }));

    vi.mocked(useSearch).mockReturnValue({
      ...defaultSearchReturn,
      query: 'test',
      results,
      total: 40, // 2 pages worth
    });

    renderSearchPage();
    expect(screen.getByText('Previous')).toBeDefined();
    expect(screen.getByText('Next')).toBeDefined();
    expect(screen.getByText('Page 1 of 2')).toBeDefined();
  });
});
