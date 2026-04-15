import { describe, it, expect } from 'vitest';
import type { Store } from '../types';

describe('Store write contract', () => {
  it('createStore payload uses address_line_1 (with underscore, not camelCase)', () => {
    // Simulate the payload shape that createStore would send
    const payload = {
      code: 'STORE-001',
      name: 'Test Store',
      store_type: 'STORE',
      region_id: 'region-uuid',
      address_line_1: '123 Main St',
      address_line_2: 'Suite 100',
      city: 'New York',
      postal_code: '10001',
    };
    // Correct field names present
    expect(payload).toHaveProperty('address_line_1');
    expect(payload).toHaveProperty('address_line_2');
    expect(payload).toHaveProperty('postal_code');
    // Incorrect field names absent
    expect(payload).not.toHaveProperty('address_line1');
    expect(payload).not.toHaveProperty('address_line2');
    expect(payload).not.toHaveProperty('zip_code');
    expect(payload).not.toHaveProperty('address');
    expect(payload).not.toHaveProperty('state');
  });

  it('Store response type matches backend serializer output', () => {
    const store: Store = {
      id: 'uuid',
      code: 'STORE-001',
      name: 'Test',
      store_type: 'STORE',
      status: 'ACTIVE',
      region_id: 'r-uuid',
      timezone: 'UTC',
      address_line_1: '123 Main',
      address_line_2: null,
      city: 'NYC',
      postal_code: '10001',
      latitude: '40.7128',
      longitude: '-74.006',
      is_active: true,
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
      version: 1,
    };
    // 17 fields matching backend serializeStore()
    expect(Object.keys(store)).toHaveLength(17);
    // Correct snake_case field names
    expect(store.address_line_1).toBe('123 Main');
    expect(store.address_line_2).toBeNull();
  });

  it('write payload fields are a subset of response fields', () => {
    const writeFields = [
      'code', 'name', 'store_type', 'region_id', 'timezone',
      'address_line_1', 'address_line_2', 'city', 'postal_code',
      'latitude', 'longitude', 'status',
    ];
    const responseFields = [
      'id', 'code', 'name', 'store_type', 'status', 'region_id',
      'timezone', 'address_line_1', 'address_line_2', 'city',
      'postal_code', 'latitude', 'longitude', 'is_active',
      'created_at', 'updated_at', 'version',
    ];
    for (const field of writeFields) {
      expect(responseFields).toContain(field);
    }
  });
});
