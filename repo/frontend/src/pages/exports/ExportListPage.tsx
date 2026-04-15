import { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listExports, downloadExport, DATASET_LABELS } from '@/api/exports';
import type { ExportRecord } from '@/api/exports';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge                                                       */
/* ------------------------------------------------------------------ */

const STATUS_COLORS: Record<string, React.CSSProperties> = {
  REQUESTED: { background: '#f1f5f9', color: '#475569' },
  AUTHORIZED: { background: '#dbeafe', color: '#1e40af' },
  RUNNING: { background: '#fef9c3', color: '#854d0e' },
  SUCCEEDED: { background: '#dcfce7', color: '#166534' },
  FAILED: { background: '#fee2e2', color: '#991b1b' },
  EXPIRED: { background: '#f1f5f9', color: '#94a3b8' },
};

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function ExportListPage() {
  const [page, setPage] = useState(1);
  const perPage = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['exports', page, perPage],
    queryFn: () => listExports({ page, per_page: perPage }),
  });

  const handleDownload = useCallback(async (exp: ExportRecord) => {
    try {
      const blob = await downloadExport(exp.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = exp.file_name ?? `export-${exp.dataset}.${exp.format.toLowerCase()}`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch {
      alert('Download failed. The export file may have expired.');
    }
  }, []);

  const columns: Column<ExportRecord>[] = [
    {
      key: 'dataset',
      header: 'Dataset',
      width: '150px',
      render: (exp) => (
        <span style={{ fontWeight: 500 }}>
          {DATASET_LABELS[exp.dataset as keyof typeof DATASET_LABELS] ??
            exp.dataset.replace(/_/g, ' ')}
        </span>
      ),
    },
    {
      key: 'format',
      header: 'Format',
      width: '80px',
      render: (exp) => (
        <span className="badge" style={{ background: '#f1f5f9', color: '#475569' }}>
          {exp.format}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      width: '130px',
      render: (exp) => (
        <span className="badge" style={STATUS_COLORS[exp.status] ?? {}}>
          {exp.status}
        </span>
      ),
    },
    {
      key: 'requested_by',
      header: 'Requested By',
      width: '130px',
      render: (exp) => exp.requested_by,
    },
    {
      key: 'requested_at',
      header: 'Requested At',
      width: '170px',
      render: (exp) => (
        <span style={{ fontSize: 13 }}>
          {new Date(exp.requested_at).toLocaleString()}
        </span>
      ),
    },
    {
      key: 'expires_at',
      header: 'Expires At',
      width: '170px',
      render: (exp) => (
        <span style={{ fontSize: 13, color: exp.expires_at ? undefined : 'var(--color-text-muted)' }}>
          {exp.expires_at ? new Date(exp.expires_at).toLocaleString() : '--'}
        </span>
      ),
    },
    {
      key: 'download',
      header: '',
      width: '100px',
      render: (exp) =>
        exp.status === 'SUCCEEDED' ? (
          <button
            className="btn btn-primary"
            onClick={() => handleDownload(exp)}
            style={{ fontSize: 12, padding: '4px 10px' }}
          >
            Download
          </button>
        ) : null,
    },
  ];

  if (isLoading) return <LoadingSpinner message="Loading exports..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load exports: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Exports</h1>
        <Link
          to="/exports/new"
          className="btn btn-primary"
          style={{ textDecoration: 'none', fontSize: 13, padding: '6px 14px' }}
        >
          New Export
        </Link>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(exp) => exp.id}
      />
    </div>
  );
}
