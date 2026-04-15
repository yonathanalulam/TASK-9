import { useConnectivityStore } from '@/stores/connectivityStore';

const bannerStyle: React.CSSProperties = {
  padding: '8px 16px',
  textAlign: 'center',
  fontSize: '13px',
  fontWeight: 500,
  color: '#fff',
};

export default function ConnectivityBanner() {
  const isOnline = useConnectivityStore((s) => s.isOnline);
  const isBackendReachable = useConnectivityStore((s) => s.isBackendReachable);

  if (!isOnline) {
    return (
      <div style={{ ...bannerStyle, background: 'var(--color-danger)' }}>
        You are offline. Changes will not be saved until your connection is restored.
      </div>
    );
  }

  if (!isBackendReachable) {
    return (
      <div style={{ ...bannerStyle, background: 'var(--color-warning)' }}>
        Backend unavailable. Some features may not work. Retrying...
      </div>
    );
  }

  return null;
}
