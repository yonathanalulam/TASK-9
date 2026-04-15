import { useNavigate } from 'react-router-dom';
import type { SearchResult } from '@/api/types';
import dayjs from 'dayjs';

interface SearchResultCardProps {
  item: SearchResult;
}

const typeBadge: Record<string, string> = {
  JOB_POST: 'badge badge-info',
  OPERATIONAL_NOTICE: 'badge badge-warning',
  VENDOR_BULLETIN: 'badge badge-success',
};

const typeLabel: Record<string, string> = {
  JOB_POST: 'Job Post',
  OPERATIONAL_NOTICE: 'Notice',
  VENDOR_BULLETIN: 'Bulletin',
};

/**
 * Renders an HTML string with <mark> tags from the server-provided highlights.
 * Falls back to the plain text if no highlight is available.
 */
function HighlightedText({ html, fallback }: { html?: string; fallback: string }) {
  if (html) {
    return <span dangerouslySetInnerHTML={{ __html: html }} />;
  }
  return <>{fallback}</>;
}

export default function SearchResultCard({ item }: SearchResultCardProps) {
  const navigate = useNavigate();

  return (
    <div
      className="card"
      style={{
        padding: 16,
        cursor: 'pointer',
        transition: 'box-shadow 0.15s',
      }}
      onClick={() => navigate(`/content/${item.id}`)}
      onKeyDown={(e) => {
        if (e.key === 'Enter') navigate(`/content/${item.id}`);
      }}
      role="button"
      tabIndex={0}
    >
      {/* Header row */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 8,
          marginBottom: 6,
        }}
      >
        <span className={typeBadge[item.content_type] ?? 'badge'}>
          {typeLabel[item.content_type] ?? item.content_type}
        </span>
        <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
          {item.author_name}
        </span>
        {item.published_at && (
          <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
            {dayjs(item.published_at).format('MMM D, YYYY')}
          </span>
        )}
      </div>

      {/* Title */}
      <div style={{ fontWeight: 600, fontSize: 15, marginBottom: 6 }}>
        <HighlightedText html={item.highlight_title} fallback={item.title} />
      </div>

      {/* Snippet */}
      {item.snippet && (
        <div
          style={{
            fontSize: 13,
            color: 'var(--color-text-muted)',
            lineHeight: 1.5,
            marginBottom: 8,
          }}
        >
          {item.snippet}
        </div>
      )}

      {/* Bottom row: tags, view count, reply count */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 12,
          fontSize: 12,
          color: 'var(--color-text-muted)',
        }}
      >
        {item.tags.length > 0 && (
          <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
            {item.tags.map((tag) => (
              <span
                key={tag}
                className="badge"
                style={{ fontSize: 11, padding: '1px 6px' }}
              >
                {tag}
              </span>
            ))}
          </div>
        )}
        <span>{item.view_count} views</span>
        <span>{item.reply_count} replies</span>
      </div>

      {/* Inline hover style */}
      <style>{`
        .card:hover {
          box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
      `}</style>
    </div>
  );
}
