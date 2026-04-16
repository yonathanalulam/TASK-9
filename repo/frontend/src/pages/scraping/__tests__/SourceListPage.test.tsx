import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import SourceListPage from '../SourceListPage';

const { mockListSources } = vi.hoisted(() => ({
  mockListSources: vi.fn(),
}));

vi.mock('@/api/scraping', () => ({
  listSources: mockListSources,
  pauseSource: vi.fn(),
  resumeSource: vi.fn(),
  disableSource: vi.fn(),
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

describe('SourceListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockListSources.mockResolvedValue({
      data: [
        {
          id: 'src-1',
          name: 'Test Scraper',
          base_url: 'https://example.com',
          type: 'web',
          status: 'ACTIVE',
          rate_limit: 10,
          schedule: null,
          config: {},
          last_scrape_at: '2026-01-01T00:00:00Z',
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
    mockListSources.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<SourceListPage />);
    expect(screen.getByText('Loading sources...')).toBeDefined();
  });

  it('renders the page heading after data loads', async () => {
    renderWithProviders(<SourceListPage />);
    await waitFor(() => {
      expect(screen.getByText('Scraping Sources')).toBeDefined();
    });
  });

  it('renders Add Source link', async () => {
    renderWithProviders(<SourceListPage />);
    await waitFor(() => {
      expect(screen.getByText('Add Source')).toBeDefined();
    });
    const link = screen.getByText('Add Source');
    expect(link.closest('a')?.getAttribute('href')).toBe('/scraping/sources/new');
  });

  it('renders source data in the table', async () => {
    renderWithProviders(<SourceListPage />);
    await waitFor(() => {
      expect(screen.getByText('Test Scraper')).toBeDefined();
    });
    expect(screen.getByText('https://example.com')).toBeDefined();
    expect(screen.getByText('web')).toBeDefined();
    expect(screen.getByText('10/min')).toBeDefined();
  });

  it('renders a Pause button for active sources', async () => {
    renderWithProviders(<SourceListPage />);
    await waitFor(() => {
      expect(screen.getByText('Pause')).toBeDefined();
    });
  });

  it('renders status filter dropdown', async () => {
    renderWithProviders(<SourceListPage />);
    await waitFor(() => {
      expect(screen.getByText('Scraping Sources')).toBeDefined();
    });
    expect(screen.getByDisplayValue('All Statuses')).toBeDefined();
  });
});
