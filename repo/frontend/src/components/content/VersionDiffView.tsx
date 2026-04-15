import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { diffVersions } from '@/api/content';
import LoadingSpinner from '@/components/common/LoadingSpinner';

interface VersionDiffViewProps {
  contentId: string;
  fromVersionId: string;
  toVersionId: string;
}

type ViewMode = 'unified' | 'side-by-side';

/**
 * Simple word-level diff: compares two strings word-by-word and returns
 * arrays of {text, type} segments for rendering.
 */
interface DiffSegment {
  text: string;
  type: 'unchanged' | 'added' | 'removed';
}

function computeWordDiff(before: string, after: string): DiffSegment[] {
  const beforeWords = before.split(/(\s+)/);
  const afterWords = after.split(/(\s+)/);

  // Simple LCS-based diff for words
  const m = beforeWords.length;
  const n = afterWords.length;

  // Build LCS table
  const dp: number[][] = Array.from({ length: m + 1 }, () =>
    Array(n + 1).fill(0),
  );

  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      if (beforeWords[i - 1] === afterWords[j - 1]) {
        dp[i][j] = dp[i - 1][j - 1] + 1;
      } else {
        dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
      }
    }
  }

  // Backtrack to produce diff
  const segments: DiffSegment[] = [];
  let i = m;
  let j = n;

  const stack: DiffSegment[] = [];

  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && beforeWords[i - 1] === afterWords[j - 1]) {
      stack.push({ text: beforeWords[i - 1], type: 'unchanged' });
      i--;
      j--;
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      stack.push({ text: afterWords[j - 1], type: 'added' });
      j--;
    } else {
      stack.push({ text: beforeWords[i - 1], type: 'removed' });
      i--;
    }
  }

  stack.reverse();

  // Merge adjacent segments of same type
  for (const seg of stack) {
    const last = segments[segments.length - 1];
    if (last && last.type === seg.type) {
      last.text += seg.text;
    } else {
      segments.push({ ...seg });
    }
  }

  return segments;
}

function formatValue(value: string | string[]): string {
  if (Array.isArray(value)) return value.join(', ');
  return value;
}

function DiffFieldRow({
  field,
  before,
  after,
  viewMode,
}: {
  field: string;
  before: string | string[];
  after: string | string[];
  viewMode: ViewMode;
}) {
  const beforeStr = formatValue(before);
  const afterStr = formatValue(after);
  const isBodyField = field === 'body';
  const wordDiff = isBodyField ? computeWordDiff(beforeStr, afterStr) : [];

  if (viewMode === 'side-by-side') {
    return (
      <div style={{ marginBottom: 16 }}>
        <div
          style={{
            fontWeight: 600,
            fontSize: 13,
            marginBottom: 6,
            textTransform: 'capitalize',
          }}
        >
          {field}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
          <div
            style={{
              padding: 10,
              background: '#fef2f2',
              borderRadius: 'var(--radius)',
              fontSize: 13,
              whiteSpace: 'pre-wrap',
              lineHeight: 1.6,
              border: '1px solid #fecaca',
            }}
          >
            <div
              style={{
                fontSize: 11,
                fontWeight: 600,
                color: '#991b1b',
                marginBottom: 6,
              }}
            >
              Before
            </div>
            {isBodyField
              ? wordDiff
                  .filter((s) => s.type !== 'added')
                  .map((seg, idx) => (
                    <span
                      key={idx}
                      style={{
                        background: seg.type === 'removed' ? '#fca5a5' : 'transparent',
                        textDecoration: seg.type === 'removed' ? 'line-through' : 'none',
                      }}
                    >
                      {seg.text}
                    </span>
                  ))
              : beforeStr}
          </div>
          <div
            style={{
              padding: 10,
              background: '#f0fdf4',
              borderRadius: 'var(--radius)',
              fontSize: 13,
              whiteSpace: 'pre-wrap',
              lineHeight: 1.6,
              border: '1px solid #bbf7d0',
            }}
          >
            <div
              style={{
                fontSize: 11,
                fontWeight: 600,
                color: '#166534',
                marginBottom: 6,
              }}
            >
              After
            </div>
            {isBodyField
              ? wordDiff
                  .filter((s) => s.type !== 'removed')
                  .map((seg, idx) => (
                    <span
                      key={idx}
                      style={{
                        background: seg.type === 'added' ? '#86efac' : 'transparent',
                      }}
                    >
                      {seg.text}
                    </span>
                  ))
              : afterStr}
          </div>
        </div>
      </div>
    );
  }

  // Unified view
  return (
    <div style={{ marginBottom: 16 }}>
      <div
        style={{
          fontWeight: 600,
          fontSize: 13,
          marginBottom: 6,
          textTransform: 'capitalize',
        }}
      >
        {field}
      </div>
      <div
        style={{
          padding: 10,
          background: 'var(--color-bg)',
          borderRadius: 'var(--radius)',
          fontSize: 13,
          whiteSpace: 'pre-wrap',
          lineHeight: 1.6,
          border: '1px solid var(--color-border)',
        }}
      >
        {isBodyField ? (
          wordDiff.map((seg, idx) => (
            <span
              key={idx}
              style={{
                background:
                  seg.type === 'added'
                    ? '#86efac'
                    : seg.type === 'removed'
                      ? '#fca5a5'
                      : 'transparent',
                textDecoration: seg.type === 'removed' ? 'line-through' : 'none',
              }}
            >
              {seg.text}
            </span>
          ))
        ) : (
          <>
            <span style={{ background: '#fca5a5', textDecoration: 'line-through' }}>
              {beforeStr}
            </span>
            {' '}
            <span style={{ background: '#86efac' }}>{afterStr}</span>
          </>
        )}
      </div>
    </div>
  );
}

export default function VersionDiffView({
  contentId,
  fromVersionId,
  toVersionId,
}: VersionDiffViewProps) {
  const [viewMode, setViewMode] = useState<ViewMode>('unified');

  const { data: diff, isLoading, isError } = useQuery({
    queryKey: ['content', contentId, 'diff', fromVersionId, toVersionId],
    queryFn: () => diffVersions(contentId, fromVersionId, toVersionId),
    enabled: !!contentId && !!fromVersionId && !!toVersionId,
  });

  if (isLoading) return <LoadingSpinner size={20} message="Loading diff..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', fontSize: 13 }}>
        Failed to load diff.
      </div>
    );
  }

  const diffData = diff?.data;

  if (!diffData || diffData.changes.length === 0) {
    return (
      <div style={{ color: 'var(--color-text-muted)', fontSize: 13 }}>
        No differences found between these versions.
      </div>
    );
  }

  return (
    <div>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 12,
        }}
      >
        <h3 style={{ fontSize: 14, fontWeight: 600 }}>Differences</h3>
        <div style={{ display: 'flex', gap: 4 }}>
          <button
            className={viewMode === 'unified' ? 'btn btn-primary' : 'btn btn-secondary'}
            style={{ fontSize: 11, padding: '3px 8px' }}
            onClick={() => setViewMode('unified')}
          >
            Unified
          </button>
          <button
            className={viewMode === 'side-by-side' ? 'btn btn-primary' : 'btn btn-secondary'}
            style={{ fontSize: 11, padding: '3px 8px' }}
            onClick={() => setViewMode('side-by-side')}
          >
            Side by Side
          </button>
        </div>
      </div>

      {diffData.changes.map((cf) => (
        <DiffFieldRow
          key={cf.field}
          field={cf.field}
          before={cf.before}
          after={cf.after}
          viewMode={viewMode}
        />
      ))}
    </div>
  );
}
