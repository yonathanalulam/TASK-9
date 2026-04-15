/* ------------------------------------------------------------------ */
/*  KpiCard — large-value stat card with optional trend indicator       */
/* ------------------------------------------------------------------ */

export type TrendDirection = 'up' | 'down' | 'flat';

interface KpiCardProps {
  title: string;
  value: number | string;
  subtitle?: string;
  trend?: TrendDirection;
}

const trendArrow: Record<TrendDirection, { symbol: string; color: string }> = {
  up: { symbol: '\u2191', color: 'var(--color-success, #16a34a)' },
  down: { symbol: '\u2193', color: 'var(--color-danger, #dc2626)' },
  flat: { symbol: '\u2192', color: 'var(--color-text-muted, #94a3b8)' },
};

export default function KpiCard({ title, value, subtitle, trend }: KpiCardProps) {
  return (
    <div
      className="card"
      style={{
        textAlign: 'center',
        padding: '24px 16px',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 6,
      }}
    >
      <div
        style={{
          fontSize: 12,
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.06em',
          color: 'var(--color-text-muted)',
        }}
      >
        {title}
      </div>

      <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
        <span style={{ fontSize: 32, fontWeight: 700, lineHeight: 1.1 }}>
          {typeof value === 'number' ? value.toLocaleString() : value}
        </span>
        {trend && (
          <span
            style={{
              fontSize: 18,
              fontWeight: 600,
              color: trendArrow[trend].color,
            }}
          >
            {trendArrow[trend].symbol}
          </span>
        )}
      </div>

      {subtitle && (
        <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>{subtitle}</div>
      )}
    </div>
  );
}
