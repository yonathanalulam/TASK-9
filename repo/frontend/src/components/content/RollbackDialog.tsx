import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getContentVersions, rollbackContent } from '@/api/content';
import type { ContentVersion } from '@/api/types';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import dayjs from 'dayjs';

interface RollbackDialogProps {
  contentId: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const ROLLBACK_WINDOW_DAYS = 30;

export default function RollbackDialog({
  contentId,
  isOpen,
  onClose,
  onSuccess,
}: RollbackDialogProps) {
  const queryClient = useQueryClient();
  const [targetVersionId, setTargetVersionId] = useState('');
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');

  const { data: versions, isLoading: versionsLoading } = useQuery({
    queryKey: ['content', contentId, 'versions'],
    queryFn: () => getContentVersions(contentId),
    enabled: isOpen && !!contentId,
  });

  const mutation = useMutation({
    mutationFn: () => rollbackContent(contentId, targetVersionId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['content', contentId] });
      queryClient.invalidateQueries({ queryKey: ['content', contentId, 'versions'] });
      setTargetVersionId('');
      setReason('');
      setError('');
      onSuccess();
    },
    onError: (err: Error) => {
      setError(err.message || 'Rollback failed');
    },
  });

  if (!isOpen) return null;

  const now = dayjs();
  const cutoff = now.subtract(ROLLBACK_WINDOW_DAYS, 'day');

  const isVersionExpired = (v: ContentVersion) => dayjs(v.created_at).isBefore(cutoff);

  // Exclude the latest version (nothing to roll back to)
  const versionList = versions?.data ?? [];
  const rollbackTargets = versionList.filter((_, idx) => idx > 0);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!targetVersionId) {
      setError('Please select a target version');
      return;
    }
    if (reason.trim().length < 10) {
      setError('Reason must be at least 10 characters');
      return;
    }

    mutation.mutate();
  };

  const handleClose = () => {
    setTargetVersionId('');
    setReason('');
    setError('');
    onClose();
  };

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 1000,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'rgba(0, 0, 0, 0.45)',
      }}
    >
      <div
        className="card"
        style={{
          width: 500,
          maxWidth: '90vw',
          padding: 24,
          display: 'flex',
          flexDirection: 'column',
          gap: 16,
        }}
      >
        <h2 style={{ fontSize: 18, fontWeight: 600 }}>Rollback Content</h2>

        <p style={{ color: 'var(--color-text-muted)', lineHeight: 1.6, fontSize: 13 }}>
          Select a previous version to rollback to. This will create a new version
          based on the selected version's content.
        </p>

        {versionsLoading ? (
          <LoadingSpinner size={20} message="Loading versions..." />
        ) : (
          <form onSubmit={handleSubmit}>
            {error && (
              <div
                style={{
                  background: '#fee2e2',
                  color: '#991b1b',
                  padding: '10px 14px',
                  borderRadius: 'var(--radius)',
                  marginBottom: 12,
                  fontSize: 13,
                }}
              >
                {error}
              </div>
            )}

            <div className="form-group">
              <label>Target Version</label>
              <select
                value={targetVersionId}
                onChange={(e) => setTargetVersionId(e.target.value)}
              >
                <option value="">-- Select a version --</option>
                {rollbackTargets.map((v) => {
                  const expired = isVersionExpired(v);
                  return (
                    <option key={v.id} value={v.id} disabled={expired}>
                      v{v.version_number} - {dayjs(v.created_at).format('MMM D, YYYY h:mm A')}
                      {' '}by {v.created_by}
                      {v.is_rollback ? ' [Rollback]' : ''}
                      {expired ? ' [Expired]' : ''}
                    </option>
                  );
                })}
              </select>
            </div>

            <div className="form-group">
              <label>
                Reason{' '}
                <span style={{ fontWeight: 400, color: 'var(--color-text-muted)' }}>
                  ({reason.length}/10 min)
                </span>
              </label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Explain why you are rolling back (minimum 10 characters)"
                rows={3}
                style={{ resize: 'vertical' }}
              />
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <button type="button" className="btn btn-secondary" onClick={handleClose}>
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-danger"
                disabled={mutation.isPending || !targetVersionId || reason.trim().length < 10}
              >
                {mutation.isPending ? 'Rolling back...' : 'Rollback'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
