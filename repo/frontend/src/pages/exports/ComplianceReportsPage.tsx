import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  generateReport,
  listReports,
  downloadReport,
  REPORT_TYPES,
} from '@/api/complianceReports';
import type { ComplianceReport, ReportType } from '@/api/complianceReports';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Report type display labels                                         */
/* ------------------------------------------------------------------ */

const REPORT_TYPE_LABELS: Record<string, string> = {
  RETENTION_SUMMARY: 'Retention Summary',
  CONSENT_AUDIT: 'Consent Audit',
  DATA_CLASSIFICATION: 'Data Classification',
  EXPORT_LOG: 'Export Log',
  ACCESS_AUDIT: 'Access Audit',
};

/* ------------------------------------------------------------------ */
/*  Hash verification badge                                            */
/* ------------------------------------------------------------------ */

function HashBadge({ hash }: { hash: string | null }) {
  if (!hash) {
    return (
      <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>--</span>
    );
  }

  return (
    <span
      title={hash}
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 4,
        fontSize: 12,
        background: '#dcfce7',
        color: '#166534',
        padding: '2px 8px',
        borderRadius: 4,
        fontFamily: 'monospace',
      }}
    >
      <span style={{ fontSize: 13 }}>&#10003;</span>
      {hash.slice(0, 12)}...
    </span>
  );
}

/* ------------------------------------------------------------------ */
/*  Download helper                                                    */
/* ------------------------------------------------------------------ */

async function handleDownload(report: ComplianceReport) {
  try {
    const blob = await downloadReport(report.id);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `compliance_${report.report_type}_${report.id}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch {
    alert('Download failed. The report file may no longer be available.');
  }
}

/* ------------------------------------------------------------------ */
/*  Generate report form                                               */
/* ------------------------------------------------------------------ */

function GenerateReportForm({ onSuccess }: { onSuccess: () => void }) {
  const [reportType, setReportType] = useState<ReportType>('RETENTION_SUMMARY');
  const [parametersJson, setParametersJson] = useState('{}');
  const [jsonError, setJsonError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => {
      let parameters: Record<string, unknown> = {};
      try {
        parameters = JSON.parse(parametersJson);
      } catch {
        throw new Error('Invalid JSON in parameters field');
      }
      return generateReport({ report_type: reportType, parameters });
    },
    onSuccess: () => {
      setParametersJson('{}');
      onSuccess();
    },
  });

  const handleParametersChange = (value: string) => {
    setParametersJson(value);
    try {
      JSON.parse(value);
      setJsonError(null);
    } catch {
      setJsonError('Invalid JSON');
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (jsonError) return;
    mutation.mutate();
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="card"
      style={{ padding: 20, marginBottom: 20 }}
    >
      <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 16 }}>
        Generate Compliance Report
      </h3>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>Report Type</label>
          <select
            value={reportType}
            onChange={(e) => setReportType(e.target.value as ReportType)}
          >
            {REPORT_TYPES.map((t) => (
              <option key={t} value={t}>
                {REPORT_TYPE_LABELS[t] ?? t}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>
            Parameters (JSON)
            {jsonError && (
              <span style={{ color: '#dc2626', fontWeight: 400, marginLeft: 8 }}>
                {jsonError}
              </span>
            )}
          </label>
          <textarea
            value={parametersJson}
            onChange={(e) => handleParametersChange(e.target.value)}
            rows={2}
            style={{
              fontFamily: 'monospace',
              fontSize: 13,
              borderColor: jsonError ? '#fca5a5' : undefined,
            }}
            placeholder='{"date_from": "2026-01-01"}'
          />
        </div>
      </div>

      <div style={{ marginTop: 12 }}>
        <button
          type="submit"
          className="btn btn-primary"
          disabled={mutation.isPending || !!jsonError}
          style={{ fontSize: 13, padding: '8px 16px' }}
        >
          {mutation.isPending ? 'Generating...' : 'Generate Report'}
        </button>
      </div>

      {mutation.isError && (
        <div
          style={{
            marginTop: 8,
            padding: '8px 12px',
            background: '#fee2e2',
            color: '#991b1b',
            borderRadius: 4,
            fontSize: 13,
          }}
        >
          Failed to generate report: {(mutation.error as Error).message}
        </div>
      )}
    </form>
  );
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export default function ComplianceReportsPage() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const perPage = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['compliance-reports', page, perPage],
    queryFn: () => listReports({ page, per_page: perPage }),
  });

  const columns: Column<ComplianceReport>[] = [
    {
      key: 'report_type',
      header: 'Type',
      width: '180px',
      render: (r) => (
        <span style={{ fontWeight: 500 }}>
          {REPORT_TYPE_LABELS[r.report_type] ?? r.report_type}
        </span>
      ),
    },
    {
      key: 'hash',
      header: 'Integrity',
      width: '160px',
      render: (r) => <HashBadge hash={r.tamper_hash_sha256} />,
    },
    {
      key: 'generated_by',
      header: 'Generated By',
      width: '130px',
      render: (r) => r.generated_by,
    },
    {
      key: 'generated_at',
      header: 'Generated',
      width: '170px',
      render: (r) => (
        <span style={{ fontSize: 13 }}>
          {new Date(r.generated_at).toLocaleString()}
        </span>
      ),
    },
    {
      key: 'download',
      header: 'Download',
      width: '100px',
      render: (r) => (
        <button
          className="btn btn-secondary"
          style={{ fontSize: 12, padding: '3px 10px' }}
          onClick={() => handleDownload(r)}
        >
          Download
        </button>
      ),
    },
  ];

  if (isLoading) return <LoadingSpinner message="Loading compliance reports..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load reports: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Compliance Reports</h1>
      </div>

      <GenerateReportForm
        onSuccess={() => queryClient.invalidateQueries({ queryKey: ['compliance-reports'] })}
      />

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(r) => r.id}
      />
    </div>
  );
}
