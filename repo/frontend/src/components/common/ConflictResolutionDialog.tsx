/* ------------------------------------------------------------------ */
/*  ConflictResolutionDialog — modal for 409 conflicts                 */
/* ------------------------------------------------------------------ */

interface ConflictResolutionDialogProps {
  isOpen: boolean;
  onReload: () => void;
  onClose: () => void;
}

export default function ConflictResolutionDialog({
  isOpen,
  onReload,
  onClose,
}: ConflictResolutionDialogProps) {
  if (!isOpen) return null;

  const handleReload = () => {
    onReload();
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
          width: 420,
          maxWidth: '90vw',
          padding: 24,
          display: 'flex',
          flexDirection: 'column',
          gap: 16,
        }}
      >
        <h2 style={{ fontSize: 18, fontWeight: 600 }}>Conflict Detected</h2>

        <p style={{ color: 'var(--color-text-muted)', lineHeight: 1.6 }}>
          This record was modified by another user. Your changes could not be
          saved. Please reload the latest version and try again.
        </p>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
          <button className="btn btn-secondary" onClick={onClose}>
            Cancel
          </button>
          <button className="btn btn-primary" onClick={handleReload}>
            Reload
          </button>
        </div>
      </div>
    </div>
  );
}
