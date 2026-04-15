import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listUsers } from '@/api/users';
import type { User } from '@/api/types';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

const columns: Column<User>[] = [
  {
    key: 'username',
    header: 'Username',
    render: (u) => (
      <Link to={`/users/${u.id}`} style={{ fontWeight: 500 }}>
        {u.username}
      </Link>
    ),
  },
  {
    key: 'name',
    header: 'Name',
    render: (u) => u.display_name,
  },
  {
    key: 'status',
    header: 'Status',
    width: '100px',
    render: (u) => (
      <span className={u.status === 'active' ? 'badge badge-success' : 'badge badge-danger'}>
        {u.status}
      </span>
    ),
  },
];

export default function UserListPage() {
  const [page, setPage] = useState(1);
  const per_page = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['users', page, per_page],
    queryFn: () => listUsers(page, per_page),
  });

  if (isLoading) return <LoadingSpinner message="Loading users..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load users: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Users</h1>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(u) => u.id}
      />
    </div>
  );
}
