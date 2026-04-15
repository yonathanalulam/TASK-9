import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listImports } from '@/api/imports';
import type { Import } from '@/api/imports';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge                                                       */
/* ------------------------------------------------------------------ */

const statusStyles: Record<string, React.CSSProperties> = {
  PENDING: { background: '#e2e8f0', color: '#475569' },
  VALIDATING: { background: '#dbeafe', color: '#1e40af' },
  VALIDATED: { background: '#dcfce7', color: '#166534' },
  PROCESSING: { background: '#e0e7ff', color: '#3730a3' },
  COMPLETED: { background: '#dcfce7', color: '#166534' },
  FAILED: { background: '#fee2e2', color: '#991b1b' },
};

/* ------------------------------------------------------------------ */
/*  Progress bar                                                       */
/* ------------------------------------------------------------------ */

function ProgressBar({ processed, total }: { processed: number; total: number }) {
  const pct = total > 0 ? Math.round((processed / total) * 100) : 0;
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <div
        style={{
          flex: 1,
          height: 6,
          background: '#e2e8f0',
          borderRadius: 3,
          overflow: 'hidden',
          minWidth: 60,
        }}
      >
        <div
          style={{
            width: `${pct}%`,
            height: '100%',
            background: pct === 100 ? '#22c55e' : '#3b82f6',
            borderRadius: 3,
            transition: 'width 0.3s ease',
          }}
        />
      </div>
      <span style={{ fontSize: 12, color: 'var(--color-text-muted)', whiteSpace: 'nowrap' }}>
        {processed}/{total}
      </span>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Table columns                                                      */
/* ------------------------------------------------------------------ */

const columns: Column<Import>[] = [
  {
    key: 'filename',
    header: 'Filename',
    render: (b) => (
      <Link to={`/imports/${b.id}`} style={{ fontWeight: 500 }}>
        {b.filename}
      </Link>
    ),
  },
  {
    key: 'format',
    header: 'Format',
    width: '80px',
    render: (b) => b.format,
  },
  {
    key: 'progress',
    header: 'Progress',
    width: '180px',
    render: (b) => <ProgressBar processed={b.processed_items} total={b.total_items} />,
  },
  {
    key: 'duplicates',
    header: 'Dupes',
    width: '80px',
    render: (b) => (
      <span style={{ color: b.duplicate_items > 0 ? '#d97706' : 'inherit' }}>
        {b.duplicate_items}
      </span>
    ),
  },
  {
    key: 'errors',
    header: 'Errors',
    width: '80px',
    render: (b) => (
      <span style={{ color: b.error_items > 0 ? '#dc2626' : 'inherit' }}>
        {b.error_items}
      </span>
    ),
  },
  {
    key: 'status',
    header: 'Status',
    width: '120px',
    render: (b) => (
      <span className="badge" style={statusStyles[b.status] ?? {}}>
        {b.status}
      </span>
    ),
  },
  {
    key: 'uploaded_by',
    header: 'Uploaded By',
    width: '130px',
    render: (b) => b.uploaded_by,
  },
  {
    key: 'created_at',
    header: 'Created',
    width: '150px',
    render: (b) => new Date(b.created_at).toLocaleString(),
  },
];

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function ImportBatchListPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState('');
  const perPage = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['import-batches', page, perPage, status],
    queryFn: () =>
      listImports({
        page,
        per_page: perPage,
        status: status || undefined,
      }),
  });

  if (isLoading) return <LoadingSpinner message="Loading import batches..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load import batches: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Import Batches</h1>
        <Link
          to="/imports/upload"
          className="btn btn-primary"
          style={{ textDecoration: 'none', fontSize: 13, padding: '6px 14px' }}
        >
          Upload Import
        </Link>
      </div>

      {/* Filter panel */}
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
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All Statuses</option>
            <option value="PENDING">Pending</option>
            <option value="VALIDATING">Validating</option>
            <option value="VALIDATED">Validated</option>
            <option value="PROCESSING">Processing</option>
            <option value="COMPLETED">Completed</option>
            <option value="FAILED">Failed</option>
          </select>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(b) => b.id}
      />
    </div>
  );
}
