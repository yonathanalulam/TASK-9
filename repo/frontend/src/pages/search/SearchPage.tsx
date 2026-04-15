import useSearch from '@/hooks/useSearch';
import SearchResultCard from '@/components/search/SearchResultCard';
import SearchFilters from '@/components/search/SearchFilters';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function SearchPage() {
  const {
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
  } = useSearch(300);

  const perPage = 20;
  const totalPages = total > 0 ? Math.ceil(total / perPage) : 0;

  return (
    <div>
      <div className="page-header">
        <h1>Search</h1>
      </div>

      {/* Search bar */}
      <div className="card" style={{ marginBottom: 16 }}>
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search for job posts, notices, and bulletins..."
          style={{
            width: '100%',
            padding: '12px 16px',
            border: '1px solid var(--color-border)',
            borderRadius: 'var(--radius)',
            fontSize: 15,
            outline: 'none',
          }}
        />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '260px 1fr', gap: 16 }}>
        {/* Filter panel */}
        <SearchFilters
          filters={filters}
          onFiltersChange={setFilters}
          sort={sort}
          onSortChange={setSort}
        />

        {/* Results area */}
        <div>
          {/* Loading state */}
          {isLoading && <LoadingSpinner message="Searching..." />}

          {/* Error state */}
          {error && !isLoading && (
            <div
              style={{
                color: 'var(--color-danger)',
                padding: 20,
                textAlign: 'center',
              }}
            >
              {error}
            </div>
          )}

          {/* Empty state -- no query */}
          {!query.trim() && !isLoading && !error && (
            <div
              className="card"
              style={{
                textAlign: 'center',
                padding: 40,
                color: 'var(--color-text-muted)',
              }}
            >
              Search for job posts, notices, and bulletins
            </div>
          )}

          {/* No results state */}
          {query.trim() && !isLoading && !error && results && results.length === 0 && (
            <div
              className="card"
              style={{
                textAlign: 'center',
                padding: 40,
                color: 'var(--color-text-muted)',
              }}
            >
              No results found for &lsquo;{query}&rsquo;
            </div>
          )}

          {/* Results list */}
          {results && results.length > 0 && !isLoading && (
            <>
              <div
                style={{
                  fontSize: 13,
                  color: 'var(--color-text-muted)',
                  marginBottom: 12,
                }}
              >
                {total} result{total !== 1 ? 's' : ''} found
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {results.map((item) => (
                  <SearchResultCard key={item.id} item={item} />
                ))}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 8,
                    marginTop: 20,
                  }}
                >
                  <button
                    className="btn btn-secondary"
                    disabled={page <= 1}
                    onClick={() => setPage(page - 1)}
                    style={{ padding: '6px 12px', fontSize: 13 }}
                  >
                    Previous
                  </button>
                  <span style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
                    Page {page} of {totalPages}
                  </span>
                  <button
                    className="btn btn-secondary"
                    disabled={page >= totalPages}
                    onClick={() => setPage(page + 1)}
                    style={{ padding: '6px 12px', fontSize: 13 }}
                  >
                    Next
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
