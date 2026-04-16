import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import WarehouseLoadsPage from '../WarehouseLoadsPage';

const { mockListLoadRuns } = vi.hoisted(() => ({
  mockListLoadRuns: vi.fn(),
}));

vi.mock('@/api/warehouse', () => ({
  listLoadRuns: mockListLoadRuns,
  triggerLoad: vi.fn(),
}));

function renderWithProviders(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{ui}</BrowserRouter>
    </QueryClientProvider>,
  );
}

describe('WarehouseLoadsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockListLoadRuns.mockResolvedValue({
      data: [
        {
          id: 'load-1',
          load_type: 'full',
          status: 'COMPLETED',
          rows_extracted: 500,
          rows_loaded: 498,
          rows_rejected: 2,
          rejected_details: [{ row_index: 10, reason: 'Invalid format', data: { id: 'bad' } }],
          started_at: '2026-01-01T00:00:00Z',
          completed_at: '2026-01-01T00:05:00Z',
          duration_ms: 300000,
          triggered_by: 'admin',
          error: null,
          created_at: '2026-01-01',
          updated_at: '2026-01-01',
        },
      ],
      meta: {
        request_id: 'req-1',
        timestamp: '2026-01-01T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 },
      },
      error: null,
    });
  });

  it('shows loading spinner initially', () => {
    mockListLoadRuns.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<WarehouseLoadsPage />);
    expect(screen.getByText('Loading warehouse loads...')).toBeDefined();
  });

  it('renders the page heading', async () => {
    renderWithProviders(<WarehouseLoadsPage />);
    await waitFor(() => {
      expect(screen.getByText('Warehouse Loads')).toBeDefined();
    });
  });

  it('renders the Trigger Load button', async () => {
    renderWithProviders(<WarehouseLoadsPage />);
    await waitFor(() => {
      expect(screen.getByText('Trigger Load')).toBeDefined();
    });
  });

  it('renders load run data in the table', async () => {
    renderWithProviders(<WarehouseLoadsPage />);
    await waitFor(() => {
      expect(screen.getByText('full')).toBeDefined();
    });
    expect(screen.getByText('COMPLETED')).toBeDefined();
    expect(screen.getByText('500')).toBeDefined();
    expect(screen.getByText('498')).toBeDefined();
    expect(screen.getByText('2')).toBeDefined();
  });

  it('renders duration formatted', async () => {
    renderWithProviders(<WarehouseLoadsPage />);
    await waitFor(() => {
      expect(screen.getByText('300.0s')).toBeDefined();
    });
  });
});
