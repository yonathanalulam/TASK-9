/* ------------------------------------------------------------------ */
/*  SourceDetailPage — source config, health events, recent runs        */
/* ------------------------------------------------------------------ */

import { useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  getSource,
  getSourceHealth,
  listScrapeRuns,
  triggerScrape,
  pauseSource,
  resumeSource,
  disableSource,
} from '@/api/scraping';
import type { ScrapeRun } from '@/api/scraping';
import SourceStatusIndicator from '@/components/scraping/SourceStatusIndicator';
import type { SourceStatus } from '@/components/scraping/SourceStatusIndicator';
import HealthEventTimeline from '@/components/scraping/HealthEventTimeline';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function SourceDetailPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();

  const sourceQuery = useQuery({
    queryKey: ['scraping', 'source', id],
    queryFn: () => getSource(id!),
    enabled: !!id,
  });

  const healthQuery = useQuery({
    queryKey: ['scraping', 'source', id, 'health'],
    queryFn: () => getSourceHealth(id!),
    enabled: !!id,
  });

  const runsQuery = useQuery({
    queryKey: ['scraping', 'runs', { sourceId: id }],
    queryFn: () => listScrapeRuns({ page: 1, per_page: 10 }),
    enabled: !!id,
  });

  const invalidateAll = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['scraping', 'source', id] });
    queryClient.invalidateQueries({ queryKey: ['scraping', 'runs'] });
  }, [queryClient, id]);

  const scrapeMut = useMutation({
    mutationFn: () => triggerScrape(id!),
    onSuccess: invalidateAll,
  });

  const pauseMut = useMutation({
    mutationFn: () => pauseSource(id!),
    onSuccess: invalidateAll,
  });

  const resumeMut = useMutation({
    mutationFn: () => resumeSource(id!),
    onSuccess: invalidateAll,
  });

  const disableMut = useMutation({
    mutationFn: () => disableSource(id!),
    onSuccess: invalidateAll,
  });

  if (sourceQuery.isLoading) return <LoadingSpinner message="Loading source..." />;

  if (sourceQuery.isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load source: {(sourceQuery.error as Error).message}
      </div>
    );
  }

  const source = sourceQuery.data!.data;
  const health = healthQuery.data?.data;
  const runs: ScrapeRun[] = runsQuery.data?.data ?? [];

  return (
    <div>
      <div className="page-header">
        <h1>{source.name}</h1>
        <div style={{ display: 'flex', gap: 8 }}>
          <button
            className="btn btn-primary"
            onClick={() => scrapeMut.mutate()}
            disabled={scrapeMut.isPending || source.status === 'DISABLED'}
            style={{ fontSize: 13, padding: '6px 14px' }}
          >
            {scrapeMut.isPending ? 'Triggering...' : 'Trigger Scrape'}
          </button>
          {(source.status === 'ACTIVE' || source.status === 'DEGRADED') && (
            <button
              className="btn btn-secondary"
              onClick={() => pauseMut.mutate()}
              disabled={pauseMut.isPending}
              style={{ fontSize: 13, padding: '6px 14px' }}
            >
              Pause
            </button>
          )}
          {source.status === 'PAUSED' && (
            <button
              className="btn btn-secondary"
              onClick={() => resumeMut.mutate()}
              disabled={resumeMut.isPending}
              style={{ fontSize: 13, padding: '6px 14px' }}
            >
              Resume
            </button>
          )}
          {source.status !== 'DISABLED' && (
            <button
              className="btn btn-secondary"
              onClick={() => {
                if (window.confirm('Disable this source?')) disableMut.mutate();
              }}
              disabled={disableMut.isPending}
              style={{ fontSize: 13, padding: '6px 14px', color: 'var(--color-danger)' }}
            >
              Disable
            </button>
          )}
        </div>
      </div>

      {/* Source details */}
      <div
        className="card"
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
          gap: 16,
          marginBottom: 24,
        }}
      >
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Status
          </div>
          <SourceStatusIndicator status={source.status as SourceStatus} />
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Base URL
          </div>
          <div style={{ fontSize: 14, wordBreak: 'break-all' }}>{source.base_url}</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Type
          </div>
          <div style={{ fontSize: 14 }}>{source.type}</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Rate Limit
          </div>
          <div style={{ fontSize: 14 }}>{source.rate_limit} req/min</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Schedule
          </div>
          <div style={{ fontSize: 14 }}>{source.schedule ?? 'Manual'}</div>
        </div>
        <div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>
            Last Scrape
          </div>
          <div style={{ fontSize: 14 }}>
            {source.last_scrape_at ? new Date(source.last_scrape_at).toLocaleString() : '--'}
          </div>
        </div>
      </div>

      {/* Health metrics */}
      {health && (
        <div className="card" style={{ marginBottom: 24 }}>
          <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 12 }}>Health Metrics</h3>
          <div style={{ display: 'flex', gap: 24, flexWrap: 'wrap', marginBottom: 16 }}>
            <div>
              <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Uptime</span>
              <div style={{ fontSize: 18, fontWeight: 700 }}>{health.uptime.toFixed(1)}%</div>
            </div>
            <div>
              <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
                Avg Response
              </span>
              <div style={{ fontSize: 18, fontWeight: 700 }}>{health.avg_response_ms}ms</div>
            </div>
            <div>
              <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Error Rate</span>
              <div style={{ fontSize: 18, fontWeight: 700 }}>{health.error_rate.toFixed(2)}%</div>
            </div>
          </div>
        </div>
      )}

      {/* Health event timeline */}
      <div className="card" style={{ marginBottom: 24 }}>
        <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 12 }}>Health Events</h3>
        {healthQuery.isLoading ? (
          <LoadingSpinner message="Loading events..." />
        ) : (
          <HealthEventTimeline events={health?.recent_events ?? []} />
        )}
      </div>

      {/* Recent scrape runs */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '16px 20px', borderBottom: '1px solid var(--color-border, #e2e8f0)' }}>
          <h3 style={{ fontSize: 14, fontWeight: 600, margin: 0 }}>Recent Scrape Runs</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Run ID</th>
              <th style={{ width: 100 }}>Status</th>
              <th style={{ textAlign: 'right' }}>Found</th>
              <th style={{ textAlign: 'right' }}>New</th>
              <th style={{ textAlign: 'right' }}>Updated</th>
              <th style={{ textAlign: 'right' }}>Failed</th>
              <th style={{ width: 160 }}>Started</th>
            </tr>
          </thead>
          <tbody>
            {runs.length === 0 ? (
              <tr>
                <td
                  colSpan={7}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No scrape runs yet.
                </td>
              </tr>
            ) : (
              runs.map((run) => (
                <tr key={run.id}>
                  <td>
                    <Link
                      to={`/scraping/runs/${run.id}`}
                      style={{ color: 'var(--color-primary)', fontSize: 13 }}
                    >
                      {run.id.slice(0, 12)}...
                    </Link>
                  </td>
                  <td>
                    <span
                      className={
                        run.status === 'COMPLETED'
                          ? 'badge badge-success'
                          : run.status === 'FAILED'
                            ? 'badge badge-danger'
                            : run.status === 'RUNNING'
                              ? 'badge badge-warning'
                              : 'badge badge-info'
                      }
                    >
                      {run.status}
                    </span>
                  </td>
                  <td style={{ textAlign: 'right' }}>{run.items_found}</td>
                  <td style={{ textAlign: 'right' }}>{run.items_new}</td>
                  <td style={{ textAlign: 'right' }}>{run.items_updated}</td>
                  <td style={{ textAlign: 'right' }}>
                    <span
                      style={{
                        color: run.items_failed > 0 ? 'var(--color-danger)' : undefined,
                      }}
                    >
                      {run.items_failed}
                    </span>
                  </td>
                  <td style={{ fontSize: 13 }}>{new Date(run.started_at).toLocaleString()}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
