/* ------------------------------------------------------------------ */
/*  BoundaryDetailPage — detail view for a boundary import             */
/* ------------------------------------------------------------------ */

import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getBoundary, validateBoundary, applyBoundary } from '@/api/boundaries';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge styles                                                */
/* ------------------------------------------------------------------ */

const statusStyles: Record<string, React.CSSProperties> = {
  UPLOADED: { background: '#e2e8f0', color: '#475569' },
  VALIDATING: { background: '#dbeafe', color: '#1e40af' },
  VALIDATED: { background: '#dcfce7', color: '#166534' },
  FAILED: { background: '#fee2e2', color: '#991b1b' },
  APPLIED: { background: '#f3e8ff', color: '#6b21a8' },
  SUPERSEDED: { background: '#f1f5f9', color: '#94a3b8' },
};

/* ------------------------------------------------------------------ */
/*  Component                                                          */
/* ------------------------------------------------------------------ */

export default function BoundaryDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['boundary', id],
    queryFn: () => getBoundary(id!),
    enabled: !!id,
  });

  const validateMutation = useMutation({
    mutationFn: () => validateBoundary(id!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['boundary', id] });
    },
  });

  const applyMutation = useMutation({
    mutationFn: () => applyBoundary(id!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['boundary', id] });
    },
  });

  if (isLoading) return <LoadingSpinner message="Loading boundary details..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load boundary: {(error as Error).message}
      </div>
    );
  }

  const boundary = data!.data;
  if (!boundary) return null;

  const badgeStyle = statusStyles[boundary.status] ?? {};

  return (
    <div>
      <div className="page-header">
        <h1>Boundary Import</h1>
        <button className="btn btn-secondary" onClick={() => navigate('/admin/boundaries')}>
          Back to List
        </button>
      </div>

      <div className="card">
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
          <div>
            <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 2 }}>
              Filename
            </div>
            <div style={{ fontWeight: 500 }}>{boundary.filename}</div>
          </div>

          <div>
            <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 2 }}>
              Status
            </div>
            <span className="badge" style={badgeStyle}>
              {boundary.status}
            </span>
          </div>

          <div>
            <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 2 }}>
              Hash
            </div>
            <code style={{ fontSize: 13 }}>{boundary.hash}</code>
          </div>

          <div>
            <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 2 }}>
              Uploaded By
            </div>
            <div>{boundary.uploaded_by}</div>
          </div>

          <div>
            <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 2 }}>
              Created
            </div>
            <div>{new Date(boundary.created_at).toLocaleString()}</div>
          </div>

          {boundary.area_count !== null && (
            <div>
              <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 2 }}>
                Area Count
              </div>
              <div>{boundary.area_count}</div>
            </div>
          )}
        </div>

        {/* Errors */}
        {boundary.errors && boundary.errors.length > 0 && (
          <div style={{ marginTop: 20 }}>
            <div
              style={{
                fontSize: 13,
                color: 'var(--color-danger)',
                fontWeight: 500,
                marginBottom: 8,
              }}
            >
              Errors
            </div>
            <ul
              style={{
                background: '#fee2e2',
                borderRadius: 'var(--radius)',
                padding: '12px 12px 12px 28px',
                color: '#991b1b',
                fontSize: 13,
                display: 'flex',
                flexDirection: 'column',
                gap: 4,
              }}
            >
              {boundary.errors.map((err, i) => (
                <li key={i}>{err}</li>
              ))}
            </ul>
          </div>
        )}

        {/* Actions */}
        <div style={{ marginTop: 20, display: 'flex', gap: 8 }}>
          {boundary.status === 'UPLOADED' && (
            <button
              className="btn btn-primary"
              onClick={() => validateMutation.mutate()}
              disabled={validateMutation.isPending}
            >
              {validateMutation.isPending ? 'Validating...' : 'Validate'}
            </button>
          )}

          {boundary.status === 'VALIDATED' && (
            <button
              className="btn btn-primary"
              onClick={() => applyMutation.mutate()}
              disabled={applyMutation.isPending}
            >
              {applyMutation.isPending ? 'Applying...' : 'Apply'}
            </button>
          )}
        </div>

        {/* Mutation errors */}
        {(validateMutation.isError || applyMutation.isError) && (
          <div
            style={{
              marginTop: 12,
              color: 'var(--color-danger)',
              fontSize: 13,
            }}
          >
            {(validateMutation.error as Error)?.message ??
              (applyMutation.error as Error)?.message ??
              'Operation failed'}
          </div>
        )}
      </div>
    </div>
  );
}
