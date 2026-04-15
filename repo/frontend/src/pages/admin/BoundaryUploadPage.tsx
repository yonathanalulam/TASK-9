/* ------------------------------------------------------------------ */
/*  BoundaryUploadPage — drag-and-drop boundary file upload            */
/* ------------------------------------------------------------------ */

import { useState, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { uploadBoundary } from '@/api/boundaries';
import type { BoundaryImport } from '@/api/boundaries';

const MAX_SIZE_BYTES = 25 * 1024 * 1024; // 25 MB
const ACCEPTED_EXTENSIONS = ['.geojson', '.json', '.zip'];

export default function BoundaryUploadPage() {
  const navigate = useNavigate();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [isDragOver, setIsDragOver] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const [uploadResult, setUploadResult] = useState<BoundaryImport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isUploading, setIsUploading] = useState(false);

  const validateFile = useCallback((file: File): string | null => {
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();
    if (!ACCEPTED_EXTENSIONS.includes(ext)) {
      return `Invalid file type. Accepted: ${ACCEPTED_EXTENSIONS.join(', ')}`;
    }
    if (file.size > MAX_SIZE_BYTES) {
      return `File too large. Maximum size is 25 MB.`;
    }
    return null;
  }, []);

  const handleFileSelect = useCallback(
    (file: File) => {
      setError(null);
      setUploadResult(null);
      setUploadProgress(null);
      const err = validateFile(file);
      if (err) {
        setError(err);
        return;
      }
      setSelectedFile(file);
    },
    [validateFile],
  );

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setIsDragOver(false);
      const file = e.dataTransfer.files[0];
      if (file) handleFileSelect(file);
    },
    [handleFileSelect],
  );

  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (file) handleFileSelect(file);
    },
    [handleFileSelect],
  );

  const handleUpload = useCallback(async () => {
    if (!selectedFile) return;
    setIsUploading(true);
    setError(null);
    setUploadProgress(0);

    try {
      // Simulate progress since we use a simple POST
      const progressInterval = setInterval(() => {
        setUploadProgress((prev) => {
          if (prev === null || prev >= 90) return prev;
          return prev + 10;
        });
      }, 200);

      const response = await uploadBoundary(selectedFile);

      clearInterval(progressInterval);
      setUploadProgress(100);
      setUploadResult(response.data);
    } catch (err: unknown) {
      setError((err as Error).message || 'Upload failed');
      setUploadProgress(null);
    } finally {
      setIsUploading(false);
    }
  }, [selectedFile]);

  const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  return (
    <div>
      <div className="page-header">
        <h1>Upload Boundary</h1>
      </div>

      {/* Drop zone */}
      {!uploadResult && (
        <div
          className="card"
          style={{
            padding: 40,
            textAlign: 'center',
            border: isDragOver
              ? '2px dashed var(--color-primary)'
              : '2px dashed var(--color-border)',
            background: isDragOver ? 'rgba(37, 99, 235, 0.04)' : undefined,
            cursor: 'pointer',
            transition: 'border-color 0.15s, background 0.15s',
          }}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current?.click()}
        >
          <input
            ref={fileInputRef}
            type="file"
            accept=".geojson,.json,.zip"
            style={{ display: 'none' }}
            onChange={handleInputChange}
          />

          <div style={{ fontSize: 16, fontWeight: 500, marginBottom: 8 }}>
            {isDragOver
              ? 'Drop file here'
              : 'Drag and drop a boundary file, or click to browse'}
          </div>
          <div style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
            Accepted formats: .geojson, .json, .zip (max 25 MB)
          </div>
        </div>
      )}

      {/* Selected file info */}
      {selectedFile && !uploadResult && (
        <div className="card" style={{ marginTop: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <div>
              <div style={{ fontWeight: 500 }}>{selectedFile.name}</div>
              <div style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
                {formatBytes(selectedFile.size)}
              </div>
            </div>
            <button
              className="btn btn-primary"
              onClick={handleUpload}
              disabled={isUploading}
            >
              {isUploading ? 'Uploading...' : 'Upload'}
            </button>
          </div>

          {/* Progress bar */}
          {uploadProgress !== null && (
            <div
              style={{
                marginTop: 12,
                height: 6,
                background: 'var(--color-border)',
                borderRadius: 3,
                overflow: 'hidden',
              }}
            >
              <div
                style={{
                  height: '100%',
                  width: `${uploadProgress}%`,
                  background: 'var(--color-primary)',
                  borderRadius: 3,
                  transition: 'width 0.3s',
                }}
              />
            </div>
          )}
        </div>
      )}

      {/* Error */}
      {error && (
        <div
          className="card"
          style={{
            marginTop: 16,
            background: '#fee2e2',
            borderColor: '#fecaca',
            color: '#991b1b',
          }}
        >
          {error}
        </div>
      )}

      {/* Upload result */}
      {uploadResult && (
        <div className="card" style={{ marginTop: 16 }}>
          <h3 style={{ marginBottom: 12, fontSize: 16, fontWeight: 600 }}>Upload Complete</h3>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div>
              <span style={{ fontWeight: 500 }}>File:</span> {uploadResult.filename}
            </div>
            <div>
              <span style={{ fontWeight: 500 }}>Status:</span>{' '}
              <span className="badge badge-info">{uploadResult.status}</span>
            </div>
            {uploadResult.area_count !== null && (
              <div>
                <span style={{ fontWeight: 500 }}>Areas parsed:</span>{' '}
                {uploadResult.area_count}
              </div>
            )}
          </div>

          <div style={{ marginTop: 16, display: 'flex', gap: 8 }}>
            <button
              className="btn btn-primary"
              onClick={() => navigate(`/admin/boundaries/${uploadResult.id}`)}
            >
              View Details
            </button>
            <button
              className="btn btn-secondary"
              onClick={() => {
                setSelectedFile(null);
                setUploadResult(null);
                setUploadProgress(null);
                setError(null);
              }}
            >
              Upload Another
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
