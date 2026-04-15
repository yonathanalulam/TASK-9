import { describe, it, expect } from 'vitest';
import type { VersionDiff, DiffChange } from '../content';

describe('Content diff contract', () => {
  it('VersionDiff type has v1, v2, and changes fields', () => {
    // Verify the type shape compiles and works with the backend contract
    const mockBackendResponse: VersionDiff = {
      v1: { id: 'uuid-1', version_number: 1 },
      v2: { id: 'uuid-2', version_number: 2 },
      changes: [
        { field: 'title', before: 'Old Title', after: 'New Title' },
        { field: 'body', before: 'Old body', after: 'New body' },
      ],
    };

    expect(mockBackendResponse.v1.id).toBe('uuid-1');
    expect(mockBackendResponse.v2.version_number).toBe(2);
    expect(mockBackendResponse.changes).toHaveLength(2);
    expect(mockBackendResponse.changes[0].field).toBe('title');
  });

  it('DiffChange has field, before, after', () => {
    const change: DiffChange = {
      field: 'tags',
      before: ['old-tag'],
      after: ['new-tag'],
    };
    expect(change.field).toBe('tags');
    expect(Array.isArray(change.before)).toBe(true);
  });

  // Regression test: VersionDiff must NOT have a changed_fields property
  it('VersionDiff uses changes (not changed_fields)', () => {
    const diff: VersionDiff = {
      v1: { id: '1', version_number: 1 },
      v2: { id: '2', version_number: 2 },
      changes: [],
    };
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    expect((diff as any).changed_fields).toBeUndefined();
    expect(diff.changes).toBeDefined();
  });
});
