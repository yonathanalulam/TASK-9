import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import ContentCreatePage from '../ContentCreatePage';

/* ------------------------------------------------------------------ */
/*  Mock API modules at the boundary                                   */
/* ------------------------------------------------------------------ */
const mockCreateContent = vi.fn();
vi.mock('@/api/content', () => ({
  createContent: (...args: unknown[]) => mockCreateContent(...args),
}));

const mockListStores = vi.fn();
vi.mock('@/api/stores', () => ({
  listStores: (...args: unknown[]) => mockListStores(...args),
}));

const mockListRegions = vi.fn();
vi.mock('@/api/regions', () => ({
  listRegions: (...args: unknown[]) => mockListRegions(...args),
}));

/* ------------------------------------------------------------------ */
/*  Mock react-router navigate                                         */
/* ------------------------------------------------------------------ */
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

/* ------------------------------------------------------------------ */
/*  Test helpers                                                       */
/* ------------------------------------------------------------------ */

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <ContentCreatePage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function setupDefaultMocks() {
  mockListStores.mockResolvedValue({
    data: [
      { id: 'store-1', name: 'Test Store' },
    ],
    meta: { request_id: 'req-s', timestamp: '2026-04-15T00:00:00Z' },
    error: null,
  });
  mockListRegions.mockResolvedValue({
    data: [
      { id: 'region-1', name: 'Test Region' },
    ],
    meta: { request_id: 'req-r', timestamp: '2026-04-15T00:00:00Z' },
    error: null,
  });
}

