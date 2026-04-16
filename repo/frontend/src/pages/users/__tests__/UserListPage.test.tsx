import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import UserListPage from '../UserListPage';

// Mock the users API
vi.mock('@/api/users', () => ({
  listUsers: vi.fn(),
}));

// Mock LoadingSpinner for easy detection
vi.mock('@/components/common/LoadingSpinner', () => ({
  default: ({ message }: { message?: string }) => (
    <div data-testid="loading-spinner">{message}</div>
  ),
}));

import { listUsers } from '@/api/users';

const mockUsers = [
  {
    id: 'user-1',
    username: 'jdoe',
    display_name: 'John Doe',
    status: 'active',
    last_login_at: '2026-04-10T00:00:00Z',
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-04-10T00:00:00Z',
    version: 1,
  },
  {
    id: 'user-2',
    username: 'asmith',
    display_name: 'Alice Smith',
    status: 'inactive',
    last_login_at: null,
    created_at: '2026-02-01T00:00:00Z',
    updated_at: '2026-03-01T00:00:00Z',
    version: 2,
  },
];

function renderWithProviders(ui: React.ReactElement) {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('UserListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing and shows loading state', () => {
    vi.mocked(listUsers).mockReturnValue(new Promise(() => {}));

    renderWithProviders(<UserListPage />);
    expect(screen.getByTestId('loading-spinner')).toBeDefined();
    expect(screen.getByText('Loading users...')).toBeDefined();
  });

  it('renders user list after loading', async () => {
    vi.mocked(listUsers).mockResolvedValue({
      data: mockUsers,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<UserListPage />);

    await waitFor(() => {
      expect(screen.getByText('jdoe')).toBeDefined();
    });

    expect(screen.getByText('asmith')).toBeDefined();
    expect(screen.getByText('John Doe')).toBeDefined();
    expect(screen.getByText('Alice Smith')).toBeDefined();
    expect(screen.getByText('Users')).toBeDefined();
  });

  it('renders status badges for users', async () => {
    vi.mocked(listUsers).mockResolvedValue({
      data: mockUsers,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<UserListPage />);

    await waitFor(() => {
      expect(screen.getByText('active')).toBeDefined();
    });

    expect(screen.getByText('inactive')).toBeDefined();
  });

  it('shows error message when API fails', async () => {
    vi.mocked(listUsers).mockRejectedValue(new Error('Server error'));

    renderWithProviders(<UserListPage />);

    await waitFor(() => {
      expect(screen.getByText(/Failed to load users/)).toBeDefined();
    });
  });
});
