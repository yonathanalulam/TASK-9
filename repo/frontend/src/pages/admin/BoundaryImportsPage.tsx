/* ------------------------------------------------------------------ */
/*  BoundaryImportsPage — list of all boundary imports                 */
/* ------------------------------------------------------------------ */

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listBoundaries } from '@/api/boundaries';
import type { BoundaryImport } from '@/api/boundaries';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge mapping                                               */
/* ------------------------------------------------------------------ */

const statusStyles: Record<string, React.CSSProperties> = {
  UPLOADED: { background: '#e2e8f0', color: '#475569' },
  VALIDATING: { background: '#dbeafe', color: '#1e40af' },
  VALIDATED: { background: '#dcfce7', color: '#166534' },
  FAILED: { background: '#fee2e2', color: '#991b1b' },
  APPLIED: { background: '#f3e8ff', color: '#6b21a8' },
  SUPERSEDED: { background: '#f1f5f9', color: '#94a3b8' },
};

function StatusBadge({ status }: { status: string }) {
  const style = statusStyles[status] ?? {};
  return (
    <span
      className="badge"
      style={{ ...style }}
    >
      {status}
    </span>
  );
}

/* ------------------------------------------------------------------ */
/*  Table columns                                                      */
/* ------------------------------------------------------------------ */

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const columns: Column<BoundaryImport>[] = [
  {
    key: 'filename',
    header: 'Filename',
    render: (b) => (
      <Link to={`/admin/boundaries/${b.id}`} style={{ fontWeight: 500 }}>
        {b.filename}
      </Link>
    ),
  },
  {
    key: 'type',
    header: 'Type',
    width: '100px',
    render: (b) => b.type,
  },
  {
    key: 'size',
    header: 'Size',
    width: '100px',
    render: (b) => formatBytes(b.size),
  },
  {
    key: 'status',
    header: 'Status',
    width: '120px',
    render: (b) => <StatusBadge status={b.status} />,
  },
  {
    key: 'uploaded_by',
    header: 'Uploaded By',
    width: '140px',
    render: (b) => b.uploaded_by,
  },
  {
    key: 'created_at',
    header: 'Created',
    width: '160px',
    render: (b) => new Date(b.created_at).toLocaleString(),
  },
];

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function BoundaryImportsPage() {
  const [page, setPage] = useState(1);
  const perPage = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['boundaries', page, perPage],
    queryFn: () => listBoundaries({ page, per_page: perPage }),
  });

  if (isLoading) return <LoadingSpinner message="Loading boundary imports..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load boundary imports: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Boundary Imports</h1>
        <Link to="/admin/boundaries/upload" className="btn btn-primary">
          Upload Boundary
        </Link>
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
