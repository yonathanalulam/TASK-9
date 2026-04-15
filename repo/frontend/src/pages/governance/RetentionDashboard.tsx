import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listRetentionCases,
  scheduleRetention,
  getRetentionStats,
} from '@/api/governance';
import type { RetentionCase } from '@/api/governance';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge styles                                                */
/* ------------------------------------------------------------------ */

const statusStyles: Record<string, React.CSSProperties> = {
  PENDING: { background: '#e2e8f0', color: '#475569' },
  SCHEDULED: { background: '#dbeafe', color: '#1e40af' },
  EXECUTING: { background: '#e0e7ff', color: '#3730a3' },
  COMPLETED: { background: '#dcfce7', color: '#166534' },
  FAILED: { background: '#fee2e2', color: '#991b1b' },
};

/* ------------------------------------------------------------------ */
/*  Stats card                                                         */
/* ------------------------------------------------------------------ */

function StatCard({
  label,
  value,
  color,
}: {
  label: string;
  value: number | string;
  color?: string;
}) {
  return (
    <div className="card" style={{ padding: '16px 20px' }}>
      <div
        style={{
          fontSize: 12,
          color: 'var(--color-text-muted)',
          marginBottom: 4,
        }}
      >
        {label}
      </div>
      <div style={{ fontSize: 22, fontWeight: 700, color: color || undefined }}>
        {value}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Table columns                                                      */
/* ------------------------------------------------------------------ */

function buildColumns(
  onSchedule: (id: string) => void,
  schedulingId: string | null,
): Column<RetentionCase>[] {
  return [
    {
      key: 'entity_type',
      header: 'Entity Type',
      width: '120px',
      render: (c) => (
        <span style={{ fontWeight: 500, textTransform: 'capitalize' }}>
          {c.entity_type}
        </span>
      ),
    },
    {
      key: 'entity_name',
      header: 'Entity',
      render: (c) => c.entity_name || c.entity_id.slice(0, 12) + '...',
    },
    {
      key: 'reason',
      header: 'Reason',
      render: (c) => (
        <span style={{ fontSize: 13 }}>{c.reason}</span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      width: '120px',
      render: (c) => (
        <span className="badge" style={statusStyles[c.status] ?? {}}>
          {c.status}
        </span>
      ),
    },
    {
      key: 'scheduled_for',
      header: 'Scheduled For',
      width: '160px',
      render: (c) => (
        <span
          style={{
            fontSize: 13,
            color: c.scheduled_for ? undefined : 'var(--color-text-muted)',
          }}
        >
          {c.scheduled_for ? new Date(c.scheduled_for).toLocaleString() : '--'}
        </span>
      ),
    },
    {
      key: 'created_at',
      header: 'Created',
      width: '150px',
      render: (c) => (
        <span style={{ fontSize: 13 }}>
          {new Date(c.created_at).toLocaleString()}
        </span>
      ),
    },
    {
      key: 'actions',
      header: '',
      width: '100px',
      render: (c) =>
        c.status === 'PENDING' ? (
          <button
            className="btn btn-primary"
            onClick={() => onSchedule(c.id)}
            disabled={schedulingId === c.id}
            style={{ fontSize: 12, padding: '4px 10px' }}
          >
            {schedulingId === c.id ? 'Scheduling...' : 'Schedule'}
          </button>
        ) : null,
    },
  ];
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export default function RetentionDashboard() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const perPage = 20;

  const statsQuery = useQuery({
    queryKey: ['retention-stats'],
    queryFn: getRetentionStats,
  });

  const casesQuery = useQuery({
    queryKey: ['retention-cases', page, perPage, statusFilter],
    queryFn: () =>
      listRetentionCases({
        page,
        per_page: perPage,
        status: statusFilter || undefined,
      }),
  });

  const scheduleMutation = useMutation({
    mutationFn: (id: string) => scheduleRetention(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['retention-cases'] });
      queryClient.invalidateQueries({ queryKey: ['retention-stats'] });
    },
  });

  const stats = statsQuery.data?.data;

  const columns = buildColumns(
    (id) => scheduleMutation.mutate(id),
    scheduleMutation.isPending ? (scheduleMutation.variables as string) : null,
  );

  return (
    <div>
      <div className="page-header">
        <h1>Retention Dashboard</h1>
      </div>

      {/* Stats cards */}
      {statsQuery.isLoading ? (
        <LoadingSpinner message="Loading retention stats..." />
      ) : statsQuery.isError ? (
        <div style={{ color: 'var(--color-danger)', padding: 12, marginBottom: 16 }}>
          Failed to load stats: {(statsQuery.error as Error).message}
        </div>
      ) : stats ? (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))',
            gap: 12,
            marginBottom: 20,
          }}
        >
          <StatCard label="Total Cases" value={stats.total_cases} />
          <StatCard label="Pending" value={stats.pending_count} color="#475569" />
          <StatCard label="Scheduled" value={stats.scheduled_count} color="#1e40af" />
          <StatCard label="Executing" value={stats.executing_count} color="#3730a3" />
          <StatCard label="Completed" value={stats.completed_count} color="#166534" />
          <StatCard label="Failed" value={stats.failed_count} color={stats.failed_count > 0 ? '#dc2626' : undefined} />
        </div>
      ) : null}

      {stats?.next_scheduled_deletion && (
        <div
          className="card"
          style={{
            padding: '10px 16px',
            marginBottom: 16,
            fontSize: 13,
            display: 'flex',
            alignItems: 'center',
            gap: 8,
          }}
        >
          <span style={{ fontWeight: 600 }}>Next scheduled deletion:</span>
          <span>{new Date(stats.next_scheduled_deletion).toLocaleString()}</span>
        </div>
      )}

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
            <option value="PENDING">Pending</option>
            <option value="SCHEDULED">Scheduled</option>
            <option value="EXECUTING">Executing</option>
            <option value="COMPLETED">Completed</option>
            <option value="FAILED">Failed</option>
          </select>
        </div>
      </div>

      {/* Cases table */}
      {casesQuery.isLoading ? (
        <LoadingSpinner message="Loading retention cases..." />
      ) : casesQuery.isError ? (
        <div style={{ color: 'var(--color-danger)', padding: 20 }}>
          Failed to load retention cases: {(casesQuery.error as Error).message}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={casesQuery.data?.data ?? []}
          page={page}
          totalPages={casesQuery.data?.meta?.pagination?.total_pages ?? 1}
          onPageChange={setPage}
          keyExtractor={(c) => c.id}
        />
      )}

      {/* Mutation error toast */}
      {scheduleMutation.isError && (
        <div
          style={{
            position: 'fixed',
            bottom: 20,
            right: 20,
            background: '#fee2e2',
            color: '#991b1b',
            padding: '10px 16px',
            borderRadius: 6,
            fontSize: 13,
            boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
          }}
        >
          Schedule failed: {(scheduleMutation.error as Error).message}
        </div>
      )}
    </div>
  );
}
