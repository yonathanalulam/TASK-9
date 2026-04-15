import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import {
  requestExport,
  EXPORT_DATASETS,
  EXPORT_FORMATS,
  DATASET_LABELS,
} from '@/api/exports';
import type { ExportDataset, ExportFormat } from '@/api/exports';

export default function ExportRequestPage() {
  const navigate = useNavigate();

  const [dataset, setDataset] = useState<ExportDataset>('content_items');
  const [format, setFormat] = useState<ExportFormat>('CSV');
  const [filtersJson, setFiltersJson] = useState('{}');
  const [jsonError, setJsonError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: () => {
      let filters: Record<string, unknown> = {};
      try {
        filters = JSON.parse(filtersJson);
      } catch {
        throw new Error('Invalid JSON in filters field');
      }
      return requestExport({ dataset, format, filters });
    },
    onSuccess: () => {
      navigate('/exports');
    },
  });

  const handleFiltersChange = (value: string) => {
    setFiltersJson(value);
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
    <div>
      <div className="page-header">
        <h1>New Export Request</h1>
      </div>

      <form onSubmit={handleSubmit} style={{ maxWidth: 600 }}>
        <div className="card" style={{ padding: 24 }}>
          {/* Dataset */}
          <div className="form-group">
            <label>Dataset</label>
            <select
              value={dataset}
              onChange={(e) => setDataset(e.target.value as ExportDataset)}
            >
              {EXPORT_DATASETS.map((d) => (
                <option key={d} value={d}>
                  {DATASET_LABELS[d]}
                </option>
              ))}
            </select>
          </div>

          {/* Format */}
          <div className="form-group">
            <label>Format</label>
            <div style={{ display: 'flex', gap: 16, marginTop: 4 }}>
              {EXPORT_FORMATS.map((f) => (
                <label
                  key={f}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                    cursor: 'pointer',
                    fontSize: 14,
                  }}
                >
                  <input
                    type="radio"
                    name="format"
                    value={f}
                    checked={format === f}
                    onChange={() => setFormat(f)}
                  />
                  {f}
                </label>
              ))}
            </div>
          </div>

          {/* Filters */}
          <div className="form-group">
            <label>
              Filters (JSON)
              {jsonError && (
                <span style={{ color: '#dc2626', fontWeight: 400, marginLeft: 8 }}>
                  {jsonError}
                </span>
              )}
            </label>
            <textarea
              value={filtersJson}
              onChange={(e) => handleFiltersChange(e.target.value)}
              rows={5}
              style={{
                fontFamily: 'monospace',
                fontSize: 13,
                borderColor: jsonError ? '#fca5a5' : undefined,
              }}
              placeholder='{"limit": 1000}'
            />
          </div>
        </div>

        {mutation.isError && (
          <div
            style={{
              marginTop: 12,
              padding: '8px 12px',
              background: '#fee2e2',
              color: '#991b1b',
              borderRadius: 4,
              fontSize: 13,
            }}
          >
            Export request failed: {(mutation.error as Error).message}
          </div>
        )}

        <div style={{ marginTop: 16, display: 'flex', gap: 12 }}>
          <button
            type="submit"
            className="btn btn-primary"
            disabled={mutation.isPending || !!jsonError}
            style={{ fontSize: 13, padding: '8px 20px' }}
          >
            {mutation.isPending ? 'Submitting...' : 'Request Export'}
          </button>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => navigate('/exports')}
            style={{ fontSize: 13, padding: '8px 20px' }}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
}
