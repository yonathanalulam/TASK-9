/* ------------------------------------------------------------------ */
/*  SalesTrendsPage — time-series sales with trend arrows               */
/* ------------------------------------------------------------------ */

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getSalesTrends } from '@/api/analytics';
import type { SalesTrend } from '@/api/analytics';
import LoadingSpinner from '@/components/common/LoadingSpinner';

type Granularity = 'day' | 'week' | 'month';

function trendArrow(current: number, previous: number | undefined): string {
  if (previous === undefined) return '';
  if (current > previous) return ' \u2191';
  if (current < previous) return ' \u2193';
  return ' \u2192';
}

function trendColor(current: number, previous: number | undefined): string | undefined {
  if (previous === undefined) return undefined;
  if (current > previous) return 'var(--color-success, #16a34a)';
  if (current < previous) return 'var(--color-danger, #dc2626)';
  return 'var(--color-text-muted)';
}

export default function SalesTrendsPage() {
  const [granularity, setGranularity] = useState<Granularity>('day');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['analytics', 'trends', granularity, dateFrom, dateTo],
    queryFn: () =>
      getSalesTrends({
        granularity,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      }),
  });

  const rows: SalesTrend[] = data?.data ?? [];

  if (isLoading) return <LoadingSpinner message="Loading trends..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load trends: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Sales Trends</h1>
      </div>

      {/* Filters */}
      <div
        className="card"
        style={{
          display: 'flex',
          gap: 16,
          alignItems: 'flex-end',
          flexWrap: 'wrap',
          marginBottom: 16,
          padding: '12px 16px',
        }}
      >
        <div className="form-group" style={{ marginBottom: 0, width: 140 }}>
          <label>Granularity</label>
          <select
            value={granularity}
            onChange={(e) => setGranularity(e.target.value as Granularity)}
          >
            <option value="day">Day</option>
            <option value="week">Week</option>
            <option value="month">Month</option>
          </select>
        </div>
        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Date From</label>
          <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
        </div>
        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Date To</label>
          <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
        </div>
      </div>

      {/* Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th style={{ textAlign: 'right' }}>Gross Sales</th>
              <th style={{ textAlign: 'right' }}>Net Sales</th>
              <th style={{ textAlign: 'right' }}>Quantity</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td
                  colSpan={4}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No trend data available.
                </td>
              </tr>
            ) : (
              rows.map((row, idx) => {
                const prev = idx > 0 ? rows[idx - 1] : undefined;
                return (
                  <tr key={row.date}>
                    <td style={{ fontWeight: 500 }}>{row.date}</td>
                    <td style={{ textAlign: 'right' }}>
                      ${row.gross_sales.toLocaleString()}
                      <span style={{ color: trendColor(row.gross_sales, prev?.gross_sales) }}>
                        {trendArrow(row.gross_sales, prev?.gross_sales)}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      ${row.net_sales.toLocaleString()}
                      <span style={{ color: trendColor(row.net_sales, prev?.net_sales) }}>
                        {trendArrow(row.net_sales, prev?.net_sales)}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      {row.quantity.toLocaleString()}
                      <span style={{ color: trendColor(row.quantity, prev?.quantity) }}>
                        {trendArrow(row.quantity, prev?.quantity)}
                      </span>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
