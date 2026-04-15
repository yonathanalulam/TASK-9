/* ------------------------------------------------------------------ */
/*  HealthEventTimeline — chronological list of health events          */
/* ------------------------------------------------------------------ */

import type { HealthEvent } from '@/api/scraping';

export type HealthEventType = 'INFO' | 'WARNING' | 'ERROR' | 'RECOVERY';

interface Props {
  events: HealthEvent[];
}

const typeConfig: Record<HealthEventType, { icon: string; color: string }> = {
  INFO: { icon: '\u2139', color: '#3b82f6' },
  WARNING: { icon: '\u26A0', color: '#eab308' },
  ERROR: { icon: '\u2716', color: '#dc2626' },
  RECOVERY: { icon: '\u2714', color: '#16a34a' },
};

export default function HealthEventTimeline({ events }: Props) {
  if (events.length === 0) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: 'var(--color-text-muted)' }}>
        No health events recorded.
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 0 }}>
      {events.map((event, idx) => {
        const cfg = typeConfig[event.type as HealthEventType] ?? typeConfig.INFO;
        return (
          <div
            key={event.id}
            style={{
              display: 'flex',
              gap: 12,
              padding: '12px 0',
              borderBottom:
                idx < events.length - 1 ? '1px solid var(--color-border, #e2e8f0)' : undefined,
            }}
          >
            {/* Icon column */}
            <div
              style={{
                width: 28,
                height: 28,
                borderRadius: '50%',
                background: `${cfg.color}18`,
                color: cfg.color,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: 14,
                fontWeight: 700,
                flexShrink: 0,
              }}
            >
              {cfg.icon}
            </div>

            {/* Content */}
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 2 }}>
                <span
                  style={{
                    fontSize: 12,
                    fontWeight: 600,
                    textTransform: 'uppercase',
                    color: cfg.color,
                  }}
                >
                  {event.type}
                </span>
                <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
                  {new Date(event.created_at).toLocaleString()}
                </span>
              </div>
              <div style={{ fontSize: 14 }}>{event.message}</div>
              {event.detail && (
                <div
                  style={{
                    fontSize: 12,
                    color: 'var(--color-text-muted)',
                    marginTop: 4,
                    whiteSpace: 'pre-wrap',
                  }}
                >
                  {event.detail}
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
