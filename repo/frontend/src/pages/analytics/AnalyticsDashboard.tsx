/* ------------------------------------------------------------------ */
/*  AnalyticsDashboard — KPI summary cards + quick links               */
/* ------------------------------------------------------------------ */

import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getKpiSummary } from '@/api/analytics';
import KpiCard from '@/components/analytics/KpiCard';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function AnalyticsDashboard() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['analytics', 'kpi-summary'],
    queryFn: getKpiSummary,
  });

  if (isLoading) return <LoadingSpinner message="Loading analytics..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load KPI summary: {(error as Error).message}
      </div>
    );
  }

  const kpi = data?.data;

  return (
    <div>
      <div className="page-header">
        <h1>Analytics Dashboard</h1>
      </div>

      {/* KPI cards */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
          gap: 16,
          marginBottom: 32,
        }}
      >
        <KpiCard title="Total Sales" value={kpi?.total_sales ?? 0} subtitle="Gross revenue" />
        <KpiCard title="Orders" value={kpi?.total_orders ?? 0} subtitle="Total orders" />
        <KpiCard
          title="Content Items"
          value={kpi?.content_count ?? 0}
          subtitle="Published content"
        />
        <KpiCard title="Exports" value={kpi?.export_count ?? 0} subtitle="Completed exports" />
        <KpiCard
          title="Retention Cases"
          value={kpi?.retention_count ?? 0}
          subtitle="Active retention"
        />
      </div>

      {/* Quick links */}
      <h2 style={{ fontSize: 16, marginBottom: 12 }}>Detailed Reports</h2>
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
          gap: 12,
        }}
      >
        <QuickLink to="/analytics/sales" label="Sales by Product" description="Breakdown by product, region, and channel" />
        <QuickLink to="/analytics/trends" label="Sales Trends" description="Time-series sales data with trend indicators" />
        <QuickLink to="/analytics/content" label="Content Analytics" description="Content volume by type and freshness" />
        <QuickLink to="/analytics/compliance" label="Compliance Metrics" description="Export, retention, and access metrics" />
      </div>
    </div>
  );
}

function QuickLink({
  to,
  label,
  description,
}: {
  to: string;
  label: string;
  description: string;
}) {
  return (
    <Link
      to={to}
      className="card"
      style={{
        textDecoration: 'none',
        color: 'inherit',
        padding: '16px',
        display: 'block',
        transition: 'box-shadow 0.15s',
      }}
    >
      <div style={{ fontWeight: 600, marginBottom: 4 }}>{label}</div>
      <div style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>{description}</div>
    </Link>
  );
}
