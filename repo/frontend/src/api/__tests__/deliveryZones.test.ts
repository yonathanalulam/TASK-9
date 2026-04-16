import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet, mockPost, mockPut, mockDelete } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
  mockDelete: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost, put: mockPut, delete: mockDelete },
}));

import {
  listDeliveryZones,
  getDeliveryZone,
  createDeliveryZone,
  updateDeliveryZone,
  listDeliveryWindows,
  createDeliveryWindow,
  updateDeliveryWindow,
  deleteDeliveryWindow,
} from '../deliveryZones';

describe('DeliveryZones API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('listDeliveryZones', () => {
    it('calls GET /stores/:storeId/delivery-zones', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listDeliveryZones('store-1');

      expect(mockGet).toHaveBeenCalledWith('/stores/store-1/delivery-zones', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listDeliveryZones('store-1', { page: 2, per_page: 10 });

      expect(mockGet).toHaveBeenCalledWith('/stores/store-1/delivery-zones', {
        params: { page: 2, per_page: 10 },
      });
    });
  });

  describe('getDeliveryZone', () => {
    it('calls GET /delivery-zones/:id', async () => {
      const zone = { id: 'zone-1', name: 'Downtown' };
      const envelope = { data: zone, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getDeliveryZone('zone-1');

      expect(mockGet).toHaveBeenCalledWith('/delivery-zones/zone-1');
      expect(result.data.name).toBe('Downtown');
    });
  });

  describe('createDeliveryZone', () => {
    it('calls POST /stores/:storeId/delivery-zones', async () => {
      const zone = { id: 'zone-new', name: 'Uptown' };
      const envelope = { data: zone, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { name: 'Uptown', radius_km: 5 };
      const result = await createDeliveryZone('store-1', payload);

      expect(mockPost).toHaveBeenCalledWith('/stores/store-1/delivery-zones', payload);
      expect(result.data.id).toBe('zone-new');
    });
  });

  describe('updateDeliveryZone', () => {
    it('calls PUT /delivery-zones/:id with If-Match header', async () => {
      const zone = { id: 'zone-1', name: 'Updated' };
      const envelope = { data: zone, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const payload = { name: 'Updated' };
      const result = await updateDeliveryZone('zone-1', payload, 1);

      expect(mockPut).toHaveBeenCalledWith('/delivery-zones/zone-1', payload, {
        headers: { 'If-Match': '1' },
      });
      expect(result.data.name).toBe('Updated');
    });
  });

  describe('listDeliveryWindows', () => {
    it('calls GET /delivery-zones/:zoneId/windows', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listDeliveryWindows('zone-1');

      expect(mockGet).toHaveBeenCalledWith('/delivery-zones/zone-1/windows');
      expect(result.data).toEqual([]);
    });
  });

  describe('createDeliveryWindow', () => {
    it('calls POST /delivery-zones/:zoneId/windows', async () => {
      const window = { id: 'win-1', day_of_week: 1, start_time: '09:00', end_time: '17:00' };
      const envelope = { data: window, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { day_of_week: 1, start_time: '09:00', end_time: '17:00' };
      const result = await createDeliveryWindow('zone-1', payload);

      expect(mockPost).toHaveBeenCalledWith('/delivery-zones/zone-1/windows', payload);
      expect(result.data.id).toBe('win-1');
    });
  });

  describe('updateDeliveryWindow', () => {
    it('calls PUT /delivery-windows/:id', async () => {
      const window = { id: 'win-1', is_active: false };
      const envelope = { data: window, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const result = await updateDeliveryWindow('win-1', { is_active: false });

      expect(mockPut).toHaveBeenCalledWith('/delivery-windows/win-1', { is_active: false });
      expect(result.data.is_active).toBe(false);
    });
  });

  describe('deleteDeliveryWindow', () => {
    it('calls DELETE /delivery-windows/:id', async () => {
      const envelope = { data: null, meta: {}, error: null };
      mockDelete.mockResolvedValueOnce({ data: envelope });

      const result = await deleteDeliveryWindow('win-1');

      expect(mockDelete).toHaveBeenCalledWith('/delivery-windows/win-1');
      expect(result.data).toBeNull();
    });
  });
});
