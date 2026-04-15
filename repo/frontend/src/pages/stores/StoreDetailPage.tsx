import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getStore, getStoreVersions } from '@/api/stores';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const statusBadge: Record<string, string> = {
  active: 'badge badge-success',
  inactive: 'badge badge-danger',
  temporarily_closed: 'badge badge-warning',
};

export default function StoreDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data: envelope, isLoading, isError, error } = useQuery({
    queryKey: ['store', id],
    queryFn: () => getStore(id!),
    enabled: !!id,
  });

  const { data: versionsEnvelope, isLoading: versionsLoading } = useQuery({
    queryKey: ['store', id, 'versions'],
    queryFn: () => getStoreVersions(id!),
    enabled: !!id,
  });

  if (isLoading) return <LoadingSpinner message="Loading store..." />;
  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load store: {(error as Error).message}
      </div>
    );
  }

  const store = envelope?.data;
  if (!store) return null;

  const versions = versionsEnvelope?.data;

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/stores" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Stores
          </Link>{' '}
          / {store.name}
        </h1>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <span className={statusBadge[store.status] ?? 'badge'}>
            {store.status.replace('_', ' ')}
          </span>
          <Link
            to={`/stores/${store.id}/zones`}
            className="btn btn-primary"
            style={{ fontSize: 13, padding: '6px 14px', textDecoration: 'none' }}
          >
            View Zones
          </Link>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
        {/* Store info */}
        <div className="card">
          <h2 style={{ fontSize: 16, marginBottom: 16 }}>Store Details</h2>
          <InfoRow label="Name" value={store.name} />
          <InfoRow label="Code" value={store.code} />
          <InfoRow label="Type" value={store.store_type} />
          <InfoRow label="Region ID" value={store.region_id} />
          <InfoRow label="Timezone" value={store.timezone} />
          <InfoRow label="Active" value={store.is_active ? 'Yes' : 'No'} />
          <InfoRow label="Version" value={`v${store.version}`} />
        </div>

        {/* Version history */}
        <div className="card">
          <h2 style={{ fontSize: 16, marginBottom: 16 }}>Version History</h2>
          {versionsLoading ? (
            <LoadingSpinner size={20} />
          ) : !versions || versions.length === 0 ? (
            <p style={{ color: 'var(--color-text-muted)' }}>No version history available.</p>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {versions.map((v: any) => (
                  <tr key={v.id}>
                    <td>v{v.version}</td>
                    <td>
                      <span className={statusBadge[v.status] ?? 'badge'}>
                        {v.status?.replace('_', ' ') ?? '--'}
                      </span>
                    </td>
                    <td>{v.changed_at ?? '--'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <div style={{ display: 'flex', marginBottom: 10 }}>
      <span style={{ width: 120, color: 'var(--color-text-muted)', fontSize: 13, flexShrink: 0 }}>
        {label}
      </span>
      <span style={{ fontSize: 13 }}>{value}</span>
    </div>
  );
}