describe('ContentCreatePage — behavior tests', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupDefaultMocks();
  });

  /* ---------------------------------------------------------------- */
  /*  Form rendering                                                   */
  /* ---------------------------------------------------------------- */

  it('renders all required form fields', async () => {
    renderPage();

    // Required fields
    expect(screen.getByPlaceholderText('Enter content title')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Write your content here...')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Author name')).toBeInTheDocument();

    // Content type dropdown
    expect(screen.getByText('Content Type')).toBeInTheDocument();
    expect(screen.getByText('Job Post')).toBeInTheDocument();

    // Tags input
    expect(screen.getByPlaceholderText('Type a tag and press Enter or comma')).toBeInTheDocument();

    // Submit and cancel buttons
    expect(screen.getByText('Create Content')).toBeInTheDocument();
    expect(screen.getByText('Cancel')).toBeInTheDocument();
  });

  it('renders optional store and region dropdowns', async () => {
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('Store (optional)')).toBeInTheDocument();
      expect(screen.getByText('Region (optional)')).toBeInTheDocument();
    });
  });

  it('populates store dropdown from API', async () => {
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('Test Store')).toBeInTheDocument();
    });
  });

  it('populates region dropdown from API', async () => {
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('Test Region')).toBeInTheDocument();
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Validation errors                                                */
  /* ---------------------------------------------------------------- */

  it('shows validation error when title is empty on submit', async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByText('Create Content'));

    expect(screen.getByText('Title is required')).toBeInTheDocument();
    expect(mockCreateContent).not.toHaveBeenCalled();
  });

  it('shows validation error when body is empty on submit', async () => {
    const user = userEvent.setup();
    renderPage();

    // Fill title but leave body empty
    await user.type(screen.getByPlaceholderText('Enter content title'), 'My Title');
    await user.click(screen.getByText('Create Content'));

    expect(screen.getByText('Body is required')).toBeInTheDocument();
    expect(mockCreateContent).not.toHaveBeenCalled();
  });

  it('shows validation error when author name is empty on submit', async () => {
    const user = userEvent.setup();
    renderPage();

    await user.type(screen.getByPlaceholderText('Enter content title'), 'My Title');
    await user.type(screen.getByPlaceholderText('Write your content here...'), 'Some body text');
    await user.click(screen.getByText('Create Content'));

    expect(screen.getByText('Author name is required')).toBeInTheDocument();
    expect(mockCreateContent).not.toHaveBeenCalled();
  });

  /* ---------------------------------------------------------------- */
  /*  Successful submission                                            */
  /* ---------------------------------------------------------------- */

  it('calls createContent API with correct data on valid submit', async () => {
    const user = userEvent.setup();
    mockCreateContent.mockResolvedValue({
      data: { id: 'new-content-id', title: 'My Title' },
      meta: { request_id: 'req-1', timestamp: '2026-04-15T00:00:00Z' },
      error: null,
    });

    renderPage();

    await user.type(screen.getByPlaceholderText('Enter content title'), 'My Title');
    await user.type(screen.getByPlaceholderText('Write your content here...'), 'Some body text');
    await user.type(screen.getByPlaceholderText('Author name'), 'John Doe');

    await user.click(screen.getByText('Create Content'));

    await waitFor(() => {
      expect(mockCreateContent).toHaveBeenCalledWith({
        title: 'My Title',
        body: 'Some body text',
        content_type: 'JOB_POST',
        author_name: 'John Doe',
        tags: undefined,
        store_id: null,
        region_id: null,
      });
    });
  });

  it('navigates to the created content page on success', async () => {
    const user = userEvent.setup();
    mockCreateContent.mockResolvedValue({
      data: { id: 'new-content-id' },
      meta: { request_id: 'req-1', timestamp: '2026-04-15T00:00:00Z' },
      error: null,
    });

    renderPage();

    await user.type(screen.getByPlaceholderText('Enter content title'), 'Test');
    await user.type(screen.getByPlaceholderText('Write your content here...'), 'Body');
    await user.type(screen.getByPlaceholderText('Author name'), 'Author');

    await user.click(screen.getByText('Create Content'));

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/content/new-content-id');
    });
  });

  /* ---------------------------------------------------------------- */
  /*  API error handling                                               */
  /* ---------------------------------------------------------------- */

  it('displays API error when createContent fails', async () => {
    const user = userEvent.setup();
    mockCreateContent.mockRejectedValue(new Error('Validation failed on server'));

    renderPage();

    await user.type(screen.getByPlaceholderText('Enter content title'), 'Test');
    await user.type(screen.getByPlaceholderText('Write your content here...'), 'Body');
    await user.type(screen.getByPlaceholderText('Author name'), 'Author');

    await user.click(screen.getByText('Create Content'));

    await waitFor(() => {
      expect(screen.getByText('Validation failed on server')).toBeInTheDocument();
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Content type selection                                           */
  /* ---------------------------------------------------------------- */

  it('allows selecting a different content type', async () => {
    const user = userEvent.setup();
    mockCreateContent.mockResolvedValue({
      data: { id: 'new-id' },
      meta: { request_id: 'req-1', timestamp: '2026-04-15T00:00:00Z' },
      error: null,
    });

    renderPage();

    // Change content type — the label isn't associated via htmlFor,
    // so we find the select by its currently selected option text.
    const contentTypeSelect = screen.getByDisplayValue('Job Post');
    await user.selectOptions(contentTypeSelect, 'OPERATIONAL_NOTICE');

    await user.type(screen.getByPlaceholderText('Enter content title'), 'Notice');
    await user.type(screen.getByPlaceholderText('Write your content here...'), 'Body');
    await user.type(screen.getByPlaceholderText('Author name'), 'Author');

    await user.click(screen.getByText('Create Content'));

    await waitFor(() => {
      expect(mockCreateContent).toHaveBeenCalledWith(
        expect.objectContaining({
          content_type: 'OPERATIONAL_NOTICE',
        }),
      );
    });
  });

  /* ---------------------------------------------------------------- */
  /*  Submit button disabled state                                     */
  /* ---------------------------------------------------------------- */

  it('shows "Creating..." text and disables button while mutation is pending', async () => {
    const user = userEvent.setup();
    // Create a promise that doesn't resolve immediately
    let resolveCreate!: (value: unknown) => void;
    mockCreateContent.mockReturnValue(
      new Promise((resolve) => {
        resolveCreate = resolve;
      }),
    );

    renderPage();

    await user.type(screen.getByPlaceholderText('Enter content title'), 'Test');
    await user.type(screen.getByPlaceholderText('Write your content here...'), 'Body');
    await user.type(screen.getByPlaceholderText('Author name'), 'Author');

    await user.click(screen.getByText('Create Content'));

    await waitFor(() => {
      expect(screen.getByText('Creating...')).toBeInTheDocument();
      expect(screen.getByText('Creating...')).toBeDisabled();
    });

    // Resolve to clean up
    resolveCreate({
      data: { id: 'id' },
      meta: { request_id: 'req', timestamp: '2026-01-01' },
      error: null,
    });
  });
});
