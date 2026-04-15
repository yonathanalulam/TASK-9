import { useAuth } from '@/hooks/useAuth';
import { useConnectivityStore } from '@/stores/connectivityStore';
import OfflineQueueIndicator from '@/components/common/OfflineQueueIndicator';

export default function Header() {
  const { user, logout } = useAuth();
  const isOnline = useConnectivityStore((s) => s.isOnline);
  const isBackendReachable = useConnectivityStore((s) => s.isBackendReachable);

  const statusColor = !isOnline
    ? 'var(--color-danger)'
    : !isBackendReachable
      ? 'var(--color-warning)'
      : 'var(--color-success)';

  const statusLabel = !isOnline
    ? 'Offline'
    : !isBackendReachable
      ? 'Backend down'
      : 'Online';

  return (
    <header
      style={{
        height: 52,
        background: 'var(--color-surface)',
        borderBottom: '1px solid var(--color-border)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'flex-end',
        padding: '0 24px',
        gap: 16,
      }}
    >
      {/* Offline queue indicator */}
      <OfflineQueueIndicator />

      {/* Connectivity dot */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
        <span
          style={{
            width: 8,
            height: 8,
            borderRadius: '50%',
            background: statusColor,
            display: 'inline-block',
          }}
        />
        <span style={{ color: 'var(--color-text-muted)' }}>{statusLabel}</span>
      </div>

      {/* User info */}
      {user && (
        <span style={{ fontSize: 13, color: 'var(--color-text)' }}>
          {user.display_name}
        </span>
      )}

      <button className="btn btn-secondary" style={{ padding: '4px 12px', fontSize: 13 }} onClick={logout}>
        Logout
      </button>
    </header>
  );
}
