import { useEffect } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Header from './Header';
import ConnectivityBanner from './ConnectivityBanner';
import { useConnectivityStore } from '@/stores/connectivityStore';

export default function AppShell() {
  const startListening = useConnectivityStore((s) => s.startListening);

  useEffect(() => {
    const cleanup = startListening();
    return cleanup;
  }, [startListening]);

  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      <Sidebar />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
        <Header />
        <ConnectivityBanner />
        <main style={{ flex: 1, padding: 24, overflow: 'auto' }}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}
