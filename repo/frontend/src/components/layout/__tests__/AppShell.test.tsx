import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import AppShell from '../AppShell';
import { useAuthStore } from '@/stores/authStore';
import { useConnectivityStore } from '@/stores/connectivityStore';

// Avoid Sidebar/Header pulling in heavy/IndexedDB-bound modules in jsdom.
vi.mock('@/components/common/OfflineQueueIndicator', () => ({
  default: () => null,
}));
vi.mock('@/hooks/useAuth', () => ({
  useAuth: () => ({ user: null, logout: vi.fn() }),
}));

function renderShell(child: React.ReactNode = <div>page-body</div>) {
  return render(
    <MemoryRouter initialEntries={['/']}>
      <Routes>
        <Route element={<AppShell />}>
          <Route path="/" element={child} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('AppShell', () => {
  beforeEach(() => {
    useAuthStore.setState({ user: null, token: null, roles: [], isAuthenticated: false });
    // Default: fully online and a no-op startListening so periodic axios polling
    // never fires inside jsdom (which would emit unhandled rejections).
    useConnectivityStore.setState({
      isOnline: true,
      isBackendReachable: true,
      lastCheckedAt: null,
      startListening: () => () => {},
    } as never);
  });

  it('renders sidebar branding, header logout button and outlet content', () => {
    renderShell(<div>page-body</div>);

    expect(screen.getByText('Meridian')).toBeDefined(); // Sidebar brand
    expect(screen.getByText('Logout')).toBeDefined(); // Header
    expect(screen.getByText('page-body')).toBeDefined(); // Outlet
  });

  it('shows the offline banner when the connectivity store reports offline', () => {
    useConnectivityStore.setState({ isOnline: false, isBackendReachable: true, lastCheckedAt: null });
    renderShell();

    expect(
      screen.getByText(
        /You are offline\. Changes will not be saved until your connection is restored\./,
      ),
    ).toBeDefined();
  });

  it('shows the backend-down banner when offline backend is reported but network is online', () => {
    useConnectivityStore.setState({ isOnline: true, isBackendReachable: false, lastCheckedAt: null });
    renderShell();

    expect(
      screen.getByText(/Backend unavailable\. Some features may not work\. Retrying\.\.\./),
    ).toBeDefined();
  });

  it('subscribes to connectivity events on mount and unsubscribes on unmount', () => {
    const cleanup = vi.fn();
    const startListening = vi.fn(() => cleanup);
    useConnectivityStore.setState({ startListening } as never);

    const { unmount } = renderShell();
    expect(startListening).toHaveBeenCalledTimes(1);
    expect(cleanup).not.toHaveBeenCalled();

    unmount();
    expect(cleanup).toHaveBeenCalledTimes(1);
  });

  it('shows admin-only sidebar links when the current user has the admin role', () => {
    useAuthStore.setState({
      isAuthenticated: true,
      token: 't',
      user: { id: 'u', username: 'a', display_name: 'A' } as never,
      roles: [{ role: 'admin' } as never],
    });

    renderShell();
    expect(screen.getByText('Users')).toBeDefined();
    expect(screen.getByText('Mutation Queue')).toBeDefined();
  });

  it('hides admin-only sidebar links for non-admin users', () => {
    useAuthStore.setState({
      isAuthenticated: true,
      token: 't',
      user: { id: 'u', username: 'a', display_name: 'A' } as never,
      roles: [{ role: 'analyst' } as never],
    });

    renderShell();
    expect(screen.queryByText('Mutation Queue')).toBeNull();
    expect(screen.queryByText('Users')).toBeNull();
  });
});
