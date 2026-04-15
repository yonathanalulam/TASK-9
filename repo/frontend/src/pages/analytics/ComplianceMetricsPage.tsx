/* ------------------------------------------------------------------ */
/*  ComplianceMetricsPage — export, retention, sensitive access stats   */
/* ------------------------------------------------------------------ */

import { useQuery } from '@tanstack/react-query';
import { getKpiSummary } from '@/api/analytics';
import KpiCard from '@/components/analytics/KpiCard';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function ComplianceMetricsPage() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['analytics', 'kpi-summary'],
    queryFn: getKpiSummary,
  });

  if (isLoading) return <LoadingSpinner message="Loading compliance metrics..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load compliance metrics: {(error as Error).message}
      </div>
    );
  }

  const kpi = data?.data;

  return (
    <div>
      <div className="page-header">
        <h1>Compliance Metrics</h1>
      </div>

      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
          gap: 16,
        }}
      >
        <KpiCard
          title="Export Activity"
          value={kpi?.export_count ?? 0}
          subtitle="Total completed data exports"
        />
        <KpiCard
          title="Retention Executions"
          value={kpi?.retention_count ?? 0}
          subtitle="Retention policy executions"
        />
        <KpiCard
          title="Sensitive Field Access"
          value={kpi?.sensitive_access_count ?? 0}
          subtitle="Logged access to sensitive fields"
        />
      </div>

      {/* Info note */}
      <div
        className="card"
        style={{
          marginTop: 24,
          padding: '16px 20px',
          background: 'var(--color-bg-muted, #f8fafc)',
          fontSize: 13,
          color: 'var(--color-text-muted)',
        }}
      >
        These metrics are aggregated from the platform's audit log and governance
        subsystems. For detailed compliance reports, visit the{' '}
        <a href="/compliance-reports" style={{ color: 'var(--color-primary)' }}>
          Compliance Reports
        </a>{' '}
        page.
      </div>
    </div>
  );
}
