import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getUser } from '@/api/users';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import dayjs from 'dayjs';

export default function UserDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data: envelope, isLoading, isError, error } = useQuery({
    queryKey: ['user', id],
    queryFn: () => getUser(id!),
    enabled: !!id,
  });

  if (isLoading) return <LoadingSpinner message="Loading user..." />;
  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load user: {(error as Error).message}
      </div>
    );
  }

  const user = envelope?.data;
  if (!user) return null;

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/users" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Users
          </Link>{' '}
          / {user.username}
        </h1>
        <span className={user.status === 'active' ? 'badge badge-success' : 'badge badge-danger'}>
          {user.status}
        </span>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
        {/* Info card */}
        <div className="card">
          <h2 style={{ fontSize: 16, marginBottom: 16 }}>User Information</h2>
          <InfoRow label="Username" value={user.username} />
          <InfoRow label="Display Name" value={user.display_name} />
          <InfoRow label="Status" value={user.status} />
          <InfoRow label="Created" value={dayjs(user.created_at).format('MMM D, YYYY h:mm A')} />
          <InfoRow label="Updated" value={dayjs(user.updated_at).format('MMM D, YYYY h:mm A')} />
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
