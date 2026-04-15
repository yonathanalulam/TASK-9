import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getRegion, getRegionVersions } from '@/api/regions';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import dayjs from 'dayjs';

export default function RegionDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data: envelope, isLoading, isError, error } = useQuery({
    queryKey: ['region', id],
    queryFn: () => getRegion(id!),
    enabled: !!id,
  });

  const { data: versionsEnvelope, isLoading: versionsLoading } = useQuery({
    queryKey: ['region', id, 'versions'],
    queryFn: () => getRegionVersions(id!),
    enabled: !!id,
  });

  if (isLoading) return <LoadingSpinner message="Loading region..." />;
  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load region: {(error as Error).message}
      </div>
    );
  }

  const region = envelope?.data;
  if (!region) return null;

  const versions = versionsEnvelope?.data;

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/regions" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Regions
          </Link>{' '}
          / {region.name}
        </h1>
        <span className={region.is_active ? 'badge badge-success' : 'badge badge-danger'}>
          {region.is_active ? 'Active' : 'Inactive'}
        </span>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
        {/* Region info */}
        <div className="card">
          <h2 style={{ fontSize: 16, marginBottom: 16 }}>Region Details</h2>
          <InfoRow label="Name" value={region.name} />
          <InfoRow label="Code" value={region.code} />
          <InfoRow label="Active" value={region.is_active ? 'Yes' : 'No'} />
          <InfoRow label="Parent ID" value={region.parent_id ?? 'None (top-level)'} />
          <InfoRow label="Hierarchy" value={String(region.hierarchy_level)} />
          <InfoRow label="Version" value={`v${region.version}`} />
          <InfoRow label="Effective From" value={dayjs(region.effective_from).format('MMM D, YYYY h:mm A')} />
          <InfoRow label="Effective Until" value={region.effective_until ? dayjs(region.effective_until).format('MMM D, YYYY h:mm A') : 'N/A'} />
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
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {versions.map((v: any) => (
                  <tr key={v.id}>
                    <td>v{v.version}</td>
                    <td>{v.changed_at ? dayjs(v.changed_at).format('MMM D, YYYY') : '--'}</td>
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
