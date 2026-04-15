import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { createContent } from '@/api/content';
import { listStores } from '@/api/stores';
import { listRegions } from '@/api/regions';
import LoadingSpinner from '@/components/common/LoadingSpinner';

export default function ContentCreatePage() {
  const navigate = useNavigate();

  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [contentType, setContentType] = useState('JOB_POST');
  const [authorName, setAuthorName] = useState('');
  const [tagInput, setTagInput] = useState('');
  const [tags, setTags] = useState<string[]>([]);
  const [storeId, setStoreId] = useState('');
  const [regionId, setRegionId] = useState('');
  const [formError, setFormError] = useState('');

  const { data: storesData } = useQuery({
    queryKey: ['stores', 'all'],
    queryFn: () => listStores({ per_page: 200 }),
  });

  const { data: regionsData } = useQuery({
    queryKey: ['regions', 'all'],
    queryFn: () => listRegions({ per_page: 200 }),
  });

  const mutation = useMutation({
    mutationFn: () =>
      createContent({
        title,
        body,
        content_type: contentType,
        author_name: authorName,
        tags: tags.length > 0 ? tags : undefined,
        store_id: storeId || null,
        region_id: regionId || null,
      }),
    onSuccess: (created) => {
      navigate(`/content/${created.data.id}`);
    },
    onError: (err: Error) => {
      setFormError(err.message || 'Failed to create content');
    },
  });

  const handleTagInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const value = tagInput.trim().replace(/,+$/, '');
      if (value && !tags.includes(value)) {
        setTags([...tags, value]);
      }
      setTagInput('');
    }
  };

  const removeTag = (tag: string) => {
    setTags(tags.filter((t) => t !== tag));
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

  return (
    <div>
      <div className="page-header">
        <h1>
          <Link to="/content" style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>
            Content
          </Link>{' '}
          / New
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
              placeholder="Enter content title"
            />
          </div>

          <div className="form-group">
            <label>Body</label>
            <textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="Write your content here..."
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
              placeholder="Author name"
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
                    onClick={() => removeTag(tag)}
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
              onKeyDown={handleTagInputKeyDown}
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

          <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
            <button
              type="submit"
              className="btn btn-primary"
              disabled={mutation.isPending}
            >
              {mutation.isPending ? 'Creating...' : 'Create Content'}
            </button>
            <Link
              to="/content"
              className="btn btn-secondary"
              style={{ textDecoration: 'none' }}
            >
              Cancel
            </Link>
          </div>
        </form>
      </div>

      {mutation.isPending && <LoadingSpinner message="Creating content..." />}
    </div>
  );
}
