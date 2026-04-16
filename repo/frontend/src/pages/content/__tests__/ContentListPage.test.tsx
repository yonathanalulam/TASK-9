import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import ContentListPage from '../ContentListPage';

// Mock the content API
vi.mock('@/api/content', () => ({
  listContent: vi.fn(),
}));

// Mock LoadingSpinner for easy detection
vi.mock('@/components/common/LoadingSpinner', () => ({
  default: ({ message }: { message?: string }) => (
    <div data-testid="loading-spinner">{message}</div>
  ),
}));

import { listContent } from '@/api/content';

const mockContentItems = [
  {
    id: 'content-1',
    content_type: 'JOB_POST',
    title: 'Senior Developer Role',
    body: 'Looking for a senior dev.',
    author_name: 'HR Team',
    status: 'PUBLISHED',
    published_at: '2026-04-01T00:00:00Z',
    store_id: null,
    region_id: null,
    view_count: 150,
    reply_count: 5,
    tags: ['engineering'],
    version: 1,
    created_at: '2026-03-28T00:00:00Z',
    updated_at: '2026-04-01T00:00:00Z',
  },
  {
    id: 'content-2',
    content_type: 'OPERATIONAL_NOTICE',
    title: 'System Maintenance Notice',
    body: 'Planned downtime this weekend.',
    author_name: 'Ops Team',
    status: 'DRAFT',
    published_at: null,
    store_id: null,
    region_id: null,
    view_count: 20,
    reply_count: 0,
    tags: [],
    version: 1,
    created_at: '2026-04-10T00:00:00Z',
    updated_at: '2026-04-10T00:00:00Z',
  },
];

function createQueryClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
}

function renderWithProviders(ui: React.ReactElement) {
  const qc = createQueryClient();
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('ContentListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing and shows loading state initially', () => {
    // Make the API hang so we see the loading state
    vi.mocked(listContent).mockReturnValue(new Promise(() => {}));

    renderWithProviders(<ContentListPage />);
    expect(screen.getByTestId('loading-spinner')).toBeDefined();
    expect(screen.getByText('Loading content...')).toBeDefined();
  });

  it('renders content items after loading', async () => {
    vi.mocked(listContent).mockResolvedValue({
      data: mockContentItems,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<ContentListPage />);

    await waitFor(() => {
      expect(screen.getByText('Senior Developer Role')).toBeDefined();
    });

    expect(screen.getByText('System Maintenance Notice')).toBeDefined();
    expect(screen.getByText('Content')).toBeDefined();
  });

  it('renders the Create Content link', async () => {
    vi.mocked(listContent).mockResolvedValue({
      data: mockContentItems,
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 2, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<ContentListPage />);

    await waitFor(() => {
      expect(screen.getByText('Create Content')).toBeDefined();
    });
  });

  it('renders filter dropdowns for content type and status', async () => {
    vi.mocked(listContent).mockResolvedValue({
      data: [],
      meta: {
        request_id: 'req-1',
        timestamp: '2026-04-15T00:00:00Z',
        pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 },
      },
      error: null,
    });

    renderWithProviders(<ContentListPage />);

    await waitFor(() => {
      expect(screen.getByText('Content')).toBeDefined();
    });

    // Check for filter option labels
    expect(screen.getByText('All Types')).toBeDefined();
    expect(screen.getByText('All Statuses')).toBeDefined();
  });

  it('shows error message when API fails', async () => {
    vi.mocked(listContent).mockRejectedValue(new Error('Network error'));

    renderWithProviders(<ContentListPage />);

    await waitFor(() => {
      expect(screen.getByText(/Failed to load content/)).toBeDefined();
    });
  });
});
