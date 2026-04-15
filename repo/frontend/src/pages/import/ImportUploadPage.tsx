import { useState, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { createImport } from '@/api/imports';

const ACCEPTED_TYPES = ['.csv', '.json'];
const MAX_SIZE_MB = 50;

export default function ImportUploadPage() {
  const navigate = useNavigate();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [dragOver, setDragOver] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);

  const uploadMutation = useMutation({
    mutationFn: (file: File) => createImport(file),
    onSuccess: (response) => {
      navigate(`/imports/${response.data.id}`);
    },
  });

  const validateFile = useCallback((file: File): string | null => {
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!ACCEPTED_TYPES.includes(ext)) {
      return `Invalid file type "${ext}". Accepted types: CSV, JSON.`;
    }
    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
      return `File exceeds maximum size of ${MAX_SIZE_MB}MB.`;
    }
    return null;
  }, []);

  const handleFileSelect = useCallback(
    (file: File) => {
      const err = validateFile(file);
      setValidationError(err);
      if (!err) {
        setSelectedFile(file);
      } else {
        setSelectedFile(null);
      }
    },
    [validateFile],
  );

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setDragOver(false);
      const file = e.dataTransfer.files[0];
      if (file) handleFileSelect(file);
    },
    [handleFileSelect],
  );

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      if (selectedFile) {
        uploadMutation.mutate(selectedFile);
      }
    },
    [selectedFile, uploadMutation],
  );

  const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  return (
    <div>
      <div className="page-header">
        <h1>Upload Import Batch</h1>
      </div>

      <form onSubmit={handleSubmit} style={{ maxWidth: 600 }}>
        {/* Drop zone */}
        <div
          className="card"
          onDragOver={(e) => {
            e.preventDefault();
            setDragOver(true);
          }}
          onDragLeave={() => setDragOver(false)}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current?.click()}
          style={{
            padding: 40,
            textAlign: 'center',
            cursor: 'pointer',
            border: dragOver
              ? '2px dashed var(--color-primary)'
              : '2px dashed var(--color-border)',
            background: dragOver ? 'rgba(59,130,246,0.04)' : undefined,
            transition: 'border-color 0.15s, background 0.15s',
          }}
        >
          <input
            ref={fileInputRef}
            type="file"
            accept=".csv,.json"
            style={{ display: 'none' }}
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) handleFileSelect(file);
            }}
          />

          {selectedFile ? (
            <div>
              <div style={{ fontSize: 15, fontWeight: 600, marginBottom: 4 }}>
                {selectedFile.name}
              </div>
              <div style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
                {formatSize(selectedFile.size)} &middot; Click or drop to replace
              </div>
            </div>
          ) : (
            <div>
              <div style={{ fontSize: 15, fontWeight: 500, marginBottom: 4 }}>
                Drop a CSV or JSON file here, or click to browse
              </div>
              <div style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
                Maximum file size: {MAX_SIZE_MB}MB
              </div>
            </div>
          )}
        </div>

        {/* Validation error */}
        {validationError && (
          <div
            style={{
              marginTop: 8,
              padding: '8px 12px',
              background: '#fee2e2',
              color: '#991b1b',
              borderRadius: 4,
              fontSize: 13,
            }}
          >
            {validationError}
          </div>
        )}

        {/* Upload error */}
        {uploadMutation.isError && (
          <div
            style={{
              marginTop: 8,
              padding: '8px 12px',
              background: '#fee2e2',
              color: '#991b1b',
              borderRadius: 4,
              fontSize: 13,
            }}
          >
            Upload failed: {(uploadMutation.error as Error).message}
          </div>
        )}

        {/* Submit */}
        <div style={{ marginTop: 16, display: 'flex', gap: 12 }}>
          <button
            type="submit"
            className="btn btn-primary"
            disabled={!selectedFile || uploadMutation.isPending}
            style={{ fontSize: 13, padding: '8px 20px' }}
          >
            {uploadMutation.isPending ? 'Uploading...' : 'Upload Batch'}
          </button>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => navigate('/imports')}
            style={{ fontSize: 13, padding: '8px 20px' }}
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
}
