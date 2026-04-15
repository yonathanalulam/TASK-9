import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getContent, updateContent } from '@/api/content';
import { listStores } from '@/api/stores';
import { listRegions } from '@/api/regions';
import LoadingSpinner from '@/components/common/LoadingSpinner';
import ConflictResolutionDialog from '@/components/common/ConflictResolutionDialog';

export default function ContentEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [contentType, setContentType] = useState('');
  const [authorName, setAuthorName] = useState('');
  const [tags, setTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState('');
  const [storeId, setStoreId] = useState('');
  const [regionId, setRegionId] = useState('');
  const [changeReason, setChangeReason] = useState('');
  const [formError, setFormError] = useState('');
  const [conflictOpen, setConflictOpen] = useState(false);

  const {
    data: envelope,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery({
    queryKey: ['content', id],
    queryFn: () => getContent(id!),
    enabled: !!id,
  });

  const content = envelope?.data;

  const { data: storesData } = useQuery({
    queryKey: ['stores', 'all'],
    queryFn: () => listStores({ per_page: 200 }),
  });

  const { data: regionsData } = useQuery({
    queryKey: ['regions', 'all'],
    queryFn: () => listRegions({ per_page: 200 }),
  });

  // Populate form when content loads
  useEffect(() => {
    if (content) {
      setTitle(content.title);
      setBody(content.body);
      setContentType(content.content_type);
      setAuthorName(content.author_name);
      setTags([...content.tags]);
      setStoreId(content.store_id ?? '');
      setRegionId(content.region_id ?? '');
    }
  }, [content]);

  const mutation = useMutation({
    mutationFn: () =>
      updateContent(
        id!,
        {
          title,
          body,
          content_type: contentType,
          author_name: authorName,
          tags,
          store_id: storeId || null,
          region_id: regionId || null,
          change_reason: changeReason || undefined,
        },
        content!.version,
      ),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['content', id] });
      navigate(`/content/${id}`);
    },
    onError: (err: unknown) => {
      const axiosErr = err as { response?: { status?: number } };
      if (axiosErr?.response?.status === 409) {
        setConflictOpen(true);
      } else {
        setFormError(err instanceof Error ? err.message : 'Failed to update content');
      }
    },
  });

  const handleTagKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const value = tagInput.trim().replace(/,+$/, '');
      if (value && !tags.includes(value)) {
        setTags([...tags, value]);
      }
      setTagInput('');
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');

    if (!title.trim()) {
      setFormError('Title is required');
      return;
    }
    if (!body.trim()) {
      setFormError('Body is required');
      return;
    }
    if (!authorName.trim()) {
      setFormError('Author name is required');
      return;
    }

    mutation.mutate();
  };

  const handleConflictReload = async () => {
    const result = await refetch();
    const reloaded = result.data?.data;
    if (reloaded) {
      setTitle(reloaded.title);
      setBody(reloaded.body);
      setContentType(reloaded.content_type);
      setAuthorName(reloaded.author_name);
      setTags([...reloaded.tags]);
      setStoreId(reloaded.store_id ?? '');
      setRegionId(reloaded.region_id ?? '');
    }
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
          /{' '}
          <Link
            to={`/content/${id}`}
            style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}
          >
            {content.title}
          </Link>{' '}
          / Edit
        </h1>
      </div>

      <div className="card" style={{ maxWidth: 720 }}>
        <form onSubmit={handleSubmit}>
          {formError && (
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
              {formError}
            </div>
          )}

          <div className="form-group">
            <label>Title</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
            />
          </div>

          <div className="form-group">
            <label>Body</label>
            <textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={12}
              style={{ resize: 'vertical' }}
            />
          </div>

          <div className="form-group">
            <label>Content Type</label>
            <select value={contentType} onChange={(e) => setContentType(e.target.value)}>
              <option value="JOB_POST">Job Post</option>
              <option value="OPERATIONAL_NOTICE">Operational Notice</option>
              <option value="VENDOR_BULLETIN">Vendor Bulletin</option>
            </select>
          </div>

          <div className="form-group">
            <label>Author Name</label>
            <input
              type="text"
              value={authorName}
              onChange={(e) => setAuthorName(e.target.value)}
            />
          </div>

          <div className="form-group">
            <label>Tags</label>
            <div
              style={{
                display: 'flex',
                flexWrap: 'wrap',
                gap: 6,
                marginBottom: tags.length > 0 ? 8 : 0,
              }}
            >
              {tags.map((tag) => (
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
                    onClick={() => setTags(tags.filter((t) => t !== tag))}
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
              value={tagInput}
              onChange={(e) => setTagInput(e.target.value)}
              onKeyDown={handleTagKeyDown}
              placeholder="Type a tag and press Enter or comma"
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="form-group">
              <label>Store (optional)</label>
              <select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
                <option value="">-- None --</option>
                {(storesData?.data ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label>Region (optional)</label>
              <select value={regionId} onChange={(e) => setRegionId(e.target.value)}>
                <option value="">-- None --</option>
                {(regionsData?.data ?? []).map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="form-group">
            <label>Change Reason (optional)</label>
            <input
              type="text"
              value={changeReason}
              onChange={(e) => setChangeReason(e.target.value)}
              placeholder="Briefly describe what changed"
            />
          </div>

          <div
            style={{
              display: 'flex',
              gap: 8,
              marginTop: 8,
              alignItems: 'center',
            }}
          >
            <button
              type="submit"
              className="btn btn-primary"
              disabled={mutation.isPending}
            >
              {mutation.isPending ? 'Saving...' : 'Save Changes'}
            </button>
            <Link
              to={`/content/${id}`}
              className="btn btn-secondary"
              style={{ textDecoration: 'none' }}
            >
              Cancel
            </Link>
            <span
              style={{
                marginLeft: 'auto',
                fontSize: 12,
                color: 'var(--color-text-muted)',
              }}
            >
              Version {content.version}
            </span>
          </div>
        </form>
      </div>

      <ConflictResolutionDialog
        isOpen={conflictOpen}
        onReload={handleConflictReload}
        onClose={() => setConflictOpen(false)}
      />
    </div>
  );
}
