/* ------------------------------------------------------------------ */
/*  ContentAnalyticsPage — content volume by type + freshness           */
/* ------------------------------------------------------------------ */

import { useQuery } from '@tanstack/react-query';
import { getContentVolume } from '@/api/analytics';
import type { ContentVolume } from '@/api/analytics';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const typeLabels: Record<string, string> = {
  JOB_POST: 'Job Posts',
  OPERATIONAL_NOTICE: 'Operational Notices',
  VENDOR_BULLETIN: 'Vendor Bulletins',
};

const typeColors: Record<string, string> = {
  JOB_POST: '#3b82f6',
  OPERATIONAL_NOTICE: '#8b5cf6',
  VENDOR_BULLETIN: '#f59e0b',
};

export default function ContentAnalyticsPage() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['analytics', 'content-volume'],
    queryFn: getContentVolume,
  });

  if (isLoading) return <LoadingSpinner message="Loading content analytics..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load content analytics: {(error as Error).message}
      </div>
    );
  }

  const volumes: ContentVolume[] = data?.data ?? [];
  const totalContent = volumes.reduce((sum, v) => sum + v.count, 0);

  return (
    <div>
      <div className="page-header">
        <h1>Content Analytics</h1>
      </div>

      {/* Volume cards */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
          gap: 16,
          marginBottom: 24,
        }}
      >
        {volumes.map((v) => {
          const label = typeLabels[v.content_type] ?? v.content_type;
          const color = typeColors[v.content_type] ?? '#64748b';
          const pct = totalContent > 0 ? ((v.count / totalContent) * 100).toFixed(1) : '0';

          return (
            <div
              key={v.content_type}
              className="card"
              style={{ padding: '20px', borderLeft: `4px solid ${color}` }}
            >
              <div
                style={{
                  fontSize: 12,
                  fontWeight: 600,
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  color: 'var(--color-text-muted)',
                  marginBottom: 8,
                }}
              >
                {label}
              </div>
              <div style={{ fontSize: 28, fontWeight: 700 }}>{v.count.toLocaleString()}</div>
              <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 4 }}>
                {pct}% of total content
              </div>
            </div>
          );
        })}
      </div>

      {/* Freshness indicator */}
      <div className="card" style={{ padding: '20px' }}>
        <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 12 }}>Content Freshness</h3>
        {volumes.length === 0 ? (
          <p style={{ color: 'var(--color-text-muted)' }}>No content data available.</p>
        ) : (
          <div style={{ display: 'flex', gap: 4, height: 24, borderRadius: 4, overflow: 'hidden' }}>
            {volumes.map((v) => {
              const color = typeColors[v.content_type] ?? '#64748b';
              const widthPct = totalContent > 0 ? (v.count / totalContent) * 100 : 0;
              return (
                <div
                  key={v.content_type}
                  title={`${typeLabels[v.content_type] ?? v.content_type}: ${v.count}`}
                  style={{
                    width: `${widthPct}%`,
                    background: color,
                    minWidth: widthPct > 0 ? 4 : 0,
                  }}
                />
              );
            })}
          </div>
        )}
        <div
          style={{
            display: 'flex',
            gap: 16,
            marginTop: 12,
            flexWrap: 'wrap',
          }}
        >
          {volumes.map((v) => {
            const color = typeColors[v.content_type] ?? '#64748b';
            return (
              <span
                key={v.content_type}
                style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}
              >
                <span
                  style={{
                    display: 'inline-block',
                    width: 10,
                    height: 10,
                    borderRadius: 2,
                    background: color,
                  }}
                />
                {typeLabels[v.content_type] ?? v.content_type}
              </span>
            );
          })}
        </div>
      </div>
    </div>
  );
}
