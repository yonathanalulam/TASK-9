/* ------------------------------------------------------------------ */
/*  SourceListPage — table of scraping sources with actions             */
/* ------------------------------------------------------------------ */

import { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listSources,
  pauseSource,
  resumeSource,
  disableSource,
} from '@/api/scraping';
import type { ScrapingSource } from '@/api/scraping';
import SourceStatusIndicator from '@/components/scraping/SourceStatusIndicator';
import type { SourceStatus } from '@/components/scraping/SourceStatusIndicator';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function SourceListPage() {
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const perPage = 20;
  const queryClient = useQueryClient();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['scraping', 'sources', page, perPage, statusFilter],
    queryFn: () =>
      listSources({
        page,
        per_page: perPage,
      }),
  });

  const invalidate = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['scraping', 'sources'] });
  }, [queryClient]);

  const pauseMut = useMutation({
    mutationFn: (id: string) => pauseSource(id),
    onSuccess: invalidate,
  });

  const resumeMut = useMutation({
    mutationFn: (id: string) => resumeSource(id),
    onSuccess: invalidate,
  });

  const disableMut = useMutation({
    mutationFn: (id: string) => disableSource(id),
    onSuccess: invalidate,
  });

  if (isLoading) return <LoadingSpinner message="Loading sources..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load sources: {(error as Error).message}
      </div>
    );
  }

  const sources: ScrapingSource[] = data?.data ?? [];
  const totalPages = data?.meta?.pagination?.total_pages ?? 1;

  return (
    <div>
      <div className="page-header">
        <h1>Scraping Sources</h1>
        <Link
          to="/scraping/sources/new"
          className="btn btn-primary"
          style={{ textDecoration: 'none', fontSize: 13, padding: '6px 14px' }}
        >
          Add Source
        </Link>
      </div>

      {/* Filters */}
      <div
        className="card"
        style={{
          display: 'flex',
          gap: 16,
          alignItems: 'center',
          marginBottom: 16,
          padding: '12px 16px',
        }}
      >
        <div className="form-group" style={{ marginBottom: 0, width: 180 }}>
          <label>Status</label>
          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All Statuses</option>
            <option value="ACTIVE">Active</option>
            <option value="DEGRADED">Degraded</option>
            <option value="PAUSED">Paused</option>
            <option value="DISABLED">Disabled</option>
          </select>
        </div>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Base URL</th>
              <th>Type</th>
              <th style={{ width: 130 }}>Status</th>
              <th style={{ width: 150 }}>Last Scrape</th>
              <th style={{ width: 90, textAlign: 'right' }}>Rate Limit</th>
              <th style={{ width: 200 }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {sources.length === 0 ? (
              <tr>
                <td
                  colSpan={7}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No scraping sources found.
                </td>
              </tr>
            ) : (
              sources.map((src) => (
                <tr key={src.id}>
                  <td>
                    <Link
                      to={`/scraping/sources/${src.id}`}
                      style={{ fontWeight: 500, color: 'var(--color-primary)' }}
                    >
                      {src.name}
                    </Link>
                  </td>
                  <td style={{ fontSize: 13, maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {src.base_url}
                  </td>
                  <td>{src.type}</td>
                  <td>
                    <SourceStatusIndicator status={src.status as SourceStatus} />
                  </td>
                  <td style={{ fontSize: 13 }}>
                    {src.last_scrape_at
                      ? new Date(src.last_scrape_at).toLocaleString()
                      : '--'}
                  </td>
                  <td style={{ textAlign: 'right' }}>{src.rate_limit}/min</td>
                  <td>
                    <div style={{ display: 'flex', gap: 6 }}>
                      {(src.status === 'ACTIVE' || src.status === 'DEGRADED') && (
                        <button
                          className="btn btn-secondary"
                          onClick={() => pauseMut.mutate(src.id)}
                          disabled={pauseMut.isPending}
                          style={{ fontSize: 11, padding: '3px 8px' }}
                        >
                          Pause
                        </button>
                      )}
                      {src.status === 'PAUSED' && (
                        <button
                          className="btn btn-secondary"
                          onClick={() => resumeMut.mutate(src.id)}
                          disabled={resumeMut.isPending}
                          style={{ fontSize: 11, padding: '3px 8px' }}
                        >
                          Resume
                        </button>
                      )}
                      {src.status !== 'DISABLED' && (
                        <button
                          className="btn btn-secondary"
                          onClick={() => {
                            if (window.confirm(`Disable source "${src.name}"?`)) {
                              disableMut.mutate(src.id);
                            }
                          }}
                          disabled={disableMut.isPending}
                          style={{
                            fontSize: 11,
                            padding: '3px 8px',
                            color: 'var(--color-danger)',
                          }}
                        >
                          Disable
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: 8,
            marginTop: 16,
          }}
        >
          <button
            className="btn btn-secondary"
            disabled={page <= 1}
            onClick={() => setPage((p) => p - 1)}
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
            onClick={() => setPage((p) => p + 1)}
            style={{ padding: '6px 12px', fontSize: 13 }}
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
