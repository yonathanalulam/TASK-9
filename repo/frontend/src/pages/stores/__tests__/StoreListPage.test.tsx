import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import StoreListPage from '../StoreListPage';

// Mock the API module — we test rendering behavior, not network calls
vi.mock('@/api/stores', () => ({
  listStores: vi.fn(),
}));

import { listStores } from '@/api/stores';
const mockListStores = listStores as ReturnType<typeof vi.fn>;

function renderWithProviders(ui: React.ReactElement): ReturnType<typeof render> {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

const MOCK_STORES = [
  {
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
  },
  {
    id: 'store-uuid-2',
    code: 'STR-002',
    name: 'Uptown Dark Store',
    store_type: 'DARK_STORE',
    status: 'ACTIVE',
    region_id: 'region-1',
    timezone: 'UTC',
    address_line_1: null,
    address_line_2: null,
    city: null,
    postal_code: null,
    latitude: null,
    longitude: null,
    is_active: true,
    created_at: '2026-01-02T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    version: 1,
  },
];

const MOCK_RESPONSE = {
  data: MOCK_STORES,
  meta: {
    request_id: 'req-1',
    timestamp: '2026-04-14T10:00:00Z',
    pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
  },
  error: null,
};

describe('StoreListPage — behavior tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinner while stores are being fetched', () => {
    // Never resolving promise simulates loading state
    mockListStores.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<StoreListPage />);

    expect(screen.getByText(/loading stores/i)).toBeDefined();
  });

  it('renders store names when fetch succeeds', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('Downtown Store')).toBeDefined();
      expect(screen.getByText('Uptown Dark Store')).toBeDefined();
    });
  });

  it('renders store codes in the table', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('STR-001')).toBeDefined();
      expect(screen.getByText('STR-002')).toBeDefined();
    });
  });

  it('renders store types in the table', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText('STORE')).toBeDefined();
      expect(screen.getByText('DARK_STORE')).toBeDefined();
    });
  });

  it('renders "Zones" link for each store', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      const zonesLinks = screen.getAllByText('Zones');
      expect(zonesLinks).toHaveLength(2);
    });
  });

  it('links each store name to its detail page', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      const link = screen.getByText('Downtown Store').closest('a');
      expect(link?.getAttribute('href')).toBe('/stores/store-uuid-1');
    });
  });

  it('renders error message when stores fail to load', async () => {
    mockListStores.mockRejectedValue(new Error('Network error'));
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByText(/failed to load stores/i)).toBeDefined();
    });
  });

  it('calls listStores with page 1 and per_page 20 on initial render', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(mockListStores).toHaveBeenCalledWith({ page: 1, per_page: 20 });
    });
  });

  it('renders the "Stores" page heading', async () => {
    mockListStores.mockResolvedValue(MOCK_RESPONSE);
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Stores' })).toBeDefined();
    });
  });

  it('renders an empty table (no rows) when API returns empty data', async () => {
    mockListStores.mockResolvedValue({
      ...MOCK_RESPONSE,
      data: [],
      meta: { ...MOCK_RESPONSE.meta, pagination: { page: 1, per_page: 20, total: 0, total_pages: 0 } },
    });
    renderWithProviders(<StoreListPage />);

    await waitFor(() => {
      // No store names should appear
      expect(screen.queryByText('Downtown Store')).toBeNull();
    });
  });
});
