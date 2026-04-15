import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listRegions } from '@/api/regions';
import type { Region } from '@/api/types';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const columns: Column<Region>[] = [
  {
    key: 'name',
    header: 'Name',
    render: (r) => (
      <Link to={`/regions/${r.id}`} style={{ fontWeight: 500 }}>
        {r.name}
      </Link>
    ),
  },
  {
    key: 'code',
    header: 'Code',
    width: '120px',
    render: (r) => <code style={{ fontSize: 13 }}>{r.code}</code>,
  },
  {
    key: 'is_active',
    header: 'Status',
    width: '100px',
    render: (r) => (
      <span className={r.is_active ? 'badge badge-success' : 'badge badge-danger'}>
        {r.is_active ? 'Active' : 'Inactive'}
      </span>
    ),
  },
  {
    key: 'version',
    header: 'Version',
    width: '80px',
    render: (r) => `v${r.version}`,
  },
];

export default function RegionListPage() {
  const [page, setPage] = useState(1);
  const per_page = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['regions', page, per_page],
    queryFn: () => listRegions({ page, per_page }),
  });

  if (isLoading) return <LoadingSpinner message="Loading regions..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load regions: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Regions</h1>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(r) => r.id}
      />
    </div>
  );
}
