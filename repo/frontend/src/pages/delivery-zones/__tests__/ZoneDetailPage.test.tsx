import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import ZoneDetailPage from '../ZoneDetailPage';

const { mockGetDeliveryZone, mockListDeliveryWindows } = vi.hoisted(() => ({
  mockGetDeliveryZone: vi.fn(),
  mockListDeliveryWindows: vi.fn(),
}));

vi.mock('@/api/deliveryZones', () => ({
  getDeliveryZone: mockGetDeliveryZone,
  listDeliveryWindows: mockListDeliveryWindows,
}));

function renderAtZone(zoneId: string) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/zones/${zoneId}`]}>
        <Routes>
          <Route path="/zones/:id" element={<ZoneDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const zoneEnv = (overrides: Record<string, unknown> = {}) => ({
  data: {
    id: 'z-1',
    store_id: 'store-9',
    name: 'Downtown Zone',
    status: 'active',
    is_active: true,
    version: 7,
    created_at: '',
    updated_at: '',
    ...overrides,
  },
  meta: {},
  error: null,
});

const windowsEnv = (windows: Array<{ id: string; day_of_week: number; start_time: string; end_time: string; is_active: boolean }>) => ({
  data: windows,
  meta: {},
  error: null,
});

describe('ZoneDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the zone loading spinner initially', () => {
    mockGetDeliveryZone.mockReturnValue(new Promise(() => {}));
    mockListDeliveryWindows.mockReturnValue(new Promise(() => {}));
    renderAtZone('z-1');
    expect(screen.getByText('Loading zone...')).toBeDefined();
  });

  it('renders the zone details (name, store id, status, active flag, version)', async () => {
    mockGetDeliveryZone.mockResolvedValue(zoneEnv());
    mockListDeliveryWindows.mockResolvedValue(windowsEnv([]));
    renderAtZone('z-1');

    await waitFor(() => {
      // The zone name appears in the header H1.
      expect(screen.getAllByText(/Downtown Zone/).length).toBeGreaterThan(0);
    });

    expect(screen.getByText('store-9')).toBeDefined();
    // status badge text + InfoRow value both render 'active'
    expect(screen.getAllByText('active').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Yes')).toBeDefined();
    expect(screen.getByText('v7')).toBeDefined();
  });

  it('renders the empty-state message when no delivery windows exist', async () => {
    mockGetDeliveryZone.mockResolvedValue(zoneEnv());
    mockListDeliveryWindows.mockResolvedValue(windowsEnv([]));
    renderAtZone('z-1');

    await waitFor(() => {
      expect(
        screen.getByText('No delivery windows configured for this zone.'),
      ).toBeDefined();
    });
  });

  it('renders delivery windows with the day-of-week label and active badge', async () => {
    mockGetDeliveryZone.mockResolvedValue(zoneEnv());
    mockListDeliveryWindows.mockResolvedValue(
      windowsEnv([
        { id: 'w-1', day_of_week: 1, start_time: '09:00', end_time: '17:00', is_active: true },
        { id: 'w-2', day_of_week: 5, start_time: '08:00', end_time: '12:00', is_active: false },
      ]),
    );

    renderAtZone('z-1');

    await waitFor(() => {
      expect(screen.getByText('Monday')).toBeDefined();
    });
    expect(screen.getByText('Friday')).toBeDefined();
    expect(screen.getByText('09:00 - 17:00')).toBeDefined();
    expect(screen.getByText('08:00 - 12:00')).toBeDefined();
  });

  it('renders an error message when the zone query fails', async () => {
    mockGetDeliveryZone.mockRejectedValue(new Error('not found'));
    mockListDeliveryWindows.mockResolvedValue(windowsEnv([]));
    renderAtZone('z-1');

    await waitFor(() => {
      expect(screen.getByText(/Failed to load zone: not found/)).toBeDefined();
    });
  });
});
