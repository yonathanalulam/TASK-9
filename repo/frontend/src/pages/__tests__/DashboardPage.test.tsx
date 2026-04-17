import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import DashboardPage from '../DashboardPage';
import { useAuthStore } from '@/stores/authStore';

const { mockListRegions, mockListStores, mockListUsers } = vi.hoisted(() => ({
  mockListRegions: vi.fn(),
  mockListStores: vi.fn(),
  mockListUsers: vi.fn(),
}));

vi.mock('@/api/regions', () => ({ listRegions: mockListRegions }));
vi.mock('@/api/stores', () => ({ listStores: mockListStores }));
vi.mock('@/api/users', () => ({ listUsers: mockListUsers }));

function paginated(total: number) {
  return {
    data: [],
    meta: {
      request_id: 'r',
      timestamp: '2026-01-01T00:00:00Z',
      pagination: { page: 1, per_page: 1, total, total_pages: 1 },
    },
    error: null,
  };
}

function renderDashboard() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <DashboardPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthStore.setState({
      user: {
        id: 'u-1',
        username: 'admin',
        display_name: 'Ada Admin',
        status: 'ACTIVE',
        version: 1,
        created_at: '',
        updated_at: '',
        password_changed_at: null,
      } as never,
      token: 'tok',
      roles: [],
      isAuthenticated: true,
    });
  });

  it('greets the signed-in user by display name', async () => {
    mockListRegions.mockResolvedValue(paginated(3));
    mockListStores.mockResolvedValue(paginated(7));
    mockListUsers.mockResolvedValue(paginated(11));

    renderDashboard();
    expect(screen.getByText(/Welcome back, Ada Admin\./)).toBeDefined();
  });

  it('shows a loading spinner while any summary query is pending', () => {
    // Regions never resolves → isLoading stays true.
    mockListRegions.mockReturnValue(new Promise(() => {}));
    mockListStores.mockResolvedValue(paginated(7));
    mockListUsers.mockResolvedValue(paginated(11));

    renderDashboard();
    expect(screen.getByText('Loading summary...')).toBeDefined();
  });

  it('renders region/store/user totals from the API responses', async () => {
    mockListRegions.mockResolvedValue(paginated(3));
    mockListStores.mockResolvedValue(paginated(7));
    mockListUsers.mockResolvedValue(paginated(11));

    renderDashboard();

    await waitFor(() => {
      expect(screen.getByText('Regions')).toBeDefined();
    });

    expect(screen.getByText('3')).toBeDefined();
    expect(screen.getByText('7')).toBeDefined();
    expect(screen.getByText('11')).toBeDefined();
  });

  it('uses the correct page-size when calling each API', async () => {
    mockListRegions.mockResolvedValue(paginated(0));
    mockListStores.mockResolvedValue(paginated(0));
    mockListUsers.mockResolvedValue(paginated(0));

    renderDashboard();

    await waitFor(() => {
      expect(mockListRegions).toHaveBeenCalledWith({ page: 1, per_page: 1 });
      expect(mockListStores).toHaveBeenCalledWith({ page: 1, per_page: 1 });
      expect(mockListUsers).toHaveBeenCalledWith(1, 1);
    });
  });

  it('renders "--" placeholders when an API call errors out', async () => {
    mockListRegions.mockRejectedValue(new Error('boom'));
    mockListStores.mockResolvedValue(paginated(7));
    mockListUsers.mockResolvedValue(paginated(11));

    renderDashboard();

    await waitFor(() => {
      // Regions card should show '--' for error
      expect(screen.getByText('Regions')).toBeDefined();
    });

    // Stats with zero/missing render as '--'; the error path renders one '--'.
    const placeholders = screen.getAllByText('--');
    expect(placeholders.length).toBeGreaterThanOrEqual(1);
  });

  it('falls back to "User" greeting when no user is loaded', async () => {
    useAuthStore.setState({ user: null, token: null, roles: [], isAuthenticated: false });
    mockListRegions.mockResolvedValue(paginated(0));
    mockListStores.mockResolvedValue(paginated(0));
    mockListUsers.mockResolvedValue(paginated(0));

    renderDashboard();
    expect(screen.getByText(/Welcome back, User\./)).toBeDefined();
  });
});
