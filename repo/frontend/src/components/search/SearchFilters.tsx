import { useState } from 'react';
import type { SearchFilters as SearchFiltersType, SearchSortMode } from '@/hooks/useSearch';

interface SearchFiltersProps {
  filters: SearchFiltersType;
  onFiltersChange: (f: SearchFiltersType) => void;
  sort: SearchSortMode;
  onSortChange: (s: SearchSortMode) => void;
}

const CONTENT_TYPES = [
  { value: 'JOB_POST', label: 'Job Post' },
  { value: 'OPERATIONAL_NOTICE', label: 'Operational Notice' },
  { value: 'VENDOR_BULLETIN', label: 'Vendor Bulletin' },
];

const SORT_MODES: { value: SearchSortMode; label: string }[] = [
  { value: 'relevance', label: 'Relevance' },
  { value: 'newest', label: 'Newest' },
  { value: 'most_viewed', label: 'Most Viewed' },
  { value: 'highest_reply', label: 'Most Replies' },
];

export default function SearchFilters({
  filters,
  onFiltersChange,
  sort,
  onSortChange,
}: SearchFiltersProps) {
  const [collapsed, setCollapsed] = useState(false);

  const [store, setStore] = useState(filters.store ?? '');
  const [region, setRegion] = useState(filters.region ?? '');
  const [dateFrom, setDateFrom] = useState(filters.dateFrom ?? '');
  const [dateTo, setDateTo] = useState(filters.dateTo ?? '');
  const [contentTypes, setContentTypes] = useState<string[]>(
    filters.contentTypes ?? [],
  );

  const handleTypeToggle = (type: string) => {
    setContentTypes((prev) =>
      prev.includes(type) ? prev.filter((t) => t !== type) : [...prev, type],
    );
  };

  const handleApply = () => {
    onFiltersChange({
      store: store || undefined,
      region: region || undefined,
      dateFrom: dateFrom || undefined,
      dateTo: dateTo || undefined,
      contentTypes: contentTypes.length > 0 ? contentTypes : undefined,
    });
  };

  const handleClear = () => {
    setStore('');
    setRegion('');
    setDateFrom('');
    setDateTo('');
    setContentTypes([]);
    onFiltersChange({});
    onSortChange('relevance');
  };

  return (
    <div className="card" style={{ padding: 16, alignSelf: 'start' }}>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: collapsed ? 0 : 16,
        }}
      >
        <h3 style={{ fontSize: 14, fontWeight: 600 }}>Filters</h3>
        <button
          style={{
            background: 'none',
            border: 'none',
            color: 'var(--color-primary)',
            fontSize: 12,
            cursor: 'pointer',
          }}
          onClick={() => setCollapsed(!collapsed)}
        >
          {collapsed ? 'Show' : 'Hide'}
        </button>
      </div>

      {!collapsed && (
        <>
          {/* Store filter */}
          <div className="form-group">
            <label>Store ID</label>
            <input
              type="text"
              placeholder="Filter by store UUID"
              value={store}
              onChange={(e) => setStore(e.target.value)}
              data-testid="search-filter-store"
            />
          </div>

          {/* Region filter */}
          <div className="form-group">
            <label>Region ID</label>
            <input
              type="text"
              placeholder="Filter by region UUID"
              value={region}
              onChange={(e) => setRegion(e.target.value)}
              data-testid="search-filter-region"
            />
          </div>

          {/* Date range */}
          <div className="form-group">
            <label>Date From</label>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
            />
          </div>
          <div className="form-group">
            <label>Date To</label>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
            />
          </div>

          {/* Content type checkboxes */}
          <div className="form-group">
            <label>Content Type</label>
            {CONTENT_TYPES.map((ct) => (
              <label
                key={ct.value}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 8,
                  fontWeight: 400,
                  fontSize: 13,
                  color: 'var(--color-text)',
                  marginBottom: 6,
                  cursor: 'pointer',
                }}
              >
                <input
                  type="checkbox"
                  checked={contentTypes.includes(ct.value)}
                  onChange={() => handleTypeToggle(ct.value)}
                />
                {ct.label}
              </label>
            ))}
          </div>

          {/* Sort mode */}
          <div className="form-group">
            <label>Sort By</label>
            {SORT_MODES.map((sm) => (
              <label
                key={sm.value}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 8,
                  fontWeight: 400,
                  fontSize: 13,
                  color: 'var(--color-text)',
                  marginBottom: 6,
                  cursor: 'pointer',
                }}
              >
                <input
                  type="radio"
                  name="sortMode"
                  value={sm.value}
                  checked={sort === sm.value}
                  onChange={() => onSortChange(sm.value)}
                />
                {sm.label}
              </label>
            ))}
          </div>

          {/* Actions */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 4 }}>
            <button className="btn btn-primary" onClick={handleApply} style={{ width: '100%' }}>
              Apply Filters
            </button>
            <button
              style={{
                background: 'none',
                border: 'none',
                color: 'var(--color-primary)',
                fontSize: 13,
                cursor: 'pointer',
                textAlign: 'center',
              }}
              onClick={handleClear}
            >
              Clear Filters
            </button>
          </div>
        </>
      )}
    </div>
  );
}
