import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import ImportBatchListPage from '../ImportBatchListPage';

const { mockListImports } = vi.hoisted(() => ({
  mockListImports: vi.fn(),
}));

vi.mock('@/api/imports', () => ({
  listImports: mockListImports,
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

describe('ImportBatchListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockListImports.mockResolvedValue({
      data: [
        {
          id: 'imp-1',
          filename: 'products.csv',
          format: 'csv',
          status: 'COMPLETED',
          total_items: 100,
          processed_items: 100,
          duplicate_items: 3,
          error_items: 0,
          uploaded_by: 'admin',
          created_at: '2026-01-15T10:00:00Z',
          updated_at: '2026-01-15T10:05:00Z',
        },
      ],
      meta: {
        request_id: 'req-1',
        timestamp: '2026-01-15T10:00:00Z',
        pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 },
      },
      error: null,
    });
  });

  it('shows loading spinner initially', () => {
    mockListImports.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<ImportBatchListPage />);
    expect(screen.getByText('Loading import batches...')).toBeDefined();
  });

  it('renders the page heading', async () => {
    renderWithProviders(<ImportBatchListPage />);
    await waitFor(() => {
      expect(screen.getByText('Import Batches')).toBeDefined();
    });
  });

  it('renders Upload Import link', async () => {
    renderWithProviders(<ImportBatchListPage />);
    await waitFor(() => {
      expect(screen.getByText('Upload Import')).toBeDefined();
    });
    const link = screen.getByText('Upload Import');
    expect(link.closest('a')?.getAttribute('href')).toBe('/imports/upload');
  });

  it('renders import data after loading', async () => {
    renderWithProviders(<ImportBatchListPage />);
    await waitFor(() => {
      expect(screen.getByText('products.csv')).toBeDefined();
    });
    expect(screen.getByText('csv')).toBeDefined();
    expect(screen.getByText('COMPLETED')).toBeDefined();
    expect(screen.getByText('admin')).toBeDefined();
  });

  it('renders status filter dropdown', async () => {
    renderWithProviders(<ImportBatchListPage />);
    await waitFor(() => {
      expect(screen.getByText('Import Batches')).toBeDefined();
    });
    expect(screen.getByDisplayValue('All Statuses')).toBeDefined();
  });
});
