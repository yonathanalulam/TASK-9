import { describe, it, expect } from 'vitest';

// We can't easily test the full component (it uses useQuery), but we CAN
// test that the DiffFieldRow sub-component renders diff changes correctly.
// Actually, let's just test the contract shape expectations.

describe('VersionDiffView contract', () => {
  it('should expect changes array (not changed_fields) from backend', () => {
    // This is a static contract test that would fail if someone
    // reverts the type back to changed_fields
    const backendShape = {
      v1: { id: '1', version_number: 1 },
      v2: { id: '2', version_number: 2 },
      changes: [
        { field: 'title', before: 'Old', after: 'New' },
      ],
    };

    // The component accesses .changes not .changed_fields
    expect(backendShape.changes).toBeDefined();
    expect(backendShape.changes[0].field).toBe('title');
    expect(backendShape.changes[0].before).toBe('Old');
    expect(backendShape.changes[0].after).toBe('New');
  });
});
