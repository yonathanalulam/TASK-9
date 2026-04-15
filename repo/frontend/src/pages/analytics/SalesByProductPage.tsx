/* ------------------------------------------------------------------ */
/*  SalesByProductPage — tabular sales by product with filters          */
/* ------------------------------------------------------------------ */

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getSalesByDimensions } from '@/api/analytics';
import type { SalesData } from '@/api/analytics';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function SalesByProductPage() {
  const [region, setRegion] = useState('');
  const [channel, setChannel] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['analytics', 'sales', region, channel, dateFrom, dateTo],
    queryFn: () =>
      getSalesByDimensions({
        region: region || undefined,
        channel: channel || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      }),
  });

  const rows: SalesData[] = data?.data ?? [];

  // Summary row
  const totals = rows.reduce(
    (acc, r) => ({
      gross_sales: acc.gross_sales + r.gross_sales,
      net_sales: acc.net_sales + r.net_sales,
      quantity: acc.quantity + r.quantity,
      order_count: acc.order_count + r.order_count,
    }),
    { gross_sales: 0, net_sales: 0, quantity: 0, order_count: 0 },
  );

  if (isLoading) return <LoadingSpinner message="Loading sales data..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load sales data: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Sales by Product</h1>
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
        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Region</label>
          <input
            type="text"
            placeholder="All regions"
            value={region}
            onChange={(e) => setRegion(e.target.value)}
          />
        </div>
        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Channel</label>
          <input
            type="text"
            placeholder="All channels"
            value={channel}
            onChange={(e) => setChannel(e.target.value)}
          />
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
              <th>Product</th>
              <th>Category</th>
              <th style={{ textAlign: 'right' }}>Gross Sales</th>
              <th style={{ textAlign: 'right' }}>Net Sales</th>
              <th style={{ textAlign: 'right' }}>Quantity</th>
              <th style={{ textAlign: 'right' }}>Orders</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td
                  colSpan={6}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No sales data found.
                </td>
              </tr>
            ) : (
              <>
                {rows.map((row, idx) => (
                  <tr key={`${row.product}-${idx}`}>
                    <td style={{ fontWeight: 500 }}>{row.product ?? '--'}</td>
                    <td>{row.category ?? '--'}</td>
                    <td style={{ textAlign: 'right' }}>${row.gross_sales.toLocaleString()}</td>
                    <td style={{ textAlign: 'right' }}>${row.net_sales.toLocaleString()}</td>
                    <td style={{ textAlign: 'right' }}>{row.quantity.toLocaleString()}</td>
                    <td style={{ textAlign: 'right' }}>{row.order_count.toLocaleString()}</td>
                  </tr>
                ))}
                {/* Summary row */}
                <tr
                  style={{
                    fontWeight: 700,
                    borderTop: '2px solid var(--color-border, #e2e8f0)',
                    background: 'var(--color-bg-muted, #f8fafc)',
                  }}
                >
                  <td colSpan={2}>Total</td>
                  <td style={{ textAlign: 'right' }}>${totals.gross_sales.toLocaleString()}</td>
                  <td style={{ textAlign: 'right' }}>${totals.net_sales.toLocaleString()}</td>
                  <td style={{ textAlign: 'right' }}>{totals.quantity.toLocaleString()}</td>
                  <td style={{ textAlign: 'right' }}>{totals.order_count.toLocaleString()}</td>
                </tr>
              </>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
