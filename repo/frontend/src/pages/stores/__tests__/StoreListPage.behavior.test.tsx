import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import StoreListPage from '../StoreListPage';

/* ------------------------------------------------------------------ */
/*  Mock the API module at the boundary                                */
/* ------------------------------------------------------------------ */
vi.mock('@/api/stores', () => ({
  listStores: vi.fn(),
}));

import { listStores } from '@/api/stores';
const mockListStores = listStores as ReturnType<typeof vi.fn>;

/* ------------------------------------------------------------------ */
/*  Test helpers                                                       */
/* ------------------------------------------------------------------ */

function renderWithProviders(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

function makeStore(overrides: Record<string, unknown> = {}) {
  return {
    id: 'store-uuid-1',
    code: 'STR-001',
    name: 'Downtown Store',
    store_type: 'STORE',
    status: 'ACTIVE',
    region_id: 'region-1',
    timezone: 'UTC',
    address_line_1: '123 Main St',
    address_line_2: null,
    city: 'New York',
    postal_code: '10001',
    latitude: '40.7128',
    longitude: '-74.0060',
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    version: 1,
    ...overrides,
  };
}

function makeResponse(stores: unknown[], pagination = { page: 1, per_page: 20, total: 2, total_pages: 1 }) {
  return {
    data: stores,
    meta: {
      request_id: 'req-1',
      timestamp: '2026-04-14T10:00:00Z',
      pagination,
    },
    error: null,
  };
}

describe('StoreListPage — pagination and error behavior', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('displays pagination controls when there are multiple pages', async () => {
    const stores = [makeStore()];
    mockListStores.mockResolvedValue(
      makeResponse(stores, { page: 1, per_page: 20, total: 60, total_pages: 3 }),
    );

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('Downtown Store')).toBeInTheDocument();
    });

    // Pagination controls
    expect(screen.getByText('Previous')).toBeInTheDocument();
    expect(screen.getByText('Next')).toBeInTheDocument();
    expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
  });

  it('Previous button is disabled on first page', async () => {
    mockListStores.mockResolvedValue(
      makeResponse([makeStore()], { page: 1, per_page: 20, total: 60, total_pages: 3 }),
    );

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('Downtown Store')).toBeInTheDocument();
    });

    const prevButton = screen.getByText('Previous');
    expect(prevButton).toBeDisabled();
  });

  it('clicking Next fetches page 2', async () => {
    const user = userEvent.setup();

    // Page 1
    mockListStores.mockResolvedValueOnce(
      makeResponse([makeStore()], { page: 1, per_page: 20, total: 40, total_pages: 2 }),
    );

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('Downtown Store')).toBeInTheDocument();
    });

    // Prepare page 2 response
    mockListStores.mockResolvedValueOnce(
      makeResponse(
        [makeStore({ id: 'store-2', name: 'Uptown Store', code: 'STR-002' })],
        { page: 2, per_page: 20, total: 40, total_pages: 2 },
      ),
    );

    // Click Next
    await user.click(screen.getByText('Next'));

    await waitFor(() => {
      expect(mockListStores).toHaveBeenCalledWith({ page: 2, per_page: 20 });
    });
  });

  it('does not show pagination when there is only one page', async () => {
    mockListStores.mockResolvedValue(
      makeResponse([makeStore()], { page: 1, per_page: 20, total: 1, total_pages: 1 }),
    );

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('Downtown Store')).toBeInTheDocument();
    });

    expect(screen.queryByText('Previous')).not.toBeInTheDocument();
    expect(screen.queryByText('Next')).not.toBeInTheDocument();
  });

  it('shows error message with error details on API failure', async () => {
    mockListStores.mockRejectedValue(new Error('503 Service Unavailable'));

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText(/Failed to load stores/)).toBeInTheDocument();
      expect(screen.getByText(/503 Service Unavailable/)).toBeInTheDocument();
    });
  });

  it('does not show error state or loading when data loads successfully', async () => {
    mockListStores.mockResolvedValue(makeResponse([makeStore()]));

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('Downtown Store')).toBeInTheDocument();
    });

    expect(screen.queryByText(/Failed to load stores/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Loading stores/i)).not.toBeInTheDocument();
  });

  it('shows "No data found." when API returns empty data', async () => {
    mockListStores.mockResolvedValue(
      makeResponse([], { page: 1, per_page: 20, total: 0, total_pages: 0 }),
    );

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('No data found.')).toBeInTheDocument();
    });
  });

  it('renders status text with underscores replaced by spaces', async () => {
    mockListStores.mockResolvedValue(
      makeResponse([makeStore({ status: 'temporarily_closed' })]),
    );

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('temporarily closed')).toBeInTheDocument();
    });
  });

  it('renders Zones links that point to the correct path', async () => {
    mockListStores.mockResolvedValue(makeResponse([makeStore()]));

    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      const zonesLink = screen.getByText('Zones').closest('a');
      expect(zonesLink?.getAttribute('href')).toBe('/stores/store-uuid-1/zones');
    });
  });
});
