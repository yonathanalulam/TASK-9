import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getUserConsent, createConsent } from '@/api/governance';
import type { ConsentRecord } from '@/api/governance';
import LoadingSpinner from '@/components/common/LoadingSpinner';

/* ------------------------------------------------------------------ */
/*  Status badge                                                       */
/* ------------------------------------------------------------------ */

const statusStyles: Record<string, React.CSSProperties> = {
  GRANTED: { background: '#dcfce7', color: '#166534' },
  REVOKED: { background: '#fee2e2', color: '#991b1b' },
  EXPIRED: { background: '#f1f5f9', color: '#64748b' },
};

/* ------------------------------------------------------------------ */
/*  Create consent form                                                */
/* ------------------------------------------------------------------ */

function CreateConsentForm({
  userId,
  onSuccess,
}: {
  userId: string;
  onSuccess: () => void;
}) {
  const [purpose, setPurpose] = useState('');
  const [expiresAt, setExpiresAt] = useState('');

  const mutation = useMutation({
    mutationFn: () =>
      createConsent({
        user_id: userId,
        purpose,
        expires_at: expiresAt || undefined,
      }),
    onSuccess: () => {
      setPurpose('');
      setExpiresAt('');
      onSuccess();
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!purpose) return;
    mutation.mutate();
  };

  return (
    <form
      onSubmit={handleSubmit}
      style={{
        display: 'flex',
        gap: 12,
        alignItems: 'end',
        marginBottom: 16,
      }}
    >
      <div className="form-group" style={{ marginBottom: 0, flex: 1 }}>
        <label>Purpose</label>
        <input
          type="text"
          value={purpose}
          onChange={(e) => setPurpose(e.target.value)}
          placeholder="e.g. marketing_emails, analytics"
          required
        />
      </div>

      <div className="form-group" style={{ marginBottom: 0, width: 200 }}>
        <label>Expires At (optional)</label>
        <input
          type="datetime-local"
          value={expiresAt}
          onChange={(e) => setExpiresAt(e.target.value)}
        />
      </div>

      <button
        type="submit"
        className="btn btn-primary"
        disabled={mutation.isPending || !purpose}
        style={{ fontSize: 13, padding: '8px 16px', whiteSpace: 'nowrap' }}
      >
        {mutation.isPending ? 'Granting...' : 'Grant Consent'}
      </button>

      {mutation.isError && (
        <span style={{ fontSize: 12, color: '#dc2626' }}>
          {(mutation.error as Error).message}
        </span>
      )}
    </form>
  );
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export default function ConsentDashboard() {
  const queryClient = useQueryClient();
  const [userId, setUserId] = useState('');
  const [searchInput, setSearchInput] = useState('');

  const { data, isLoading, isError, error, isFetching } = useQuery({
    queryKey: ['consent', userId],
    queryFn: () => getUserConsent(userId),
    enabled: !!userId,
  });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchInput.trim()) {
      setUserId(searchInput.trim());
    }
  };

  const records = data?.data ?? [];

  return (
    <div>
      <div className="page-header">
        <h1>Consent Dashboard</h1>
      </div>

      {/* User search */}
      <div className="card" style={{ padding: 20, marginBottom: 20 }}>
        <form
          onSubmit={handleSearch}
          style={{ display: 'flex', gap: 12, alignItems: 'end' }}
        >
          <div className="form-group" style={{ marginBottom: 0, flex: 1, maxWidth: 400 }}>
            <label>User ID</label>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Enter user ID to view consent history"
            />
          </div>
          <button
            type="submit"
            className="btn btn-primary"
            disabled={!searchInput.trim()}
            style={{ fontSize: 13, padding: '8px 16px' }}
          >
            Search
          </button>
        </form>
      </div>

      {/* Results */}
      {userId && (
        <>
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              marginBottom: 16,
            }}
          >
            <h2 style={{ fontSize: 16 }}>
              Consent Records for{' '}
              <code style={{ fontSize: 14, background: '#f1f5f9', padding: '2px 6px', borderRadius: 4 }}>
                {userId}
              </code>
            </h2>
          </div>

          {/* Add consent form */}
          <div className="card" style={{ padding: 20, marginBottom: 20 }}>
            <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 12 }}>
              Grant New Consent
            </h3>
            <CreateConsentForm
              userId={userId}
              onSuccess={() => queryClient.invalidateQueries({ queryKey: ['consent', userId] })}
            />
          </div>

          {/* Consent table */}
          {isLoading || isFetching ? (
            <LoadingSpinner message="Loading consent records..." />
          ) : isError ? (
            <div style={{ color: 'var(--color-danger)', padding: 20 }}>
              Failed to load consent records: {(error as Error).message}
            </div>
          ) : (
            <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
              <table>
                <thead>
                  <tr>
                    <th>Purpose</th>
                    <th style={{ width: 110 }}>Status</th>
                    <th style={{ width: 170 }}>Granted At</th>
                    <th style={{ width: 170 }}>Revoked At</th>
                    <th style={{ width: 170 }}>Expires At</th>
                  </tr>
                </thead>
                <tbody>
                  {records.length === 0 ? (
                    <tr>
                      <td
                        colSpan={5}
                        style={{ textAlign: 'center', padding: 32, color: 'var(--color-text-muted)' }}
                      >
                        No consent records found for this user.
                      </td>
                    </tr>
                  ) : (
                    records.map((record: ConsentRecord) => (
                      <tr key={record.id}>
                        <td style={{ fontWeight: 500 }}>{record.purpose}</td>
                        <td>
                          <span
                            className="badge"
                            style={statusStyles[record.status] ?? {}}
                          >
                            {record.status}
                          </span>
                        </td>
                        <td style={{ fontSize: 13 }}>
                          {new Date(record.granted_at).toLocaleString()}
                        </td>
                        <td style={{ fontSize: 13, color: record.revoked_at ? undefined : 'var(--color-text-muted)' }}>
                          {record.revoked_at
                            ? new Date(record.revoked_at).toLocaleString()
                            : '--'}
                        </td>
                        <td style={{ fontSize: 13, color: record.expires_at ? undefined : 'var(--color-text-muted)' }}>
                          {record.expires_at
                            ? new Date(record.expires_at).toLocaleString()
                            : '--'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {/* Empty state when no user selected */}
      {!userId && (
        <div
          className="card"
          style={{
            padding: 40,
            textAlign: 'center',
            color: 'var(--color-text-muted)',
          }}
        >
          Enter a user ID above to view and manage their consent records.
        </div>
      )}
    </div>
  );
}
