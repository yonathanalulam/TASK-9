import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import ComplianceReportsPage from '../ComplianceReportsPage';

// Mock the API module
vi.mock('@/api/complianceReports', () => ({
  REPORT_TYPES: [
    'RETENTION_SUMMARY',
    'CONSENT_AUDIT',
    'DATA_CLASSIFICATION',
    'EXPORT_LOG',
    'ACCESS_AUDIT',
  ],
  generateReport: vi.fn(),
  listReports: vi.fn().mockResolvedValue({
    data: [
      {
        id: 'report-1',
        report_type: 'RETENTION_SUMMARY',
        generated_by: 'user-uuid',
        parameters: {},
        download_url: '/api/v1/compliance-reports/report-1/download',
        tamper_hash_sha256: 'abc123def456',
        previous_report_id: null,
        previous_report_hash: null,
        generated_at: '2026-04-14T10:00:00+00:00',
      },
    ],
    meta: {
      request_id: 'req-1',
      timestamp: '2026-04-14T10:00:00Z',
      pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 },
    },
    error: null,
  }),
  downloadReport: vi.fn(),
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

describe('ComplianceReportsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the report type selector with all 5 backend-canonical types', async () => {
    renderWithProviders(<ComplianceReportsPage />);

    await waitFor(() => {
      const select = screen.getByRole('combobox');
      expect(select).toBeDefined();
    });

    const options = screen.getAllByRole('option');
    const optionValues = options.map((o) => (o as HTMLOptionElement).value);
    expect(optionValues).toContain('RETENTION_SUMMARY');
    expect(optionValues).toContain('CONSENT_AUDIT');
    expect(optionValues).toContain('DATA_CLASSIFICATION');
    expect(optionValues).toContain('EXPORT_LOG');
    expect(optionValues).toContain('ACCESS_AUDIT');
    // Old invalid types must NOT appear
    expect(optionValues).not.toContain('AUDIT_LOG');
    expect(optionValues).not.toContain('DATA_ACCESS');
    expect(optionValues).not.toContain('CONSENT_SUMMARY');
  });

  it('does NOT have a title input field', async () => {
    renderWithProviders(<ComplianceReportsPage />);

    await waitFor(() => {
      expect(screen.getByText('Generate Compliance Report')).toBeDefined();
    });

    // The old page had a "Title" input. It must be gone.
    expect(screen.queryByPlaceholderText('Report title')).toBeNull();
    expect(screen.queryByLabelText('Title')).toBeNull();
  });

  it('renders reports using generated_at (not created_at)', async () => {
    renderWithProviders(<ComplianceReportsPage />);

    await waitFor(() => {
      // The date should be rendered from generated_at field
      expect(screen.getByText(/4\/14\/2026|14\/04\/2026|2026/)).toBeDefined();
    });
  });

  it('renders tamper hash verification badge', async () => {
    renderWithProviders(<ComplianceReportsPage />);

    await waitFor(() => {
      // Hash badge shows first 12 chars of tamper_hash_sha256
      expect(screen.getByText(/abc123def456/)).toBeDefined();
    });
  });

  it('renders a Download button for each report', async () => {
    renderWithProviders(<ComplianceReportsPage />);

    await waitFor(() => {
      const downloadButton = screen.getByRole('button', { name: 'Download' });
      expect(downloadButton).toBeDefined();
    });
  });
});
