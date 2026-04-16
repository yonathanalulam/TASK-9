import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import SalesByProductPage from '../SalesByProductPage';

const { mockGetSalesByDimensions } = vi.hoisted(() => ({
  mockGetSalesByDimensions: vi.fn(),
}));

vi.mock('@/api/analytics', () => ({
  getSalesByDimensions: mockGetSalesByDimensions,
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

describe('SalesByProductPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetSalesByDimensions.mockResolvedValue({
      data: [
        {
          product: 'Widget A',
          category: 'Widgets',
          gross_sales: 5000,
          net_sales: 4500,
          quantity: 50,
          order_count: 20,
        },
      ],
      meta: { request_id: 'req-1', timestamp: '2026-01-01T00:00:00Z' },
      error: null,
    });
  });

  it('shows loading spinner initially', () => {
    mockGetSalesByDimensions.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<SalesByProductPage />);
    expect(screen.getByText('Loading sales data...')).toBeDefined();
  });

  it('renders the page heading', async () => {
    renderWithProviders(<SalesByProductPage />);
    await waitFor(() => {
      expect(screen.getByText('Sales by Product')).toBeDefined();
    });
  });

  it('renders filter inputs', async () => {
    renderWithProviders(<SalesByProductPage />);
    await waitFor(() => {
      expect(screen.getByText('Sales by Product')).toBeDefined();
    });
    expect(screen.getByPlaceholderText('All regions')).toBeDefined();
    expect(screen.getByPlaceholderText('All channels')).toBeDefined();
  });

  it('renders product data in the table', async () => {
    renderWithProviders(<SalesByProductPage />);
    await waitFor(() => {
      expect(screen.getByText('Widget A')).toBeDefined();
    });
    expect(screen.getByText('Widgets')).toBeDefined();
  });

  it('renders a Total summary row', async () => {
    renderWithProviders(<SalesByProductPage />);
    await waitFor(() => {
      expect(screen.getByText('Total')).toBeDefined();
    });
  });
});
