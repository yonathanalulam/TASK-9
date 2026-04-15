/* ------------------------------------------------------------------ */
/*  SourceStatusIndicator — colored dot + label for source status      */
/* ------------------------------------------------------------------ */

export type SourceStatus = 'ACTIVE' | 'DEGRADED' | 'PAUSED' | 'DISABLED';

interface Props {
  status: SourceStatus;
}

const statusConfig: Record<SourceStatus, { color: string; label: string }> = {
  ACTIVE: { color: '#16a34a', label: 'Active' },
  DEGRADED: { color: '#eab308', label: 'Degraded' },
  PAUSED: { color: '#f97316', label: 'Paused' },
  DISABLED: { color: '#dc2626', label: 'Disabled' },
};

export default function SourceStatusIndicator({ status }: Props) {
  const cfg = statusConfig[status] ?? { color: '#94a3b8', label: status };

  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
      <span
        style={{
          display: 'inline-block',
          width: 10,
          height: 10,
          borderRadius: '50%',
          background: cfg.color,
          flexShrink: 0,
        }}
      />
      <span style={{ fontSize: 13, fontWeight: 500 }}>{cfg.label}</span>
    </span>
  );
}
