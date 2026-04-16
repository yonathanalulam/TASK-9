import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import DataClassificationPage from '../DataClassificationPage';

const { mockListClassifications } = vi.hoisted(() => ({
  mockListClassifications: vi.fn(),
}));

vi.mock('@/api/governance', () => ({
  listClassifications: mockListClassifications,
  createClassification: vi.fn(),
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

describe('DataClassificationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockListClassifications.mockResolvedValue({
      data: [
        {
          id: 'cls-1',
          entity_type: 'content',
          entity_id: 'c-1',
          entity_name: 'Secret Document',
          classification: 'RESTRICTED',
          justification: 'Contains PII',
          classified_by: 'admin',
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
    mockListClassifications.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<DataClassificationPage />);
    expect(screen.getByText('Loading classifications...')).toBeDefined();
  });

  it('renders the page heading', async () => {
    renderWithProviders(<DataClassificationPage />);
    await waitFor(() => {
      expect(screen.getByText('Data Classifications')).toBeDefined();
    });
  });

  it('renders classification data after loading', async () => {
    renderWithProviders(<DataClassificationPage />);
    await waitFor(() => {
      expect(screen.getByText('Secret Document')).toBeDefined();
    });
  });
});
