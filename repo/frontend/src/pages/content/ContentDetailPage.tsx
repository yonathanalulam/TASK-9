import { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  getContent,
  publishContent,
  archiveContent,
  updateContent,
} from '@/api/content';
import type { ContentItem } from '@/api/types';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import VersionTimeline from '@/components/content/VersionTimeline';
import RollbackDialog from '@/components/content/RollbackDialog';
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

export default function ContentDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [isEditing, setIsEditing] = useState(false);
  const [editTitle, setEditTitle] = useState('');
  const [editBody, setEditBody] = useState('');
  const [editTags, setEditTags] = useState<string[]>([]);
  const [editTagInput, setEditTagInput] = useState('');
  const [editError, setEditError] = useState('');
  const [rollbackOpen, setRollbackOpen] = useState(false);

  const {
    data: envelope,
    isLoading,
    isError,
    error,
  } = useQuery({
    queryKey: ['content', id],
    queryFn: () => getContent(id!),
    enabled: !!id,
  });

  const content = envelope?.data;

  const publishMutation = useMutation({
    mutationFn: () => publishContent(id!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['content', id] });
      queryClient.invalidateQueries({ queryKey: ['content', id, 'versions'] });
    },
  });

  const archiveMutation = useMutation({
    mutationFn: () => archiveContent(id!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['content', id] });
      queryClient.invalidateQueries({ queryKey: ['content', id, 'versions'] });
    },
  });

  const editMutation = useMutation({
    mutationFn: () =>
      updateContent(
        id!,
        { title: editTitle, body: editBody, tags: editTags },
        content!.version,
      ),
    onSuccess: () => {
      setIsEditing(false);
      queryClient.invalidateQueries({ queryKey: ['content', id] });
      queryClient.invalidateQueries({ queryKey: ['content', id, 'versions'] });
    },
    onError: (err: unknown) => {
      const axiosErr = err as { response?: { status?: number } };
      if (axiosErr?.response?.status === 409) {
        setEditError(
          'This content was modified by another user. Please reload and try again.',
        );
      } else {
        setEditError(err instanceof Error ? err.message : 'Failed to save changes');
      }
    },
  });

  const startEditing = (c: ContentItem) => {
    setEditTitle(c.title);
    setEditBody(c.body);
    setEditTags([...c.tags]);
    setEditTagInput('');
    setEditError('');
    setIsEditing(true);
  };

  const handleEditTagKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const value = editTagInput.trim().replace(/,+$/, '');
      if (value && !editTags.includes(value)) {
        setEditTags([...editTags, value]);
      }
      setEditTagInput('');
    }
  };

  const handleSaveEdit = (e: React.FormEvent) => {
    e.preventDefault();
    setEditError('');
    if (!editTitle.trim()) {
      setEditError('Title is required');
      return;
    }
    if (!editBody.trim()) {
      setEditError('Body is required');
      return;
    }
    editMutation.mutate();
  };

  if (isLoading) return <LoadingSpinner message="Loading content..." />;
  if (isError) {
    return (
      <div style={{ color: 'var(--color-danger)', padding: 20 }}>
        Failed to load content: {(error as Error).message}
      </div>
    );
  }
  if (!content) return null;

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/content" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Content
          </Link>{' '}
          / {content.title}
        </h1>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <span className={typeBadge[content.content_type] ?? 'badge'}>
            {typeLabel[content.content_type] ?? content.content_type}
          </span>
          <span className={statusBadge[content.status] ?? 'badge'}>
            {content.status.replace('_', ' ')}
          </span>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 340px', gap: 20 }}>
        {/* Main content area */}
        <div>
          {isEditing ? (
            <div className="card">
              <h2 style={{ fontSize: 16, marginBottom: 16 }}>Edit Content</h2>
              <form onSubmit={handleSaveEdit}>
                {editError && (
                  <div
                    style={{
                      background: '#fee2e2',
                      color: '#991b1b',
                      padding: '10px 14px',
                      borderRadius: 'var(--radius)',
                      marginBottom: 16,
                      fontSize: 13,
                    }}
                  >
                    {editError}
                  </div>
                )}

                <div className="form-group">
                  <label>Title</label>
                  <input
                    type="text"
                    value={editTitle}
                    onChange={(e) => setEditTitle(e.target.value)}
                  />
                </div>

                <div className="form-group">
                  <label>Body</label>
                  <textarea
                    value={editBody}
                    onChange={(e) => setEditBody(e.target.value)}
                    rows={12}
                    style={{ resize: 'vertical' }}
                  />
                </div>

                <div className="form-group">
                  <label>Tags</label>
                  <div
                    style={{
                      display: 'flex',
                      flexWrap: 'wrap',
                      gap: 6,
                      marginBottom: editTags.length > 0 ? 8 : 0,
                    }}
                  >
                    {editTags.map((tag) => (
                      <span
                        key={tag}
                        className="badge badge-info"
                        style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: 4,
                          padding: '3px 8px',
                        }}
                      >
                        {tag}
                        <button
                          type="button"
                          onClick={() => setEditTags(editTags.filter((t) => t !== tag))}
                          style={{
                            background: 'none',
                            border: 'none',
                            padding: 0,
                            color: 'inherit',
                            fontSize: 14,
                            lineHeight: 1,
                            cursor: 'pointer',
                          }}
                        >
                          x
                        </button>
                      </span>
                    ))}
                  </div>
                  <input
                    type="text"
                    value={editTagInput}
                    onChange={(e) => setEditTagInput(e.target.value)}
                    onKeyDown={handleEditTagKeyDown}
                    placeholder="Type a tag and press Enter or comma"
                  />
                </div>

                <div style={{ display: 'flex', gap: 8 }}>
                  <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={editMutation.isPending}
                  >
                    {editMutation.isPending ? 'Saving...' : 'Save Changes'}
                  </button>
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setIsEditing(false)}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          ) : (
            <div className="card">
              <h2 style={{ fontSize: 20, marginBottom: 12 }}>{content.title}</h2>

              <div
                style={{
                  display: 'flex',
                  gap: 16,
                  marginBottom: 16,
                  color: 'var(--color-text-muted)',
                  fontSize: 13,
                }}
              >
                <span>By {content.author_name}</span>
                {content.published_at && (
                  <span>
                    Published {dayjs(content.published_at).format('MMM D, YYYY h:mm A')}
                  </span>
                )}
                <span>{content.view_count} views</span>
                <span>{content.reply_count} replies</span>
              </div>

              {content.tags.length > 0 && (
                <div
                  style={{
                    display: 'flex',
                    gap: 6,
                    flexWrap: 'wrap',
                    marginBottom: 16,
                  }}
                >
                  {content.tags.map((tag) => (
                    <span key={tag} className="badge badge-info">
                      {tag}
                    </span>
                  ))}
                </div>
              )}

              <div
                style={{
                  lineHeight: 1.7,
                  whiteSpace: 'pre-wrap',
                  borderTop: '1px solid var(--color-border)',
                  paddingTop: 16,
                }}
              >
                {content.body}
              </div>

              {/* Action buttons */}
              <div
                style={{
                  display: 'flex',
                  gap: 8,
                  marginTop: 20,
                  paddingTop: 16,
                  borderTop: '1px solid var(--color-border)',
                }}
              >
                {content.status === 'DRAFT' && (
                  <button
                    className="btn btn-primary"
                    disabled={publishMutation.isPending}
                    onClick={() => publishMutation.mutate()}
                  >
                    {publishMutation.isPending ? 'Publishing...' : 'Publish'}
                  </button>
                )}

                {(content.status === 'PUBLISHED' || content.status === 'UPDATED') && (
                  <>
                    <button
                      className="btn btn-primary"
                      onClick={() => startEditing(content)}
                    >
                      Edit
                    </button>
                    <button
                      className="btn btn-secondary"
                      onClick={() => navigate(`/content/${content.id}/edit`)}
                    >
                      Full Edit
                    </button>
                    <button
                      className="btn btn-danger"
                      disabled={archiveMutation.isPending}
                      onClick={() => archiveMutation.mutate()}
                    >
                      {archiveMutation.isPending ? 'Archiving...' : 'Archive'}
                    </button>
                  </>
                )}

                {content.status === 'ROLLED_BACK' && (
                  <button
                    className="btn btn-primary"
                    disabled={publishMutation.isPending}
                    onClick={() => publishMutation.mutate()}
                  >
                    {publishMutation.isPending ? 'Publishing...' : 'Republish'}
                  </button>
                )}

                {content.status === 'ARCHIVED' && (
                  <button
                    className="btn btn-primary"
                    disabled={publishMutation.isPending}
                    onClick={() => publishMutation.mutate()}
                  >
                    {publishMutation.isPending ? 'Restoring...' : 'Restore'}
                  </button>
                )}

                <button
                  className="btn btn-secondary"
                  onClick={() => setRollbackOpen(true)}
                >
                  Rollback
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Version timeline sidebar */}
        <div>
          <VersionTimeline contentId={id!} />
        </div>
      </div>

      <RollbackDialog
        contentId={id!}
        isOpen={rollbackOpen}
        onClose={() => setRollbackOpen(false)}
        onSuccess={() => {
          setRollbackOpen(false);
          queryClient.invalidateQueries({ queryKey: ['content', id] });
          queryClient.invalidateQueries({ queryKey: ['content', id, 'versions'] });
        }}
      />
    </div>
  );
}
