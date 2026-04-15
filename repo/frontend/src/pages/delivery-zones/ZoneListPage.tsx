import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listDeliveryZones } from '@/api/deliveryZones';
import { getStore } from '@/api/stores';
import type { DeliveryZone } from '@/api/types';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const statusBadge: Record<string, string> = {
  active: 'badge badge-success',
  inactive: 'badge badge-danger',
};

const columns: Column<DeliveryZone>[] = [
  {
    key: 'name',
    header: 'Name',
    render: (z) => (
      <Link to={`/zones/${z.id}`} style={{ fontWeight: 500 }}>
        {z.name}
      </Link>
    ),
  },
  {
    key: 'status',
    header: 'Status',
    width: '100px',
    render: (z) => (
      <span className={statusBadge[z.status] ?? 'badge'}>
        {z.status}
      </span>
    ),
  },
  {
    key: 'version',
    header: 'Version',
    width: '80px',
    render: (z) => `v${z.version}`,
  },
];

export default function ZoneListPage() {
  const { storeId } = useParams<{ storeId: string }>();
  const [page, setPage] = useState(1);
  const per_page = 20;

  const { data: storeEnvelope } = useQuery({
    queryKey: ['store', storeId],
    queryFn: () => getStore(storeId!),
    enabled: !!storeId,
  });

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['zones', storeId, page, per_page],
    queryFn: () => listDeliveryZones(storeId!, { page, per_page }),
    enabled: !!storeId,
  });

  if (isLoading) return <LoadingSpinner message="Loading zones..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load zones: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/stores" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Stores
          </Link>{' '}
          /{' '}
          <Link
            to={`/stores/${storeId}`}
            style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}
          >
            {storeEnvelope?.data?.name ?? storeId}
          </Link>{' '}
          / Zones
        </h1>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(z) => z.id}
      />
    </div>
  );
}
