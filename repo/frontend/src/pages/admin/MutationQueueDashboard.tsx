/* ------------------------------------------------------------------ */
/*  MutationQueueDashboard — admin view of server-side mutation logs   */
/* ------------------------------------------------------------------ */

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { listMutationLogs } from '@/api/mutations';
import type { MutationLog } from '@/api/mutations';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge mapping                                               */
/* ------------------------------------------------------------------ */

const statusBadge: Record<string, string> = {
  APPLIED: 'badge badge-success',
  CONFLICT: 'badge badge-warning',
  REJECTED: 'badge badge-danger',
  PENDING: 'badge badge-info',
};

/* ------------------------------------------------------------------ */
/*  Table columns                                                      */
/* ------------------------------------------------------------------ */

const columns: Column<MutationLog>[] = [
  {
    key: 'mutation_id',
    header: 'Mutation ID',
    width: '200px',
    render: (m) => (
      <code style={{ fontSize: 12 }}>{m.mutation_id.slice(0, 12)}...</code>
    ),
  },
  {
    key: 'entity_type',
    header: 'Entity Type',
    width: '120px',
    render: (m) => m.entity_type,
  },
  {
    key: 'operation',
    header: 'Operation',
    width: '100px',
    render: (m) => (
      <code style={{ fontSize: 13 }}>{m.operation}</code>
    ),
  },
  {
    key: 'status',
    header: 'Status',
    width: '110px',
    render: (m) => (
      <span className={statusBadge[m.status] ?? 'badge'}>
        {m.status}
      </span>
    ),
  },
  {
    key: 'received_at',
    header: 'Received',
    width: '160px',
    render: (m) => new Date(m.received_at).toLocaleString(),
  },
  {
    key: 'processed_at',
    header: 'Processed',
    width: '160px',
    render: (m) => (m.processed_at ? new Date(m.processed_at).toLocaleString() : '-'),
  },
];

/* ------------------------------------------------------------------ */
/*  Filter options                                                     */
/* ------------------------------------------------------------------ */

const STATUS_OPTIONS = ['', 'APPLIED', 'CONFLICT', 'REJECTED', 'PENDING'];

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function MutationQueueDashboard() {
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const perPage = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['mutation-logs', page, perPage, statusFilter],
    queryFn: () =>
      listMutationLogs({
        page,
        per_page: perPage,
      }),
  });

  if (isLoading) return <LoadingSpinner message="Loading mutation logs..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load mutation logs: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Mutation Queue</h1>
      </div>

      {/* Filters */}
      <div style={{ marginBottom: 16, display: 'flex', alignItems: 'center', gap: 12 }}>
        <label style={{ fontSize: 13, fontWeight: 500, color: 'var(--color-text-muted)' }}>
          Status:
        </label>
        <select
          value={statusFilter}
          onChange={(e) => {
            setStatusFilter(e.target.value);
            setPage(1);
          }}
          style={{
            padding: '6px 10px',
            border: '1px solid var(--color-border)',
            borderRadius: 'var(--radius)',
            fontSize: 13,
          }}
        >
          {STATUS_OPTIONS.map((opt) => (
            <option key={opt} value={opt}>
              {opt || 'All'}
            </option>
          ))}
        </select>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(m) => m.id}
      />
    </div>
  );
}
