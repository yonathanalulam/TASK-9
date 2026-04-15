interface LoadingSpinnerProps {
  size?: number;
  message?: string;
}

export default function LoadingSpinner({ size = 32, message }: LoadingSpinnerProps) {
  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 40,
        gap: 12,
      }}
    >
      <div
        style={{
          width: size,
          height: size,
          border: '3px solid var(--color-border)',
          borderTopColor: 'var(--color-primary)',
          borderRadius: '50%',
          animation: 'spin 0.8s linear infinite',
        }}
      />
      {message && (
        <span style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>{message}</span>
      )}

      {/* Inline keyframes */}
      <style>{`
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      `}</style>
    </div>
  );
}
