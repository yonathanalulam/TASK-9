import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listStores } from '@/api/stores';
import type { Store } from '@/api/types';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const statusBadge: Record<string, string> = {
  active: 'badge badge-success',
  inactive: 'badge badge-danger',
  temporarily_closed: 'badge badge-warning',
};

const columns: Column<Store>[] = [
  {
    key: 'name',
    header: 'Name',
    render: (s) => (
      <Link to={`/stores/${s.id}`} style={{ fontWeight: 500 }}>
        {s.name}
      </Link>
    ),
  },
  {
    key: 'code',
    header: 'Code',
    width: '120px',
    render: (s) => <code style={{ fontSize: 13 }}>{s.code}</code>,
  },
  {
    key: 'store_type',
    header: 'Type',
    render: (s) => s.store_type,
  },
  {
    key: 'status',
    header: 'Status',
    width: '140px',
    render: (s) => (
      <span className={statusBadge[s.status] ?? 'badge'}>
        {s.status.replace('_', ' ')}
      </span>
    ),
  },
  {
    key: 'zones',
    header: '',
    width: '90px',
    render: (s) => (
      <Link to={`/stores/${s.id}/zones`} style={{ fontSize: 13 }}>
        Zones
      </Link>
    ),
  },
];

export default function StoreListPage() {
  const [page, setPage] = useState(1);
  const per_page = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['stores', page, per_page],
    queryFn: () => listStores({ page, per_page }),
  });

  if (isLoading) return <LoadingSpinner message="Loading stores..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load stores: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Stores</h1>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(s) => s.id}
      />
    </div>
  );
}
