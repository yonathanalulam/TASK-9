import { describe, it, expect } from 'vitest';
import type { Store } from '../types';

describe('Store contract', () => {
  it('Store interface includes all backend-returned fields', () => {
    const mockStore: Store = {
      id: 'uuid',
      code: 'STORE-001',
      name: 'Test Store',
      store_type: 'STORE',
      status: 'ACTIVE',
      region_id: 'region-uuid',
      timezone: 'UTC',
      address_line_1: '123 Main St',
      address_line_2: null,
      city: 'New York',
      postal_code: '10001',
      latitude: '40.7128',
      longitude: '-74.0060',
      is_active: true,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
      version: 1,
    };
    // All 17 fields present
    expect(Object.keys(mockStore)).toHaveLength(17);
    expect(mockStore.address_line_1).toBe('123 Main St');
    expect(mockStore.postal_code).toBe('10001');
    expect(mockStore.created_at).toBeDefined();
  });

  it('Store uses snake_case field names matching backend', () => {
    const mockStore: Store = {
      id: '1', code: 'S', name: 'S', store_type: 'STORE',
      status: 'ACTIVE', region_id: 'r', timezone: 'UTC',
      address_line_1: null, address_line_2: null, city: null,
      postal_code: null, latitude: null, longitude: null,
      is_active: true, created_at: '', updated_at: '', version: 1,
    };
    // Verify NO camelCase fields exist
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const s = mockStore as any;
    expect(s.storeType).toBeUndefined();
    expect(s.regionId).toBeUndefined();
    expect(s.isActive).toBeUndefined();
    expect(s.addressLine1).toBeUndefined();
    expect(s.postalCode).toBeUndefined();
    expect(s.createdAt).toBeUndefined();
    // snake_case exists
    expect(s.store_type).toBeDefined();
    expect(s.region_id).toBeDefined();
    expect(s.is_active).toBeDefined();
  });
});
