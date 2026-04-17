import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import ZoneListPage from '../ZoneListPage';

const { mockListDeliveryZones, mockGetStore } = vi.hoisted(() => ({
  mockListDeliveryZones: vi.fn(),
  mockGetStore: vi.fn(),
}));

vi.mock('@/api/deliveryZones', () => ({
  listDeliveryZones: mockListDeliveryZones,
}));
vi.mock('@/api/stores', () => ({
  getStore: mockGetStore,
}));

function renderAtStore(storeId: string) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/stores/${storeId}/zones`]}>
        <Routes>
          <Route path="/stores/:storeId/zones" element={<ZoneListPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function zoneEnvelope(zones: Array<Partial<{ id: string; name: string; status: string; version: number }>>) {
  return {
    data: zones.map((z) => ({
      id: z.id ?? 'z',
      store_id: 'store-9',
      name: z.name ?? 'Zone',
      status: z.status ?? 'active',
      is_active: true,
      version: z.version ?? 1,
      created_at: '',
      updated_at: '',
    })),
    meta: {
      request_id: 'r',
      timestamp: '',
      pagination: { page: 1, per_page: 20, total: zones.length, total_pages: 1 },
    },
    error: null,
  };
}

describe('ZoneListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetStore.mockResolvedValue({
      data: { id: 'store-9', name: 'North Hub Store' },
      meta: {},
      error: null,
    });
  });

  it('shows a loading spinner while zones are loading', () => {
    mockListDeliveryZones.mockReturnValue(new Promise(() => {}));
    renderAtStore('store-9');
    expect(screen.getByText('Loading zones...')).toBeDefined();
  });

  it('renders rows with zone name, status badge and version', async () => {
    mockListDeliveryZones.mockResolvedValue(
      zoneEnvelope([
        { id: 'z-1', name: 'North Zone', status: 'active', version: 4 },
        { id: 'z-2', name: 'South Zone', status: 'inactive', version: 1 },
      ]),
    );

    renderAtStore('store-9');

    await waitFor(() => {
      expect(screen.getByText('North Zone')).toBeDefined();
    });
    expect(screen.getByText('South Zone')).toBeDefined();
    expect(screen.getByText('active')).toBeDefined();
    expect(screen.getByText('inactive')).toBeDefined();
    expect(screen.getByText('v4')).toBeDefined();
    expect(screen.getByText('v1')).toBeDefined();
  });

  it('renders the resolved store name in the breadcrumb header', async () => {
    mockListDeliveryZones.mockResolvedValue(zoneEnvelope([]));
    renderAtStore('store-9');

    await waitFor(() => {
      expect(screen.getByText('North Hub Store')).toBeDefined();
    });
  });

  it('passes the storeId from the URL to listDeliveryZones', async () => {
    mockListDeliveryZones.mockResolvedValue(zoneEnvelope([]));
    renderAtStore('store-9');
    await waitFor(() => {
      expect(mockListDeliveryZones).toHaveBeenCalledWith(
        'store-9',
        expect.objectContaining({ page: 1, per_page: 20 }),
      );
    });
  });

  it('renders an error message when the zones query fails', async () => {
    mockListDeliveryZones.mockRejectedValue(new Error('network down'));
    renderAtStore('store-9');

    await waitFor(() => {
      expect(screen.getByText(/Failed to load zones: network down/)).toBeDefined();
    });
  });

  it('renders the empty-state message when there are no zones', async () => {
    mockListDeliveryZones.mockResolvedValue(zoneEnvelope([]));
    renderAtStore('store-9');

    await waitFor(() => {
      expect(screen.getByText(/no data found/i)).toBeDefined();
    });
  });
});
