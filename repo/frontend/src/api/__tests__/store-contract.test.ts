import { vi, describe, it, expect, beforeEach } from 'vitest';
import type { Store } from '../types';

/* ------------------------------------------------------------------ */
/*  Mock the API client (axios instance used by all API modules)       */
/* ------------------------------------------------------------------ */
const { mockGet } = vi.hoisted(() => ({
  mockGet: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet },
}));

import { listStores, getStore } from '../stores';

describe('Store contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

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

  /* ================================================================== */
  /*  Behavior tests — call real API functions with mocked HTTP          */
  /* ================================================================== */

  describe('listStores', () => {
    it('calls GET /stores with query params and returns unwrapped data', async () => {
      const storeData: Store[] = [
        {
          id: 'uuid-1', code: 'S-001', name: 'Store One', store_type: 'STORE',
          status: 'ACTIVE', region_id: 'r-1', timezone: 'UTC',
          address_line_1: '1 Main St', address_line_2: null, city: 'NYC',
          postal_code: '10001', latitude: '40.71', longitude: '-74.00',
          is_active: true, created_at: '2026-01-01T00:00:00Z',
          updated_at: '2026-01-01T00:00:00Z', version: 1,
        },
      ];
      const envelope = {
        data: storeData,
        meta: { request_id: 'req-1', timestamp: '2026-01-01T00:00:00Z' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const params = { page: 1, per_page: 10, region_id: 'r-1' };
      const result = await listStores(params);

      expect(mockGet).toHaveBeenCalledWith('/stores', { params });
      expect(result.data).toHaveLength(1);
      expect(result.data[0].code).toBe('S-001');
      expect(result.data[0].address_line_1).toBe('1 Main St');
      expect(result.meta.request_id).toBe('req-1');
      expect(result.error).toBeNull();
    });

    it('calls GET /stores with no params by default', async () => {
      const envelope = {
        data: [],
        meta: { request_id: 'req-2', timestamp: '2026-01-01T00:00:00Z' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listStores();

      expect(mockGet).toHaveBeenCalledWith('/stores', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('propagates HTTP errors from the client', async () => {
      mockGet.mockRejectedValueOnce({
        response: { status: 422, data: { error: { code: 'VALIDATION_ERROR' } } },
      });

      await expect(listStores({ page: -1 })).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 422 }),
        }),
      );
    });
  });

  describe('getStore', () => {
    it('calls GET /stores/:id and returns the store', async () => {
      const storeData: Store = {
        id: 'store-uuid', code: 'S-002', name: 'Store Two', store_type: 'WAREHOUSE',
        status: 'ACTIVE', region_id: 'r-2', timezone: 'America/New_York',
        address_line_1: '2 Oak Ave', address_line_2: 'Floor 3', city: 'Boston',
        postal_code: '02101', latitude: '42.36', longitude: '-71.06',
        is_active: true, created_at: '2026-02-01T00:00:00Z',
        updated_at: '2026-02-01T00:00:00Z', version: 2,
      };
      const envelope = {
        data: storeData,
        meta: { request_id: 'req-3', timestamp: '2026-02-01T00:00:00Z' },
        error: null,
      };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getStore('store-uuid');

      expect(mockGet).toHaveBeenCalledWith('/stores/store-uuid');
      expect(result.data.id).toBe('store-uuid');
      expect(result.data.store_type).toBe('WAREHOUSE');
      expect(result.data.address_line_2).toBe('Floor 3');
    });

    it('propagates 404 for unknown store', async () => {
      mockGet.mockRejectedValueOnce({
        response: { status: 404, data: { error: { code: 'NOT_FOUND' } } },
      });

      await expect(getStore('nonexistent')).rejects.toEqual(
        expect.objectContaining({
          response: expect.objectContaining({ status: 404 }),
        }),
      );
    });
  });
});
