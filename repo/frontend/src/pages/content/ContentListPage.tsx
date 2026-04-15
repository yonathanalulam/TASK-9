import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { listContent } from '@/api/content';
import type { ContentItem } from '@/api/types';
import DataTable from '@/components/common/DataTable';
import type { Column } from '@/components/common/DataTable';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import dayjs from 'dayjs';

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

const statusBadge: Record<string, string> = {
  DRAFT: 'badge',
  PUBLISHED: 'badge badge-success',
  UPDATED: 'badge badge-info',
  ARCHIVED: 'badge badge-danger',
  ROLLED_BACK: 'badge badge-warning',
};

const columns: Column<ContentItem>[] = [
  {
    key: 'title',
    header: 'Title',
    render: (c) => (
      <Link to={`/content/${c.id}`} style={{ fontWeight: 500 }}>
        {c.title}
      </Link>
    ),
  },
  {
    key: 'content_type',
    header: 'Type',
    width: '130px',
    render: (c) => (
      <span className={typeBadge[c.content_type] ?? 'badge'}>
        {typeLabel[c.content_type] ?? c.content_type}
      </span>
    ),
  },
  {
    key: 'author_name',
    header: 'Author',
    width: '150px',
    render: (c) => c.author_name,
  },
  {
    key: 'status',
    header: 'Status',
    width: '120px',
    render: (c) => (
      <span className={statusBadge[c.status] ?? 'badge'}>
        {c.status.replace('_', ' ')}
      </span>
    ),
  },
  {
    key: 'published_at',
    header: 'Published',
    width: '140px',
    render: (c) =>
      c.published_at ? dayjs(c.published_at).format('MMM D, YYYY') : '--',
  },
  {
    key: 'view_count',
    header: 'Views',
    width: '80px',
    render: (c) => String(c.view_count),
  },
];

export default function ContentListPage() {
  const [page, setPage] = useState(1);
  const [contentType, setContentType] = useState('');
  const [status, setStatus] = useState('');
  const per_page = 20;

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['content', page, per_page, contentType, status],
    queryFn: () =>
      listContent({
        page,
        per_page,
        content_type: contentType || undefined,
        status: status || undefined,
      }),
  });

  if (isLoading) return <LoadingSpinner message="Loading content..." />;

  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load content: {(error as Error).message}
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <h1>Content</h1>
        <Link
          to="/content/new"
          className="btn btn-primary"
          style={{ textDecoration: 'none', fontSize: 13, padding: '6px 14px' }}
        >
          Create Content
        </Link>
      </div>

      {/* Filter panel */}
      <div
        className="card"
        style={{
          display: 'flex',
          gap: 16,
          alignItems: 'center',
          marginBottom: 16,
          padding: '12px 16px',
        }}
      >
        <div className="form-group" style={{ marginBottom: 0, width: 180 }}>
          <label>Content Type</label>
          <select
            value={contentType}
            onChange={(e) => {
              setContentType(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All Types</option>
            <option value="JOB_POST">Job Post</option>
            <option value="OPERATIONAL_NOTICE">Operational Notice</option>
            <option value="VENDOR_BULLETIN">Vendor Bulletin</option>
          </select>
        </div>

        <div className="form-group" style={{ marginBottom: 0, width: 160 }}>
          <label>Status</label>
          <select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All Statuses</option>
            <option value="DRAFT">Draft</option>
            <option value="PUBLISHED">Published</option>
            <option value="UPDATED">Updated</option>
            <option value="ARCHIVED">Archived</option>
            <option value="ROLLED_BACK">Rolled Back</option>
          </select>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        page={page}
        totalPages={data?.meta?.pagination?.total_pages ?? 1}
        onPageChange={setPage}
        keyExtractor={(c) => c.id}
      />
    </div>
  );
}
