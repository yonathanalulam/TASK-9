import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getReviewQueue, mergeItem, rejectItem } from '@/api/dedup';
import type { DedupReviewItem } from '@/api/dedup';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import SimilarityBadge from '@/components/import/SimilarityBadge';

/* ------------------------------------------------------------------ */
/*  Side-by-side comparison card                                       */
/* ------------------------------------------------------------------ */

function ComparisonCard({
  item,
  onMerge,
  onReject,
  merging,
  rejecting,
}: {
  item: DedupReviewItem;
  onMerge: () => void;
  onReject: () => void;
  merging: boolean;
  rejecting: boolean;
}) {
  return (
    <div
      className="card"
      style={{ padding: 20, marginBottom: 16 }}
    >
      {/* Header with similarity badge */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 16,
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ fontSize: 14, fontWeight: 600 }}>Review Item</span>
          <SimilarityBadge score={item.similarity_score} />
        </div>
        <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
          {new Date(item.created_at).toLocaleString()}
        </div>
      </div>

      {/* Side-by-side comparison */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: '1fr 1fr',
          gap: 16,
          marginBottom: 16,
        }}
      >
        {/* Import item (left) */}
        <div
          style={{
            padding: 16,
            border: '1px solid var(--color-border)',
            borderRadius: 6,
            background: 'rgba(59,130,246,0.03)',
          }}
        >
          <div
            style={{
              fontSize: 11,
              fontWeight: 600,
              textTransform: 'uppercase',
              letterSpacing: '0.06em',
              color: '#3b82f6',
              marginBottom: 10,
            }}
          >
            Import Item
          </div>
          <div style={{ marginBottom: 8 }}>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Title</div>
            <div style={{ fontWeight: 500 }}>{item.import_item.title}</div>
          </div>
          <div>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Body</div>
            <div
              style={{
                fontSize: 13,
                lineHeight: 1.5,
                maxHeight: 120,
                overflow: 'auto',
                whiteSpace: 'pre-wrap',
              }}
            >
              {item.import_item.body}
            </div>
          </div>
        </div>

        {/* Existing content (right) */}
        <div
          style={{
            padding: 16,
            border: '1px solid var(--color-border)',
            borderRadius: 6,
            background: 'rgba(34,197,94,0.03)',
          }}
        >
          <div
            style={{
              fontSize: 11,
              fontWeight: 600,
              textTransform: 'uppercase',
              letterSpacing: '0.06em',
              color: '#16a34a',
              marginBottom: 10,
            }}
          >
            Existing Content
          </div>
          <div style={{ marginBottom: 8 }}>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Title</div>
            <div style={{ fontWeight: 500 }}>{item.existing_content.title}</div>
          </div>
          <div style={{ marginBottom: 8 }}>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Body</div>
            <div
              style={{
                fontSize: 13,
                lineHeight: 1.5,
                maxHeight: 120,
                overflow: 'auto',
                whiteSpace: 'pre-wrap',
              }}
            >
              {item.existing_content.body}
            </div>
          </div>
          <div style={{ display: 'flex', gap: 12 }}>
            <div>
              <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Type</div>
              <span className="badge">{item.existing_content.content_type}</span>
            </div>
            <div>
              <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Status</div>
              <span className="badge">{item.existing_content.status}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Action buttons */}
      <div style={{ display: 'flex', gap: 12 }}>
        <button
          className="btn btn-primary"
          onClick={onMerge}
          disabled={merging || rejecting}
          style={{ fontSize: 13, padding: '6px 16px' }}
        >
          {merging ? 'Merging...' : 'Merge'}
        </button>
        <button
          className="btn btn-secondary"
          onClick={onReject}
          disabled={merging || rejecting}
          style={{
            fontSize: 13,
            padding: '6px 16px',
            color: '#dc2626',
            borderColor: '#fca5a5',
          }}
        >
          {rejecting ? 'Rejecting...' : 'Reject'}
        </button>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/*  Main page                                                          */
/* ------------------------------------------------------------------ */

export default function DedupReviewPage() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('PENDING');
  const perPage = 10;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['dedup-review', page, perPage, statusFilter],
    queryFn: () =>
      getReviewQueue({
        page,
        per_page: perPage,
      }),
  });

  const mergeMutation = useMutation({
    mutationFn: (id: string) => mergeItem(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dedup-review'] });
    },
  });

  const rejectMutation = useMutation({
    mutationFn: (id: string) => rejectItem(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dedup-review'] });
    },
  });

  if (isLoading) return <LoadingSpinner message="Loading review queue..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load review queue: {(error as Error).message}
      </div>
    );
  }

  const items = data?.data ?? [];
  const totalPages = data?.meta?.pagination?.total_pages ?? 1;

  return (
    <div>
      <div className="page-header">
        <h1>Dedup Review Queue</h1>
        <span
          style={{
            fontSize: 13,
            color: 'var(--color-text-muted)',
            background: 'var(--color-bg-muted, #f1f5f9)',
            padding: '4px 10px',
            borderRadius: 4,
          }}
        >
          {data?.meta?.pagination?.total ?? 0} items
        </span>
      </div>

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
        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Status</label>
          <select
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All</option>
            <option value="PENDING">Pending</option>
            <option value="MERGED">Merged</option>
            <option value="REJECTED">Rejected</option>
          </select>
        </div>
      </div>

      {/* Review cards */}
      {items.length === 0 ? (
        <div
          className="card"
          style={{ padding: 40, textAlign: 'center', color: 'var(--color-text-muted)' }}
        >
          No items to review.
        </div>
      ) : (
        items.map((item) => (
          <ComparisonCard
            key={item.id}
            item={item}
            onMerge={() => mergeMutation.mutate(item.id)}
            onReject={() => rejectMutation.mutate(item.id)}
            merging={mergeMutation.isPending && mergeMutation.variables === item.id}
            rejecting={rejectMutation.isPending && rejectMutation.variables === item.id}
          />
        ))
      )}

      {/* Pagination */}
      {totalPages > 1 && (
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: 8,
            marginTop: 16,
          }}
        >
          <button
            className="btn btn-secondary"
            disabled={page <= 1}
            onClick={() => setPage(page - 1)}
            style={{ padding: '6px 12px', fontSize: 13 }}
          >
            Previous
          </button>
          <span style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
            Page {page} of {totalPages}
          </span>
          <button
            className="btn btn-secondary"
            disabled={page >= totalPages}
            onClick={() => setPage(page + 1)}
            style={{ padding: '6px 12px', fontSize: 13 }}
          >
            Next
          </button>
        </div>
      )}

      {/* Mutation errors */}
      {mergeMutation.isError && (
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
          Merge failed: {(mergeMutation.error as Error).message}
        </div>
      )}
      {rejectMutation.isError && (
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
          Reject failed: {(rejectMutation.error as Error).message}
        </div>
      )}
    </div>
  );
}
