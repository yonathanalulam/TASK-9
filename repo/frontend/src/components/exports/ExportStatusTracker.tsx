export type ExportStatus = 'REQUESTED' | 'AUTHORIZED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED' | 'EXPIRED';

interface ExportStatusTrackerProps {
  status: ExportStatus;
}

const STEPS: { key: ExportStatus; label: string }[] = [
  { key: 'REQUESTED', label: 'Requested' },
  { key: 'AUTHORIZED', label: 'Authorized' },
  { key: 'RUNNING', label: 'Running' },
  { key: 'SUCCEEDED', label: 'Succeeded' },
];

const stepIndex: Record<string, number> = {
  REQUESTED: 0,
  AUTHORIZED: 1,
  RUNNING: 2,
  SUCCEEDED: 3,
  FAILED: -1,
  EXPIRED: -1,
};

export default function ExportStatusTracker({ status }: ExportStatusTrackerProps) {
  const currentIdx = stepIndex[status] ?? -1;
  const isFailed = status === 'FAILED';
  const isExpired = status === 'EXPIRED';

  return (
    <div>
      {/* Pipeline steps */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 0,
        }}
      >
        {STEPS.map((step, idx) => {
          const isActive = idx === currentIdx;
          const isCompleted = currentIdx >= 0 && idx < currentIdx;
          const isPending = currentIdx >= 0 && idx > currentIdx;

          let bg = '#e2e8f0';
          let fg = '#94a3b8';
          if (isCompleted) {
            bg = '#dcfce7';
            fg = '#166534';
          } else if (isActive) {
            bg = '#dbeafe';
            fg = '#1e40af';
          } else if (isPending) {
            bg = '#f1f5f9';
            fg = '#94a3b8';
          }

          return (
            <div key={step.key} style={{ display: 'flex', alignItems: 'center' }}>
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  padding: '6px 14px',
                  background: bg,
                  color: fg,
                  fontSize: 12,
                  fontWeight: isActive ? 700 : 500,
                  borderRadius: 4,
                  whiteSpace: 'nowrap',
                }}
              >
                {isCompleted && (
                  <span style={{ marginRight: 4, fontSize: 14 }}>&#10003;</span>
                )}
                {step.label}
              </div>
              {idx < STEPS.length - 1 && (
                <div
                  style={{
                    width: 24,
                    height: 2,
                    background: isCompleted ? '#86efac' : '#e2e8f0',
                  }}
                />
              )}
            </div>
          );
        })}
      </div>

      {/* Error / Expired banner */}
      {isFailed && (
        <div
          style={{
            marginTop: 8,
            padding: '6px 12px',
            background: '#fee2e2',
            color: '#991b1b',
            borderRadius: 4,
            fontSize: 12,
            fontWeight: 600,
          }}
        >
          Export Failed
        </div>
      )}
      {isExpired && (
        <div
          style={{
            marginTop: 8,
            padding: '6px 12px',
            background: '#f1f5f9',
            color: '#64748b',
            borderRadius: 4,
            fontSize: 12,
            fontWeight: 600,
          }}
        >
          Export Expired
        </div>
      )}
    </div>
  );
}
