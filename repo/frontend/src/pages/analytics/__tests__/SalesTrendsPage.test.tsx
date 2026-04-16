import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import SalesTrendsPage from '../SalesTrendsPage';

const { mockGetSalesTrends } = vi.hoisted(() => ({
  mockGetSalesTrends: vi.fn(),
}));

vi.mock('@/api/analytics', () => ({
  getSalesTrends: mockGetSalesTrends,
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

describe('SalesTrendsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetSalesTrends.mockResolvedValue({
      data: [
        { date: '2026-01-01', gross_sales: 1000, net_sales: 900, quantity: 10 },
        { date: '2026-01-02', gross_sales: 1200, net_sales: 1050, quantity: 12 },
      ],
      meta: { request_id: 'req-1', timestamp: '2026-01-02T00:00:00Z' },
      error: null,
    });
  });

  it('shows loading spinner initially', () => {
    mockGetSalesTrends.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<SalesTrendsPage />);
    expect(screen.getByText('Loading trends...')).toBeDefined();
  });

  it('renders the page heading', async () => {
    renderWithProviders(<SalesTrendsPage />);
    await waitFor(() => {
      expect(screen.getByText('Sales Trends')).toBeDefined();
    });
  });

  it('renders granularity selector', async () => {
    renderWithProviders(<SalesTrendsPage />);
    await waitFor(() => {
      expect(screen.getByText('Sales Trends')).toBeDefined();
    });
    expect(screen.getByDisplayValue('Day')).toBeDefined();
  });

  it('renders trend data in the table', async () => {
    renderWithProviders(<SalesTrendsPage />);
    await waitFor(() => {
      expect(screen.getByText('2026-01-01')).toBeDefined();
    });
    expect(screen.getByText('2026-01-02')).toBeDefined();
  });
});
