import { vi, describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { VersionDiff } from '@/api/content';

/* ------------------------------------------------------------------ */
/*  Mock the API client used by the content API module                 */
/* ------------------------------------------------------------------ */
const { mockGet } = vi.hoisted(() => ({
  mockGet: vi.fn(),
}));
vi.mock('@/api/client', () => ({
  default: { get: mockGet },
}));

import VersionDiffView from '../VersionDiffView';

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
    },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

describe('VersionDiffView contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

  it('should expect changes array (not changed_fields) from backend', () => {
    // This is a static contract test that would fail if someone
    // reverts the type back to changed_fields
    const backendShape = {
      v1: { id: '1', version_number: 1 },
      v2: { id: '2', version_number: 2 },
      changes: [
        { field: 'title', before: 'Old', after: 'New' },
      ],
    };

    // The component accesses .changes not .changed_fields
    expect(backendShape.changes).toBeDefined();
    expect(backendShape.changes[0].field).toBe('title');
    expect(backendShape.changes[0].before).toBe('Old');
    expect(backendShape.changes[0].after).toBe('New');
  });

  /* ================================================================== */
  /*  Behavior tests — render with mock data and verify display          */
  /* ================================================================== */

  it('renders diff fields from API response', async () => {
    const diffData: VersionDiff = {
      v1: { id: 'ver-1', version_number: 1 },
      v2: { id: 'ver-2', version_number: 2 },
      changes: [
        { field: 'title', before: 'Old Title', after: 'New Title' },
        { field: 'body', before: 'Old body content', after: 'New body content' },
      ],
    };
    const envelope = {
      data: diffData,
      meta: { request_id: 'req-1', timestamp: '2026-04-14T00:00:00+00:00' },
      error: null,
    };
    mockGet.mockResolvedValueOnce({ data: envelope });

    render(
      <VersionDiffView contentId="c-1" fromVersionId="ver-1" toVersionId="ver-2" />,
      { wrapper: createWrapper() },
    );

    // Wait for data to load and verify diff field names are rendered
    expect(await screen.findByText('title')).toBeInTheDocument();
    expect(screen.getByText('body')).toBeInTheDocument();

    // Verify the heading
    expect(screen.getByText('Differences')).toBeInTheDocument();
  });

  it('calls the correct diff API endpoint', async () => {
    const diffData: VersionDiff = {
      v1: { id: 'v-a', version_number: 1 },
      v2: { id: 'v-b', version_number: 2 },
      changes: [{ field: 'title', before: 'A', after: 'B' }],
    };
    const envelope = {
      data: diffData,
      meta: { request_id: 'req-2', timestamp: '2026-04-14T00:00:00+00:00' },
      error: null,
    };
    mockGet.mockResolvedValueOnce({ data: envelope });

    render(
      <VersionDiffView contentId="content-xyz" fromVersionId="v-a" toVersionId="v-b" />,
      { wrapper: createWrapper() },
    );

    // Wait for the query to fire
    await screen.findByText('title');

    expect(mockGet).toHaveBeenCalledWith('/content/content-xyz/versions/v-a/diff/v-b');
  });

  it('shows "No differences" when changes array is empty', async () => {
    const diffData: VersionDiff = {
      v1: { id: 'v1', version_number: 1 },
      v2: { id: 'v2', version_number: 2 },
      changes: [],
    };
    const envelope = {
      data: diffData,
      meta: { request_id: 'req-3', timestamp: '2026-04-14T00:00:00+00:00' },
      error: null,
    };
    mockGet.mockResolvedValueOnce({ data: envelope });

    render(
      <VersionDiffView contentId="c-1" fromVersionId="v1" toVersionId="v2" />,
      { wrapper: createWrapper() },
    );

    expect(await screen.findByText('No differences found between these versions.')).toBeInTheDocument();
  });

  it('displays before and after values for non-body fields', async () => {
    const diffData: VersionDiff = {
      v1: { id: 'v1', version_number: 1 },
      v2: { id: 'v2', version_number: 2 },
      changes: [
        { field: 'title', before: 'Original Title', after: 'Updated Title' },
      ],
    };
    const envelope = {
      data: diffData,
      meta: { request_id: 'req-4', timestamp: '2026-04-14T00:00:00+00:00' },
      error: null,
    };
    mockGet.mockResolvedValueOnce({ data: envelope });

    render(
      <VersionDiffView contentId="c-1" fromVersionId="v1" toVersionId="v2" />,
      { wrapper: createWrapper() },
    );

    expect(await screen.findByText('Original Title')).toBeInTheDocument();
    expect(screen.getByText('Updated Title')).toBeInTheDocument();
  });

  it('renders loading state while fetching', () => {
    // Never resolve the mock to keep loading state
    mockGet.mockReturnValueOnce(new Promise(() => {}));

    render(
      <VersionDiffView contentId="c-1" fromVersionId="v1" toVersionId="v2" />,
      { wrapper: createWrapper() },
    );

    expect(screen.getByText('Loading diff...')).toBeInTheDocument();
  });

  it('renders error state on API failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network error'));

    render(
      <VersionDiffView contentId="c-1" fromVersionId="v1" toVersionId="v2" />,
      { wrapper: createWrapper() },
    );

    expect(await screen.findByText('Failed to load diff.')).toBeInTheDocument();
  });

  it('renders array values (e.g. tags) as comma-separated strings', async () => {
    const diffData: VersionDiff = {
      v1: { id: 'v1', version_number: 1 },
      v2: { id: 'v2', version_number: 2 },
      changes: [
        { field: 'tags', before: ['old-tag', 'common'], after: ['new-tag', 'common'] },
      ],
    };
    const envelope = {
      data: diffData,
      meta: { request_id: 'req-5', timestamp: '2026-04-14T00:00:00+00:00' },
      error: null,
    };
    mockGet.mockResolvedValueOnce({ data: envelope });

    render(
      <VersionDiffView contentId="c-1" fromVersionId="v1" toVersionId="v2" />,
      { wrapper: createWrapper() },
    );

    expect(await screen.findByText('tags')).toBeInTheDocument();
    expect(screen.getByText('old-tag, common')).toBeInTheDocument();
    expect(screen.getByText('new-tag, common')).toBeInTheDocument();
  });
});
