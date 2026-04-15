import type { ReactNode } from 'react';

export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  width?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  page: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  keyExtractor: (row: T) => string;
}

export default function DataTable<T>({
  columns,
  data,
  page,
  totalPages,
  onPageChange,
  keyExtractor,
}: DataTableProps<T>) {
  return (
    <div>
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table>
          <thead>
            <tr>
              {columns.map((col) => (
                <th key={col.key} style={{ width: col.width }}>
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length}
                  style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                >
                  No data found.
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={keyExtractor(row)}>
                  {columns.map((col) => (
                    <td key={col.key}>{col.render(row)}</td>
                  ))}
                </tr>
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
            onClick={() => onPageChange(page - 1)}
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
            onClick={() => onPageChange(page + 1)}
            style={{ padding: '6px 12px', fontSize: 13 }}
          >
            Next
          </button>
        </div>
      )}
    </div>
  );
}
