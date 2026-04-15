import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listClassifications,
  createClassification,
} from '@/api/governance';
import type {
  DataClassification,
  CreateClassificationData,
} from '@/api/governance';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import ClassificationBadge from '@/components/governance/ClassificationBadge';
import type { ClassificationLevel } from '@/components/governance/ClassificationBadge';

/* ------------------------------------------------------------------ */
/*  Add-classification form                                            */
/* ------------------------------------------------------------------ */

const CLASSIFICATION_OPTIONS: { value: string; label: string }[] = [
  { value: 'PUBLIC_INTERNAL', label: 'Public / Internal' },
  { value: 'CONFIDENTIAL', label: 'Confidential' },
  { value: 'RESTRICTED', label: 'Restricted' },
  { value: 'HIGHLY_RESTRICTED', label: 'Highly Restricted' },
];

function AddClassificationForm({ onSuccess }: { onSuccess: () => void }) {
  const [entityType, setEntityType] = useState('');
  const [entityId, setEntityId] = useState('');
  const [fieldName, setFieldName] = useState('');
  const [classification, setClassification] = useState('PUBLIC_INTERNAL');

  const mutation = useMutation({
    mutationFn: (data: CreateClassificationData) => createClassification(data),
    onSuccess: () => {
      setEntityType('');
      setEntityId('');
      setFieldName('');
      setClassification('PUBLIC_INTERNAL');
      onSuccess();
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!entityType || !entityId) return;
    mutation.mutate({
      entity_type: entityType,
      entity_id: entityId,
      classification,
      justification: fieldName || undefined,
    });
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="card"
      style={{ padding: 20, marginBottom: 20 }}
    >
      <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 16 }}>
        Add Classification
      </h3>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: 12, alignItems: 'end' }}>
        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>Entity Type</label>
          <input
            type="text"
            value={entityType}
            onChange={(e) => setEntityType(e.target.value)}
            placeholder="e.g. content, user"
            required
          />
        </div>

        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>Entity ID</label>
          <input
            type="text"
            value={entityId}
            onChange={(e) => setEntityId(e.target.value)}
            placeholder="UUID"
            required
          />
        </div>

        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>Field Name</label>
          <input
            type="text"
            value={fieldName}
            onChange={(e) => setFieldName(e.target.value)}
            placeholder="Optional justification"
          />
        </div>

        <div className="form-group" style={{ marginBottom: 0 }}>
          <label>Classification</label>
          <select
            value={classification}
            onChange={(e) => setClassification(e.target.value)}
          >
            {CLASSIFICATION_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>

        <button
          type="submit"
          className="btn btn-primary"
          disabled={mutation.isPending || !entityType || !entityId}
          style={{ fontSize: 13, padding: '8px 16px', whiteSpace: 'nowrap' }}
        >
          {mutation.isPending ? 'Adding...' : 'Add'}
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
          Failed to add classification: {(mutation.error as Error).message}
        </div>
      )}
    </form>
  );
}

/* ------------------------------------------------------------------ */
/*  Table columns                                                      */
/* ------------------------------------------------------------------ */

const columns: Column<DataClassification>[] = [
  {
    key: 'entity_type',
    header: 'Entity Type',
    width: '120px',
    render: (c) => (
      <span style={{ fontWeight: 500, textTransform: 'capitalize' }}>
        {c.entity_type}
      </span>
    ),
  },
  {
    key: 'entity_name',
    header: 'Entity',
    render: (c) => c.entity_name || c.entity_id.slice(0, 12) + '...',
  },
  {
    key: 'classification',
    header: 'Classification',
    width: '180px',
    render: (c) => <ClassificationBadge classification={c.classification as ClassificationLevel} />,
  },
  {
    key: 'justification',
    header: 'Justification',
    render: (c) => (
      <span style={{ fontSize: 13, color: c.justification ? undefined : 'var(--color-text-muted)' }}>
        {c.justification || '--'}
      </span>
    ),
  },
  {
    key: 'classified_by',
    header: 'Classified By',
    width: '130px',
    render: (c) => c.classified_by,
  },
  {
    key: 'created_at',
    header: 'Created',
    width: '150px',
    render: (c) => new Date(c.created_at).toLocaleString(),
  },
];

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export default function DataClassificationPage() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [classFilter, setClassFilter] = useState('');
  const [entityTypeFilter, setEntityTypeFilter] = useState('');
  const perPage = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['classifications', page, perPage, classFilter, entityTypeFilter],
    queryFn: () =>
      listClassifications({
        page,
        per_page: perPage,
        classification: classFilter || undefined,
        entity_type: entityTypeFilter || undefined,
      }),
  });

  if (isLoading) return <LoadingSpinner message="Loading classifications..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load classifications: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Data Classifications</h1>
      </div>

      <AddClassificationForm
        onSuccess={() => queryClient.invalidateQueries({ queryKey: ['classifications'] })}
      />

      {/* Filters */}
      <div
        className="card"
        style={{
          display: 'flex',
          gap: 16,
          alignItems: 'center',
          marginBottom: 16,
          padding: '12px 16px',
        }}
      >
        <div className="form-group" style={{ marginBottom: 0, width: 200 }}>
          <label>Classification</label>
          <select
            value={classFilter}
            onChange={(e) => {
              setClassFilter(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All Levels</option>
            {CLASSIFICATION_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Entity Type</label>
          <input
            type="text"
            value={entityTypeFilter}
            onChange={(e) => {
              setEntityTypeFilter(e.target.value);
              setPage(1);
            }}
            placeholder="Filter by type"
          />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(c) => c.id}
      />
    </div>
  );
}
