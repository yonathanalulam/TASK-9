/* ------------------------------------------------------------------ */
/*  SourceHealthDashboard — grid of sources with traffic-light status   */
/* ------------------------------------------------------------------ */

import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getHealthDashboard } from '@/api/scraping';
import type { SourceHealth } from '@/api/scraping';
import SourceStatusIndicator from '@/components/scraping/SourceStatusIndicator';
import type { SourceStatus } from '@/components/scraping/SourceStatusIndicator';
import HealthEventTimeline from '@/components/scraping/HealthEventTimeline';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const statusColors: Record<string, string> = {
  ACTIVE: '#16a34a',
  DEGRADED: '#eab308',
  PAUSED: '#f97316',
  DISABLED: '#dc2626',
};

export default function SourceHealthDashboard() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['scraping', 'health-dashboard'],
    queryFn: getHealthDashboard,
    refetchInterval: 30_000,
  });

  if (isLoading) return <LoadingSpinner message="Loading health dashboard..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load health dashboard: {(error as Error).message}
      </div>
    );
  }

  const dashboard = data!.data;

  return (
    <div>
      <div className="page-header">
        <h1>Source Health Dashboard</h1>
      </div>

      {/* Summary counts */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(4, 1fr)',
          gap: 12,
          marginBottom: 24,
        }}
      >
        {(
          [
            { label: 'Active', count: dashboard.active, color: statusColors.ACTIVE },
            { label: 'Degraded', count: dashboard.degraded, color: statusColors.DEGRADED },
            { label: 'Paused', count: dashboard.paused, color: statusColors.PAUSED },
            { label: 'Disabled', count: dashboard.disabled, color: statusColors.DISABLED },
          ] as const
        ).map((s) => (
          <div
            key={s.label}
            className="card"
            style={{
              textAlign: 'center',
              padding: '16px',
              borderTop: `3px solid ${s.color}`,
            }}
          >
            <div style={{ fontSize: 28, fontWeight: 700 }}>{s.count}</div>
            <div
              style={{
                fontSize: 12,
                fontWeight: 600,
                textTransform: 'uppercase',
                color: 'var(--color-text-muted)',
              }}
            >
              {s.label}
            </div>
          </div>
        ))}
      </div>

      {/* Source grid with traffic-light indicators */}
      <h2 style={{ fontSize: 16, marginBottom: 12 }}>All Sources</h2>
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
          gap: 12,
          marginBottom: 24,
        }}
      >
        {dashboard.sources.map((src: SourceHealth) => (
          <Link
            key={src.source_id}
            to={`/scraping/sources/${src.source_id}`}
            className="card"
            style={{
              textDecoration: 'none',
              color: 'inherit',
              padding: '16px',
              display: 'flex',
              alignItems: 'center',
              gap: 12,
              borderLeft: `4px solid ${statusColors[src.status] ?? '#94a3b8'}`,
            }}
          >
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontWeight: 600, marginBottom: 4 }}>{src.source_name}</div>
              <SourceStatusIndicator status={src.status as SourceStatus} />
            </div>
            <div style={{ textAlign: 'right', fontSize: 12, color: 'var(--color-text-muted)' }}>
              <div>{src.uptime.toFixed(1)}% up</div>
              <div>{src.avg_response_ms}ms avg</div>
              <div>{src.error_rate.toFixed(2)}% err</div>
            </div>
          </Link>
        ))}

        {dashboard.sources.length === 0 && (
          <div
            className="card"
            style={{ padding: 32, textAlign: 'center', color: 'var(--color-text-muted)' }}
          >
            No sources configured.
          </div>
        )}
      </div>

      {/* Recent health events */}
      <div className="card">
        <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 12 }}>Recent Health Events</h3>
        <HealthEventTimeline events={dashboard.recent_events} />
      </div>
    </div>
  );
}
