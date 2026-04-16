import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import ExportListPage from '../ExportListPage';

const sampleExportData = {
  data: [
    {
      id: 'export-1',
      dataset: 'content_items',
      format: 'CSV',
      status: 'SUCCEEDED',
      requested_by: 'admin',
      authorized_by: null,
      filters: {},
      file_name: 'export.csv',
      watermark_text: null,
      tamper_hash_sha256: null,
      requested_at: '2026-04-14T10:00:00+00:00',
      authorized_at: null,
      completed_at: '2026-04-14T10:01:00+00:00',
      expires_at: '2026-04-21T10:01:00+00:00',
    },
  ],
  meta: {
    request_id: 'req-1',
    timestamp: '2026-04-14T10:00:00Z',
    pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 },
  },
  error: null,
};

// Hoist mock functions so they can be reassigned per-test
const { mockListExports, mockDownloadExport } = vi.hoisted(() => ({
  mockListExports: vi.fn(),
  mockDownloadExport: vi.fn(),
}));

vi.mock('@/api/exports', () => ({
  listExports: mockListExports,
  downloadExport: mockDownloadExport,
  DATASET_LABELS: {
    content_items: 'Content Items',
    audit_events: 'Audit Events',
  },
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

describe('ExportListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default: resolve with sample data
    mockListExports.mockResolvedValue(sampleExportData);
  });

  it('shows loading spinner initially', () => {
    // Override with a never-resolving promise to keep loading state
    mockListExports.mockReturnValue(new Promise(() => {}));

    renderWithProviders(<ExportListPage />);

    expect(screen.getByText('Loading exports...')).toBeDefined();
  });

  it('renders the page heading after data loads', async () => {
    renderWithProviders(<ExportListPage />);

    await waitFor(() => {
      expect(screen.getByText('Exports')).toBeDefined();
    });
  });

  it('renders a New Export link', async () => {
    renderWithProviders(<ExportListPage />);

    await waitFor(() => {
      expect(screen.getByText('New Export')).toBeDefined();
    });

    const link = screen.getByText('New Export');
    expect(link.closest('a')?.getAttribute('href')).toBe('/exports/new');
  });

  it('renders export data after loading', async () => {
    renderWithProviders(<ExportListPage />);

    await waitFor(() => {
      expect(screen.getByText('Content Items')).toBeDefined();
    });

    expect(screen.getByText('CSV')).toBeDefined();
    expect(screen.getByText('SUCCEEDED')).toBeDefined();
    expect(screen.getByText('admin')).toBeDefined();
  });

  it('renders a Download button for succeeded exports', async () => {
    renderWithProviders(<ExportListPage />);

    await waitFor(() => {
      const downloadBtn = screen.getByRole('button', { name: 'Download' });
      expect(downloadBtn).toBeDefined();
    });
  });
});
