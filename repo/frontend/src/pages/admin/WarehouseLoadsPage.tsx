/* ------------------------------------------------------------------ */
/*  WarehouseLoadsPage — ETL run table with trigger + expandable detail */
/* ------------------------------------------------------------------ */

import { useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { listLoadRuns, triggerLoad } from '@/api/warehouse';
import type { LoadRun } from '@/api/warehouse';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const statusBadge: Record<string, string> = {
  PENDING: 'badge badge-info',
  RUNNING: 'badge badge-warning',
  COMPLETED: 'badge badge-success',
  FAILED: 'badge badge-danger',
  PARTIAL: 'badge badge-warning',
};

function formatDuration(ms: number | null): string {
  if (ms === null) return '--';
  if (ms < 1000) return `${ms}ms`;
  const secs = (ms / 1000).toFixed(1);
  return `${secs}s`;
}

export default function WarehouseLoadsPage() {
  const [page, setPage] = useState(1);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const perPage = 20;
  const queryClient = useQueryClient();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['warehouse', 'loads', page, perPage],
    queryFn: () => listLoadRuns({ page, per_page: perPage }),
  });

  const triggerMutation = useMutation({
    mutationFn: triggerLoad,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse', 'loads'] });
    },
  });

  const toggleExpand = useCallback(
    (id: string) => {
      setExpandedId((prev) => (prev === id ? null : id));
    },
    [],
  );

  if (isLoading) return <LoadingSpinner message="Loading warehouse loads..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load warehouse runs: {(error as Error).message}
      </div>
    );
  }

  const runs: LoadRun[] = data?.data ?? [];
  const totalPages = data?.meta?.pagination?.total_pages ?? 1;

  return (
    <div>
      <div className="page-header">
        <h1>Warehouse Loads</h1>
        <button
          className="btn btn-primary"
          onClick={() => triggerMutation.mutate()}
          disabled={triggerMutation.isPending}
          style={{ fontSize: 13, padding: '6px 14px' }}
        >
          {triggerMutation.isPending ? 'Triggering...' : 'Trigger Load'}
        </button>
      </div>

      {triggerMutation.isError && (
        <div
          style={{
            color: 'var(--color-danger)',
            padding: '8px 12px',
            marginBottom: 12,
            fontSize: 13,
          }}
        >
          Trigger failed: {(triggerMutation.error as Error).message}
        </div>
      )}

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table>
          <thead>
            <tr>
              <th style={{ width: 30 }} />
              <th>Load Type</th>
              <th style={{ width: 110 }}>Status</th>
              <th style={{ textAlign: 'right' }}>Extracted</th>
              <th style={{ textAlign: 'right' }}>Loaded</th>
              <th style={{ textAlign: 'right' }}>Rejected</th>
              <th style={{ width: 100 }}>Duration</th>
              <th style={{ width: 160 }}>Started</th>
            </tr>
          </thead>
          <tbody>
            {runs.length === 0 ? (
              <tr>
                <td
                  colSpan={8}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No warehouse loads found. Click &quot;Trigger Load&quot; to start one.
                </td>
              </tr>
            ) : (
              runs.map((run) => (
                <>
                  <tr key={run.id}>
                    <td>
                      {run.rows_rejected > 0 && (
                        <button
                          onClick={() => toggleExpand(run.id)}
                          style={{
                            background: 'none',
                            border: 'none',
                            cursor: 'pointer',
                            fontSize: 14,
                            padding: 2,
                          }}
                          title="Toggle rejected rows"
                        >
                          {expandedId === run.id ? '\u25BC' : '\u25B6'}
                        </button>
                      )}
                    </td>
                    <td style={{ fontWeight: 500 }}>{run.load_type}</td>
                    <td>
                      <span className={statusBadge[run.status] ?? 'badge'}>{run.status}</span>
                    </td>
                    <td style={{ textAlign: 'right' }}>{run.rows_extracted.toLocaleString()}</td>
                    <td style={{ textAlign: 'right' }}>{run.rows_loaded.toLocaleString()}</td>
                    <td style={{ textAlign: 'right' }}>
                      <span
                        style={{
                          color: run.rows_rejected > 0 ? 'var(--color-danger)' : undefined,
                          fontWeight: run.rows_rejected > 0 ? 600 : undefined,
                        }}
                      >
                        {run.rows_rejected.toLocaleString()}
                      </span>
                    </td>
                    <td>{formatDuration(run.duration_ms)}</td>
                    <td style={{ fontSize: 13 }}>
                      {new Date(run.started_at).toLocaleString()}
                    </td>
                  </tr>
                  {/* Expandable rejected rows detail */}
                  {expandedId === run.id && run.rejected_details.length > 0 && (
                    <tr key={`${run.id}-detail`}>
                      <td colSpan={8} style={{ padding: 0 }}>
                        <div
                          style={{
                            padding: '12px 20px',
                            background: 'var(--color-bg-muted, #f8fafc)',
                          }}
                        >
                          <div
                            style={{
                              fontSize: 12,
                              fontWeight: 600,
                              marginBottom: 8,
                              textTransform: 'uppercase',
                              color: 'var(--color-text-muted)',
                            }}
                          >
                            Rejected Rows ({run.rejected_details.length})
                          </div>
                          <table style={{ fontSize: 13 }}>
                            <thead>
                              <tr>
                                <th style={{ width: 80 }}>Row #</th>
                                <th>Reason</th>
                                <th>Data</th>
                              </tr>
                            </thead>
                            <tbody>
                              {run.rejected_details.map((r) => (
                                <tr key={r.row_index}>
                                  <td>{r.row_index}</td>
                                  <td style={{ color: 'var(--color-danger)' }}>{r.reason}</td>
                                  <td>
                                    <code style={{ fontSize: 11, wordBreak: 'break-all' }}>
                                      {JSON.stringify(r.data)}
                                    </code>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </td>
                    </tr>
                  )}
                </>
              ))
            )}
          </tbody>
        </table>
      </div>

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
            onClick={() => setPage((p) => p - 1)}
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
            onClick={() => setPage((p) => p + 1)}
            style={{ padding: '6px 12px', fontSize: 13 }}
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
