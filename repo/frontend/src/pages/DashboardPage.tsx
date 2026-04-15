import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { listRegions } from '@/api/regions';
import { listStores } from '@/api/stores';
import { listUsers } from '@/api/users';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user);

  const regionsQuery = useQuery({
    queryKey: ['regions', 'summary'],
    queryFn: () => listRegions({ page: 1, per_page: 1 }),
  });

  const storesQuery = useQuery({
    queryKey: ['stores', 'summary'],
    queryFn: () => listStores({ page: 1, per_page: 1 }),
  });

  const usersQuery = useQuery({
    queryKey: ['users', 'summary'],
    queryFn: () => listUsers(1, 1),
  });

  const isLoading = regionsQuery.isLoading || storesQuery.isLoading || usersQuery.isLoading;

  return (
    <div>
      <div className="page-header">
        <h1>Dashboard</h1>
      </div>

      <p style={{ color: 'var(--color-text-muted)', marginBottom: 24 }}>
        Welcome back, {user?.display_name ?? 'User'}.
      </p>

      {isLoading ? (
        <LoadingSpinner message="Loading summary..." />
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16 }}>
          <StatCard
            label="Regions"
            value={regionsQuery.data?.meta?.pagination?.total}
            error={regionsQuery.isError}
          />
          <StatCard
            label="Stores"
            value={storesQuery.data?.meta?.pagination?.total}
            error={storesQuery.isError}
          />
          <StatCard
            label="Users"
            value={usersQuery.data?.meta?.pagination?.total}
            error={usersQuery.isError}
          />
        </div>
      )}
    </div>
  );
}

function StatCard({
  label,
  value,
  error,
}: {
  label: string;
  value?: number;
  error: boolean;
}) {
  return (
    <div className="card" style={{ textAlign: 'center' }}>
      <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 4 }}>
        {label}
      </div>
      <div style={{ fontSize: 28, fontWeight: 700 }}>
        {error ? '--' : (value ?? '--')}
      </div>
    </div>
  );
}
