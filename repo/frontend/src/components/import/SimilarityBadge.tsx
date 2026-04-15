interface SimilarityBadgeProps {
  score: number | null;
}

function getColor(score: number): { background: string; color: string } {
  if (score >= 0.92) return { background: '#dcfce7', color: '#166534' };
  if (score >= 0.80) return { background: '#fef9c3', color: '#854d0e' };
  return { background: '#f1f5f9', color: '#64748b' };
}

function getLabel(score: number): string {
  if (score >= 0.92) return 'High';
  if (score >= 0.80) return 'Medium';
  return 'Low';
}

export default function SimilarityBadge({ score }: SimilarityBadgeProps) {
  if (score === null || score === undefined) {
    return (
      <span
        className="badge"
        style={{ background: '#f1f5f9', color: '#94a3b8' }}
      >
        N/A
      </span>
    );
  }

  const { background, color } = getColor(score);

  return (
    <span
      className="badge"
      style={{ background, color }}
      title={`Similarity: ${(score * 100).toFixed(1)}%`}
    >
      {(score * 100).toFixed(1)}% {getLabel(score)}
    </span>
  );
}
