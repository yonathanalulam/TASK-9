import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getImport, getImportItems } from '@/api/imports';
import type { ImportItem } from '@/api/imports';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import SimilarityBadge from '@/components/import/SimilarityBadge';

/* ------------------------------------------------------------------ */
/*  Status styling                                                     */
/* ------------------------------------------------------------------ */

const batchStatusStyles: Record<string, React.CSSProperties> = {
  PENDING: { background: '#e2e8f0', color: '#475569' },
  VALIDATING: { background: '#dbeafe', color: '#1e40af' },
  VALIDATED: { background: '#dcfce7', color: '#166534' },
  PROCESSING: { background: '#e0e7ff', color: '#3730a3' },
  COMPLETED: { background: '#dcfce7', color: '#166534' },
  FAILED: { background: '#fee2e2', color: '#991b1b' },
};

const itemStatusStyles: Record<string, React.CSSProperties> = {
  PENDING: { background: '#e2e8f0', color: '#475569' },
  IMPORTED: { background: '#dcfce7', color: '#166534' },
  DUPLICATE: { background: '#fef9c3', color: '#854d0e' },
  MERGED: { background: '#e0e7ff', color: '#3730a3' },
  REJECTED: { background: '#fee2e2', color: '#991b1b' },
  ERROR: { background: '#fee2e2', color: '#991b1b' },
};

/* ------------------------------------------------------------------ */
/*  Item table columns                                                 */
/* ------------------------------------------------------------------ */

const itemColumns: Column<ImportItem>[] = [
  {
    key: 'row_index',
    header: 'Row',
    width: '60px',
    render: (item) => String(item.row_index),
  },
  {
    key: 'title',
    header: 'Title',
    render: (item) => item.title || item.external_id || '--',
  },
  {
    key: 'status',
    header: 'Status',
    width: '110px',
    render: (item) => (
      <span className="badge" style={itemStatusStyles[item.status] ?? {}}>
        {item.status}
      </span>
    ),
  },
  {
    key: 'similarity',
    header: 'Similarity',
    width: '140px',
    render: (item) => <SimilarityBadge score={item.similarity_score} />,
  },
  {
    key: 'matched_content_id',
    header: 'Matched Content',
    width: '160px',
    render: (item) =>
      item.matched_content_id ? (
        <Link to={`/content/${item.matched_content_id}`} style={{ fontSize: 12 }}>
          {item.matched_content_id.slice(0, 12)}...
        </Link>
      ) : (
        <span style={{ color: 'var(--color-text-muted)' }}>--</span>
      ),
  },
  {
    key: 'error',
    header: 'Error',
    width: '200px',
    render: (item) =>
      item.error_message ? (
        <span style={{ fontSize: 12, color: '#dc2626' }}>{item.error_message}</span>
      ) : (
        <span style={{ color: 'var(--color-text-muted)' }}>--</span>
      ),
  },
];

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function ImportBatchDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [itemPage, setItemPage] = useState(1);
  const [itemStatus, setItemStatus] = useState('');
  const perPage = 25;

  const batchQuery = useQuery({
    queryKey: ['import-batch', id],
    queryFn: () => getImport(id!),
    enabled: !!id,
  });

  const itemsQuery = useQuery({
    queryKey: ['import-batch-items', id, itemPage, perPage, itemStatus],
    queryFn: () =>
      getImportItems(id!, {
        page: itemPage,
        per_page: perPage,
        status: itemStatus || undefined,
      }),
    enabled: !!id,
  });

  if (batchQuery.isLoading) return <LoadingSpinner message="Loading batch details..." />;

  if (batchQuery.isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load batch: {(batchQuery.error as Error).message}
      </div>
    );
  }

  const batch = batchQuery.data!.data;
  const pct = batch.total_items > 0
    ? Math.round((batch.processed_items / batch.total_items) * 100)
    : 0;

  return (
    <div>
      <div className="page-header">
        <h1>Import Batch: {batch.filename}</h1>
        <Link
          to="/imports"
          className="btn btn-secondary"
          style={{ textDecoration: 'none', fontSize: 13, padding: '6px 14px' }}
        >
          Back to List
        </Link>
      </div>

      {/* Summary cards */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
          gap: 12,
          marginBottom: 20,
        }}
      >
        <div className="card" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Status
          </div>
          <span className="badge" style={batchStatusStyles[batch.status] ?? {}}>
            {batch.status}
          </span>
        </div>

        <div className="card" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Progress
          </div>
          <div style={{ fontWeight: 600 }}>
            {batch.processed_items} / {batch.total_items} ({pct}%)
          </div>
          <div
            style={{
              marginTop: 6,
              height: 4,
              background: '#e2e8f0',
              borderRadius: 2,
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                width: `${pct}%`,
                height: '100%',
                background: pct === 100 ? '#22c55e' : '#3b82f6',
                transition: 'width 0.3s',
              }}
            />
          </div>
        </div>

        <div className="card" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Duplicates
          </div>
          <div style={{ fontWeight: 600, color: batch.duplicate_items > 0 ? '#d97706' : undefined }}>
            {batch.duplicate_items}
          </div>
        </div>

        <div className="card" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Errors
          </div>
          <div style={{ fontWeight: 600, color: batch.error_items > 0 ? '#dc2626' : undefined }}>
            {batch.error_items}
          </div>
        </div>

        <div className="card" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Format
          </div>
          <div style={{ fontWeight: 600 }}>{batch.format}</div>
        </div>

        <div className="card" style={{ padding: '16px 20px' }}>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Uploaded By
          </div>
          <div style={{ fontWeight: 500 }}>{batch.uploaded_by}</div>
        </div>
      </div>

      {/* Items table */}
      <h2 style={{ fontSize: 16, marginBottom: 12 }}>Batch Items</h2>

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
          <label>Item Status</label>
          <select
            value={itemStatus}
            onChange={(e) => {
              setItemStatus(e.target.value);
              setItemPage(1);
            }}
          >
            <option value="">All Statuses</option>
            <option value="PENDING">Pending</option>
            <option value="IMPORTED">Imported</option>
            <option value="DUPLICATE">Duplicate</option>
            <option value="MERGED">Merged</option>
            <option value="REJECTED">Rejected</option>
            <option value="ERROR">Error</option>
          </select>
        </div>
      </div>

      {itemsQuery.isLoading ? (
        <LoadingSpinner message="Loading items..." />
      ) : itemsQuery.isError ? (
        <div style={{ color: 'var(--color-danger)', padding: 20 }}>
          Failed to load items: {(itemsQuery.error as Error).message}
        </div>
      ) : (
        <DataTable
          columns={itemColumns}
          data={itemsQuery.data?.data ?? []}
          page={itemPage}
          totalPages={itemsQuery.data?.meta?.pagination?.total_pages ?? 1}
          onPageChange={setItemPage}
          keyExtractor={(item) => item.id}
        />
      )}
    </div>
  );
}
