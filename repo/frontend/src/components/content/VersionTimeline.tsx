import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getContentVersions } from '@/api/content';
import type { ContentVersion } from '@/api/types';
import VersionDiffView from './VersionDiffView';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import dayjs from 'dayjs';

interface VersionTimelineProps {
  contentId: string;
}

function getVersionType(v: ContentVersion): string {
  if (v.is_rollback) return 'rollback';
  if (v.version_number === 1) return 'initial';
  if (v.status_at_creation === 'PUBLISHED') return 'publish';
  return 'edit';
}

const versionTypeBadge: Record<string, string> = {
  initial: 'badge badge-info',
  edit: 'badge',
  publish: 'badge badge-success',
  rollback: 'badge badge-warning',
};

const versionTypeLabel: Record<string, string> = {
  initial: 'Initial',
  edit: 'Edit',
  publish: 'Publish',
  rollback: 'Rollback',
};

export default function VersionTimeline({ contentId }: VersionTimelineProps) {
  const [selectedVersion, setSelectedVersion] = useState<ContentVersion | null>(null);
  const [compareMode, setCompareMode] = useState(false);
  const [compareFrom, setCompareFrom] = useState<string | null>(null);
  const [compareTo, setCompareTo] = useState<string | null>(null);
  const [showDiff, setShowDiff] = useState(false);

  const { data: versions, isLoading } = useQuery({
    queryKey: ['content', contentId, 'versions'],
    queryFn: () => getContentVersions(contentId),
    enabled: !!contentId,
  });

  const versionList = versions?.data ?? [];

  const handleVersionClick = (v: ContentVersion) => {
    if (compareMode) {
      if (!compareFrom) {
        setCompareFrom(v.id);
      } else if (!compareTo) {
        setCompareTo(v.id);
        setShowDiff(true);
      }
    } else {
      setSelectedVersion(selectedVersion?.id === v.id ? null : v);
    }
  };

  const startCompare = () => {
    setCompareMode(true);
    setCompareFrom(null);
    setCompareTo(null);
    setShowDiff(false);
    setSelectedVersion(null);
  };

  const cancelCompare = () => {
    setCompareMode(false);
    setCompareFrom(null);
    setCompareTo(null);
    setShowDiff(false);
  };

  return (
    <div className="card">
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 16,
        }}
      >
        <h2 style={{ fontSize: 16 }}>Version History</h2>
        {!compareMode ? (
          <button
            className="btn btn-secondary"
            style={{ fontSize: 12, padding: '4px 10px' }}
            onClick={startCompare}
            disabled={!versionList || versionList.length < 2}
          >
            Compare
          </button>
        ) : (
          <button
            className="btn btn-secondary"
            style={{ fontSize: 12, padding: '4px 10px' }}
            onClick={cancelCompare}
          >
            Cancel
          </button>
        )}
      </div>

      {compareMode && !showDiff && (
        <div
          style={{
            fontSize: 12,
            color: 'var(--color-text-muted)',
            marginBottom: 12,
            padding: '8px 10px',
            background: '#dbeafe',
            borderRadius: 'var(--radius)',
          }}
        >
          {!compareFrom
            ? 'Select the first version to compare'
            : 'Now select the second version'}
        </div>
      )}

      {isLoading ? (
        <LoadingSpinner size={20} />
      ) : !versionList || versionList.length === 0 ? (
        <p style={{ color: 'var(--color-text-muted)', fontSize: 13 }}>
          No version history available.
        </p>
      ) : (
        <div style={{ position: 'relative' }}>
          {/* Timeline line */}
          <div
            style={{
              position: 'absolute',
              left: 11,
              top: 8,
              bottom: 8,
              width: 2,
              background: 'var(--color-border)',
            }}
          />

          {versionList.map((v) => {
            const vType = getVersionType(v);
            const isSelected = compareFrom === v.id || compareTo === v.id;

            return (
              <div
                key={v.id}
                style={{
                  position: 'relative',
                  paddingLeft: 32,
                  paddingBottom: 16,
                  cursor: 'pointer',
                }}
                onClick={() => handleVersionClick(v)}
              >
                {/* Timeline dot */}
                <div
                  style={{
                    position: 'absolute',
                    left: 5,
                    top: 4,
                    width: 14,
                    height: 14,
                    borderRadius: '50%',
                    background:
                      vType === 'rollback'
                        ? 'var(--color-warning)'
                        : isSelected
                          ? 'var(--color-primary)'
                          : 'var(--color-surface)',
                    border: `2px solid ${
                      isSelected
                        ? 'var(--color-primary)'
                        : vType === 'rollback'
                          ? 'var(--color-warning)'
                          : 'var(--color-border)'
                    }`,
                    zIndex: 1,
                  }}
                />

                <div
                  style={{
                    padding: '8px 12px',
                    borderRadius: 'var(--radius)',
                    background:
                      isSelected
                        ? 'rgba(37, 99, 235, 0.08)'
                        : selectedVersion?.id === v.id
                          ? 'var(--color-bg)'
                          : 'transparent',
                    border:
                      isSelected
                        ? '1px solid var(--color-primary)'
                        : '1px solid transparent',
                    transition: 'background 0.15s',
                  }}
                >
                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: 8,
                      marginBottom: 4,
                    }}
                  >
                    <span style={{ fontWeight: 600, fontSize: 13 }}>
                      v{v.version_number}
                    </span>
                    <span className={versionTypeBadge[vType]}>
                      {versionTypeLabel[vType]}
                    </span>
                  </div>

                  <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
                    {dayjs(v.created_at).format('MMM D, YYYY h:mm A')}
                  </div>

                  <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
                    by {v.created_by}
                  </div>

                  {v.change_reason && (
                    <div
                      style={{
                        fontSize: 12,
                        color: 'var(--color-text)',
                        marginTop: 4,
                        fontStyle: 'italic',
                      }}
                    >
                      {v.change_reason}
                    </div>
                  )}

                  {/* Expanded version detail */}
                  {selectedVersion?.id === v.id && !compareMode && (
                    <div
                      style={{
                        marginTop: 8,
                        paddingTop: 8,
                        borderTop: '1px solid var(--color-border)',
                        fontSize: 12,
                      }}
                    >
                      <div style={{ marginBottom: 4 }}>
                        <strong>Title:</strong> {v.title}
                      </div>
                      <div style={{ marginBottom: 4 }}>
                        <strong>Type:</strong> {v.content_type}
                      </div>
                      <div style={{ marginBottom: 4 }}>
                        <strong>Status:</strong> {v.status_at_creation}
                      </div>
                      {v.tags.length > 0 && (
                        <div>
                          <strong>Tags:</strong> {v.tags.join(', ')}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Diff view */}
      {showDiff && compareFrom && compareTo && (
        <div style={{ marginTop: 16, borderTop: '1px solid var(--color-border)', paddingTop: 16 }}>
          <VersionDiffView
            contentId={contentId}
            fromVersionId={compareFrom}
            toVersionId={compareTo}
          />
        </div>
      )}
    </div>
  );
}
