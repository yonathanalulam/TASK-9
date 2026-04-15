/* ------------------------------------------------------------------ */
/*  ScrapeRunDetailPage — run stats + summary                          */
/* ------------------------------------------------------------------ */

import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getScrapeRun } from '@/api/scraping';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function ScrapeRunDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['scraping', 'run', id],
    queryFn: () => getScrapeRun(id!),
    enabled: !!id,
  });

  if (isLoading) return <LoadingSpinner message="Loading scrape run..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load scrape run: {(error as Error).message}
      </div>
    );
  }

  const run = data!.data;

  const statusBadge = (status: string) => {
    switch (status) {
      case 'COMPLETED':
        return 'badge badge-success';
      case 'FAILED':
        return 'badge badge-danger';
      case 'RUNNING':
        return 'badge badge-warning';
      default:
        return 'badge badge-info';
    }
  };

  return (
    <div>
      <div className="page-header">
        <h1>Scrape Run</h1>
        <Link
          to={`/scraping/sources/${run.source_id}`}
          className="btn btn-secondary"
          style={{ textDecoration: 'none', fontSize: 13, padding: '6px 14px' }}
        >
          Back to Source
        </Link>
      </div>

      {/* Run stats */}
      <div
        className="card"
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))',
          gap: 16,
          marginBottom: 24,
        }}
      >
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Source
          </div>
          <div style={{ fontSize: 14, fontWeight: 500 }}>{run.source_name}</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Status
          </div>
          <span className={statusBadge(run.status)}>{run.status}</span>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Items Found
          </div>
          <div style={{ fontSize: 20, fontWeight: 700 }}>{run.items_found}</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            New
          </div>
          <div style={{ fontSize: 20, fontWeight: 700, color: 'var(--color-success, #16a34a)' }}>
            {run.items_new}
          </div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Updated
          </div>
          <div style={{ fontSize: 20, fontWeight: 700, color: '#3b82f6' }}>{run.items_updated}</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Failed
          </div>
          <div
            style={{
              fontSize: 20,
              fontWeight: 700,
              color: run.items_failed > 0 ? 'var(--color-danger)' : undefined,
            }}
          >
            {run.items_failed}
          </div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Duration
          </div>
          <div style={{ fontSize: 14 }}>
            {run.duration_ms !== null ? `${(run.duration_ms / 1000).toFixed(1)}s` : '--'}
          </div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Started
          </div>
          <div style={{ fontSize: 13 }}>{new Date(run.started_at).toLocaleString()}</div>
        </div>
      </div>

      {run.error && (
        <div
          className="card"
          style={{
            marginBottom: 24,
            padding: '12px 16px',
            borderLeft: '4px solid var(--color-danger)',
            fontSize: 13,
            color: 'var(--color-danger)',
          }}
        >
          <strong>Error:</strong> {run.error}
        </div>
      )}
    </div>
  );
}
