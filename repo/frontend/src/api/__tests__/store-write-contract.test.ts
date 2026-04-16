import { vi, describe, it, expect, beforeEach } from 'vitest';
import type { Store } from '../types';

/* ------------------------------------------------------------------ */
/*  Mock the API client (axios instance used by all API modules)       */
/* ------------------------------------------------------------------ */
const { mockPost, mockPut } = vi.hoisted(() => ({
  mockPost: vi.fn(),
  mockPut: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { post: mockPost, put: mockPut },
}));

import { createStore, updateStore } from '../stores';

describe('Store write contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

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

  /* ================================================================== */
  /*  Behavior tests — call real API functions with mocked HTTP          */
  /* ================================================================== */

  describe('createStore', () => {
    const newStorePayload = {
      code: 'STORE-001',
      name: 'Test Store',
      store_type: 'STORE',
      region_id: 'region-uuid',
      address_line_1: '123 Main St',
      address_line_2: 'Suite 100',
      city: 'New York',
      postal_code: '10001',
    };

    const createdStore: Store = {
      id: 'new-uuid', code: 'STORE-001', name: 'Test Store',
      store_type: 'STORE', status: 'ACTIVE', region_id: 'region-uuid',
      timezone: 'UTC', address_line_1: '123 Main St', address_line_2: 'Suite 100',
      city: 'New York', postal_code: '10001', latitude: null, longitude: null,
      is_active: true, created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z', version: 1,
    };

    it('calls POST /stores with the payload and returns the created store', async () => {
      const envelope = {
        data: createdStore,
        meta: { request_id: 'req-1', timestamp: '2026-01-01T00:00:00Z' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await createStore(newStorePayload);

      expect(mockPost).toHaveBeenCalledWith('/stores', newStorePayload);
      expect(result.data.id).toBe('new-uuid');
      expect(result.data.code).toBe('STORE-001');
      expect(result.data.address_line_1).toBe('123 Main St');
      expect(result.data.address_line_2).toBe('Suite 100');
      expect(result.data.version).toBe(1);
    });

    it('sends snake_case fields to the backend, not camelCase', async () => {
      const envelope = {
        data: createdStore,
        meta: { request_id: 'req-2', timestamp: '2026-01-01T00:00:00Z' },
        error: null,
      };
      mockPost.mockResolvedValueOnce({ data: envelope });

      await createStore(newStorePayload);

      const sentPayload = mockPost.mock.calls[0][1];
      expect(sentPayload).toHaveProperty('address_line_1');
      expect(sentPayload).toHaveProperty('store_type');
      expect(sentPayload).toHaveProperty('region_id');
      expect(sentPayload).not.toHaveProperty('addressLine1');
      expect(sentPayload).not.toHaveProperty('storeType');
      expect(sentPayload).not.toHaveProperty('regionId');
    });

    it('propagates validation errors', async () => {
      mockPost.mockRejectedValueOnce({
        response: { status: 422, data: { error: { code: 'VALIDATION_ERROR', message: 'Invalid' } } },
      });

      await expect(createStore(newStorePayload)).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 422 }),
        }),
      );
    });
  });

  describe('updateStore', () => {
    it('calls PUT /stores/:id with data and If-Match header for optimistic locking', async () => {
      const updatedStore: Store = {
        id: 'store-uuid', code: 'STORE-001', name: 'Updated Store',
        store_type: 'STORE', status: 'ACTIVE', region_id: 'region-uuid',
        timezone: 'UTC', address_line_1: '456 New St', address_line_2: null,
        city: 'Boston', postal_code: '02101', latitude: null, longitude: null,
        is_active: true, created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-02-01T00:00:00Z', version: 2,
      };
      const envelope = {
        data: updatedStore,
        meta: { request_id: 'req-3', timestamp: '2026-02-01T00:00:00Z' },
        error: null,
      };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const updateData = { name: 'Updated Store', address_line_1: '456 New St', city: 'Boston' };
      const result = await updateStore('store-uuid', updateData, 1);

      expect(mockPut).toHaveBeenCalledWith(
        '/stores/store-uuid',
        updateData,
        { headers: { 'If-Match': '1' } },
      );
      expect(result.data.name).toBe('Updated Store');
      expect(result.data.version).toBe(2);
    });

    it('propagates 409 conflict when version is stale', async () => {
      mockPut.mockRejectedValueOnce({
        response: { status: 409, data: { error: { code: 'VERSION_CONFLICT' } } },
      });

      await expect(updateStore('store-uuid', { name: 'X' }, 1)).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 409 }),
        }),
      );
    });
  });
});
