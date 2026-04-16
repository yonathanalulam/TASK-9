import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import RegionListPage from '../RegionListPage';

// Mock the regions API
vi.mock('@/api/regions', () => ({
  listRegions: vi.fn(),
}));

// Mock LoadingSpinner for easy detection
vi.mock('@/components/common/LoadingSpinner', () => ({
  default: ({ message }: { message?: string }) => (
    <div data-testid="loading-spinner">{message}</div>
  ),
}));

import { listRegions } from '@/api/regions';

const mockRegions = [
  {
    id: 'region-1',
    code: 'US-WEST',
    name: 'US West',
    parent_id: null,
    hierarchy_level: 1,
    effective_from: '2026-01-01',
    effective_until: null,
    is_active: true,
    version: 3,
  },
  {
    id: 'region-2',
    code: 'US-EAST',
    name: 'US East',
    parent_id: null,
    hierarchy_level: 1,
    effective_from: '2026-01-01',
    effective_until: '2026-12-31',
    is_active: false,
    version: 1,
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

describe('RegionListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing and shows loading state', () => {
    vi.mocked(listRegions).mockReturnValue(new Promise(() => {}));

    renderWithProviders(<RegionListPage />);
    expect(screen.getByTestId('loading-spinner')).toBeDefined();
    expect(screen.getByText('Loading regions...')).toBeDefined();
  });

  it('renders region list after loading', async () => {
    vi.mocked(listRegions).mockResolvedValue({
      data: mockRegions,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<RegionListPage />);

    await waitFor(() => {
      expect(screen.getByText('US West')).toBeDefined();
    });

    expect(screen.getByText('US East')).toBeDefined();
    expect(screen.getByText('US-WEST')).toBeDefined();
    expect(screen.getByText('US-EAST')).toBeDefined();
    expect(screen.getByText('Regions')).toBeDefined();
  });

  it('renders status badges for active/inactive regions', async () => {
    vi.mocked(listRegions).mockResolvedValue({
      data: mockRegions,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<RegionListPage />);

    await waitFor(() => {
      expect(screen.getByText('Active')).toBeDefined();
    });

    expect(screen.getByText('Inactive')).toBeDefined();
  });

  it('renders version numbers', async () => {
    vi.mocked(listRegions).mockResolvedValue({
      data: mockRegions,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<RegionListPage />);

    await waitFor(() => {
      expect(screen.getByText('v3')).toBeDefined();
    });

    expect(screen.getByText('v1')).toBeDefined();
  });

  it('shows error message when API fails', async () => {
    vi.mocked(listRegions).mockRejectedValue(new Error('Connection refused'));

    renderWithProviders(<RegionListPage />);

    await waitFor(() => {
      expect(screen.getByText(/Failed to load regions/)).toBeDefined();
    });
  });
});
