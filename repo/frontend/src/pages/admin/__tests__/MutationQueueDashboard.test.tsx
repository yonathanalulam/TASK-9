import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import MutationQueueDashboard from '../MutationQueueDashboard';

const { mockListMutationLogs } = vi.hoisted(() => ({
  mockListMutationLogs: vi.fn(),
}));

vi.mock('@/api/mutations', () => ({
  listMutationLogs: mockListMutationLogs,
}));

function envelope(rows: Array<Partial<{
  id: string;
  mutation_id: string;
  entity_type: string;
  operation: string;
  status: string;
  received_at: string;
  processed_at: string | null;
}>>) {
  return {
    data: rows.map((r, idx) => ({
      id: r.id ?? `id-${idx}`,
      mutation_id: r.mutation_id ?? `mut-${idx}-1234567890ab`,
      entity_type: r.entity_type ?? 'store',
      entity_id: 's-1',
      operation: r.operation ?? 'update',
      status: r.status ?? 'APPLIED',
      received_at: r.received_at ?? '2026-01-01T00:00:00Z',
      processed_at: r.processed_at ?? null,
      error_detail: null,
    })),
    meta: {
      request_id: 'r',
      timestamp: '',
      pagination: { page: 1, per_page: 20, total: rows.length, total_pages: 1 },
    },
    error: null,
  };
}

function renderWithProviders() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <MutationQueueDashboard />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe('MutationQueueDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the loading spinner initially', () => {
    mockListMutationLogs.mockReturnValue(new Promise(() => {}));
    renderWithProviders();
    expect(screen.getByText('Loading mutation logs...')).toBeDefined();
  });

  it('renders mutation rows with truncated mutation_id, entity type, operation and status', async () => {
    mockListMutationLogs.mockResolvedValue(
      envelope([
        {
          id: 'm-1',
          mutation_id: 'abcdef0123456789',
          entity_type: 'delivery_zone',
          operation: 'create',
          status: 'APPLIED',
          received_at: '2026-01-01T00:00:00Z',
          processed_at: '2026-01-01T00:00:01Z',
        },
        {
          id: 'm-2',
          mutation_id: 'fedcba9876543210',
          entity_type: 'store',
          operation: 'update',
          status: 'CONFLICT',
          received_at: '2026-01-02T00:00:00Z',
        },
      ]),
    );

    renderWithProviders();

    await waitFor(() => {
      // first 12 chars + "..."
      expect(screen.getByText('abcdef012345...')).toBeDefined();
    });
    expect(screen.getByText('fedcba987654...')).toBeDefined();
    expect(screen.getByText('delivery_zone')).toBeDefined();
    expect(screen.getByText('store')).toBeDefined();
    expect(screen.getByText('create')).toBeDefined();
    expect(screen.getByText('update')).toBeDefined();
    expect(screen.getByText('APPLIED')).toBeDefined();
    expect(screen.getByText('CONFLICT')).toBeDefined();
  });

  it('renders "-" when processed_at is null', async () => {
    mockListMutationLogs.mockResolvedValue(
      envelope([{ id: 'm-3', processed_at: null }]),
    );
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText('-')).toBeDefined();
    });
  });

  it('changing the status filter triggers a new query and resets to page 1', async () => {
    mockListMutationLogs.mockResolvedValue(envelope([]));
    renderWithProviders();

    await waitFor(() => {
      expect(mockListMutationLogs).toHaveBeenCalled();
    });
    mockListMutationLogs.mockClear();

    const select = screen.getByRole('combobox');
    fireEvent.change(select, { target: { value: 'REJECTED' } });

    await waitFor(() => {
      expect(mockListMutationLogs).toHaveBeenCalledWith(
        expect.objectContaining({ page: 1, per_page: 20 }),
      );
    });
  });

  it('renders an error message when the query fails', async () => {
    mockListMutationLogs.mockRejectedValue(new Error('500 server error'));
    renderWithProviders();

    await waitFor(() => {
      expect(screen.getByText(/Failed to load mutation logs: 500 server error/)).toBeDefined();
    });
  });

  it('renders the empty-state message when there are no mutations', async () => {
    mockListMutationLogs.mockResolvedValue(envelope([]));
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText(/no data found/i)).toBeDefined();
    });
  });
});
