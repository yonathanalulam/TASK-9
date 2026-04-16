import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import ExportRequestPage from '../ExportRequestPage';

// Mock the exports API module
vi.mock('@/api/exports', () => ({
  requestExport: vi.fn(),
  EXPORT_DATASETS: ['content_items', 'audit_events'] as const,
  EXPORT_FORMATS: ['CSV'] as const,
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

describe('ExportRequestPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the page heading', () => {
    renderWithProviders(<ExportRequestPage />);
    expect(screen.getByText('New Export Request')).toBeDefined();
  });

  it('renders a dataset selector with all backend-canonical values', () => {
    renderWithProviders(<ExportRequestPage />);

    // The dataset <select> should render as a combobox
    const select = screen.getByRole('combobox');
    expect(select).toBeDefined();

    const options = screen.getAllByRole('option');
    const values = options.map((o) => (o as HTMLOptionElement).value);
    expect(values).toContain('content_items');
    expect(values).toContain('audit_events');
  });

  it('renders format radio buttons', () => {
    renderWithProviders(<ExportRequestPage />);

    const csvRadio = screen.getByRole('radio', { name: 'CSV' });
    expect(csvRadio).toBeDefined();
    expect((csvRadio as HTMLInputElement).checked).toBe(true);
  });

  it('renders the filters JSON textarea', () => {
    renderWithProviders(<ExportRequestPage />);

    const textarea = screen.getByRole('textbox');
    expect(textarea).toBeDefined();
    expect((textarea as HTMLTextAreaElement).value).toBe('{}');
  });

  it('renders the submit button', () => {
    renderWithProviders(<ExportRequestPage />);

    const submitBtn = screen.getByRole('button', { name: 'Request Export' });
    expect(submitBtn).toBeDefined();
    expect((submitBtn as HTMLButtonElement).disabled).toBe(false);
  });

  it('renders a cancel button', () => {
    renderWithProviders(<ExportRequestPage />);

    const cancelBtn = screen.getByRole('button', { name: 'Cancel' });
    expect(cancelBtn).toBeDefined();
  });
});
