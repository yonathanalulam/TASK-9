import { useState, useCallback } from 'react';
import apiClient from '@/api/client';

interface MaskedFieldProps {
  /** The masked value to display by default, e.g. "***-**-1234" */
  maskedValue: string;
  /** API endpoint to fetch the unmasked value */
  unmaskedEndpoint: string;
  /** Field name in the API response that contains the unmasked value */
  responseField?: string;
}

export default function MaskedField({
  maskedValue,
  unmaskedEndpoint,
  responseField = 'value',
}: MaskedFieldProps) {
  const [revealed, setRevealed] = useState(false);
  const [unmaskedValue, setUnmaskedValue] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleReveal = useCallback(async () => {
    if (revealed) {
      setRevealed(false);
      return;
    }

    if (unmaskedValue !== null) {
      setRevealed(true);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(unmaskedEndpoint);
      const value = res.data?.[responseField] ?? res.data;
      setUnmaskedValue(String(value));
      setRevealed(true);
    } catch (err) {
      setError('Failed to reveal value');
      console.error('MaskedField reveal error:', err);
    } finally {
      setLoading(false);
    }
  }, [revealed, unmaskedValue, unmaskedEndpoint, responseField]);

  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
      <code
        style={{
          fontFamily: 'monospace',
          fontSize: 13,
          padding: '2px 6px',
          background: 'var(--color-bg-muted, #f1f5f9)',
          borderRadius: 4,
        }}
      >
        {revealed && unmaskedValue !== null ? unmaskedValue : maskedValue}
      </code>

      <button
        onClick={handleReveal}
        disabled={loading}
        className="btn btn-secondary"
        style={{
          padding: '2px 8px',
          fontSize: 11,
          lineHeight: '18px',
          minWidth: 48,
        }}
      >
        {loading ? '...' : revealed ? 'Hide' : 'Show'}
      </button>

      {error && (
        <span style={{ fontSize: 11, color: 'var(--color-danger, #dc2626)' }}>
          {error}
        </span>
      )}
    </span>
  );
}
