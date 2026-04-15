export type ClassificationLevel = 'PUBLIC_INTERNAL' | 'CONFIDENTIAL' | 'RESTRICTED' | 'HIGHLY_RESTRICTED';

interface ClassificationBadgeProps {
  classification: ClassificationLevel;
}

const classificationStyles: Record<ClassificationLevel, { background: string; color: string }> = {
  PUBLIC_INTERNAL: { background: '#dcfce7', color: '#166534' },
  CONFIDENTIAL: { background: '#fef9c3', color: '#854d0e' },
  RESTRICTED: { background: '#ffedd5', color: '#9a3412' },
  HIGHLY_RESTRICTED: { background: '#fee2e2', color: '#991b1b' },
};

const classificationLabels: Record<ClassificationLevel, string> = {
  PUBLIC_INTERNAL: 'Public / Internal',
  CONFIDENTIAL: 'Confidential',
  RESTRICTED: 'Restricted',
  HIGHLY_RESTRICTED: 'Highly Restricted',
};

export default function ClassificationBadge({ classification }: ClassificationBadgeProps) {
  const style = classificationStyles[classification] ?? { background: '#f1f5f9', color: '#64748b' };
  const label = classificationLabels[classification] ?? classification;

  return (
    <span className="badge" style={style}>
      {label}
    </span>
  );
}
