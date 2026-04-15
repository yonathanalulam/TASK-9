/* ------------------------------------------------------------------ */
/*  OfflineQueueIndicator — badge showing queued mutation count        */
/* ------------------------------------------------------------------ */

import { useMutationQueue } from '@/services/mutationQueue/useMutationQueue';

export default function OfflineQueueIndicator() {
  const { queueSize, isReplaying } = useMutationQueue();

  if (queueSize === 0 && !isReplaying) return null;

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 6,
        fontSize: 13,
      }}
    >
      {isReplaying ? (
        <span
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 4,
            padding: '2px 10px',
            borderRadius: 12,
            background: '#dbeafe',
            color: '#1e40af',
            fontWeight: 500,
          }}
        >
          Syncing...
        </span>
      ) : (
        queueSize > 0 && (
          <span
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: 4,
              padding: '2px 10px',
              borderRadius: 12,
              background: '#fef3c7',
              color: '#92400e',
              fontWeight: 500,
            }}
          >
            {queueSize} pending
          </span>
        )
      )}
    </div>
  );
}
