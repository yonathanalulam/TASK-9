import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getDeliveryZone, listDeliveryWindows } from '@/api/deliveryZones';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const statusBadge: Record<string, string> = {
  active: 'badge badge-success',
  inactive: 'badge badge-danger',
};

export default function ZoneDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data: zoneEnvelope, isLoading, isError, error } = useQuery({
    queryKey: ['zone', id],
    queryFn: () => getDeliveryZone(id!),
    enabled: !!id,
  });

  const { data: windowsEnvelope, isLoading: windowsLoading } = useQuery({
    queryKey: ['zone', id, 'windows'],
    queryFn: () => listDeliveryWindows(id!),
    enabled: !!id,
  });

  if (isLoading) return <LoadingSpinner message="Loading zone..." />;
  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load zone: {(error as Error).message}
      </div>
    );
  }

  const zone = zoneEnvelope?.data;
  if (!zone) return null;

  const windows = windowsEnvelope?.data;

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/stores" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Stores
          </Link>{' '}
          /{' '}
          <Link
            to={`/stores/${zone.store_id}/zones`}
            style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}
          >
            Zones
          </Link>{' '}
          / {zone.name}
        </h1>
        <span className={statusBadge[zone.status] ?? 'badge'}>
          {zone.status}
        </span>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
        {/* Zone info */}
        <div className="card">
          <h2 style={{ fontSize: 16, marginBottom: 16 }}>Zone Details</h2>
          <InfoRow label="Name" value={zone.name} />
          <InfoRow label="Store ID" value={zone.store_id} />
          <InfoRow label="Status" value={zone.status} />
          <InfoRow label="Active" value={zone.is_active ? 'Yes' : 'No'} />
          <InfoRow label="Version" value={`v${zone.version}`} />
        </div>

        {/* Delivery Windows */}
        <div className="card">
          <h2 style={{ fontSize: 16, marginBottom: 16 }}>Delivery Windows</h2>
          {windowsLoading ? (
            <LoadingSpinner size={20} />
          ) : !windows || windows.length === 0 ? (
            <p style={{ color: 'var(--color-text-muted)' }}>
              No delivery windows configured for this zone.
            </p>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>Day</th>
                  <th>Window</th>
                  <th>Active</th>
                </tr>
              </thead>
              <tbody>
                {windows.map((w) => (
                  <tr key={w.id}>
                    <td>{DAY_NAMES[w.day_of_week] ?? w.day_of_week}</td>
                    <td>
                      {w.start_time} - {w.end_time}
                    </td>
                    <td>
                      <span
                        className={w.is_active ? 'badge badge-success' : 'badge badge-danger'}
                      >
                        {w.is_active ? 'Yes' : 'No'}
                      </span>
                    </td>
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
