import { vi, describe, it, expect, beforeEach } from 'vitest';

const { mockGet } = vi.hoisted(() => ({
  mockGet: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet },
}));

import {
  getKpiSummary,
  getSalesByDimensions,
  getSalesTrends,
  getContentVolume,
} from '../analytics';

describe('Analytics API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('getKpiSummary', () => {
    it('calls GET /analytics/kpi-summary', async () => {
      const kpi = {
        total_sales: 50000,
        total_orders: 120,
        content_count: 300,
        export_count: 5,
        retention_count: 2,
        sensitive_access_count: 8,
      };
      const envelope = { data: kpi, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getKpiSummary();

      expect(mockGet).toHaveBeenCalledWith('/analytics/kpi-summary');
      expect(result.data.total_sales).toBe(50000);
    });
  });

  describe('getSalesByDimensions', () => {
    it('calls GET /analytics/sales with params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await getSalesByDimensions({ region: 'US', channel: 'online' });

      expect(mockGet).toHaveBeenCalledWith('/analytics/sales', {
        params: { region: 'US', channel: 'online' },
      });
    });

    it('calls without params when undefined', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await getSalesByDimensions();

      expect(mockGet).toHaveBeenCalledWith('/analytics/sales', {
        params: undefined,
      });
    });
  });

  describe('getSalesTrends', () => {
    it('calls GET /analytics/sales/trends with params', async () => {
      const trends = [{ date: '2026-01-01', gross_sales: 1000, net_sales: 900, quantity: 10 }];
      const envelope = { data: trends, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getSalesTrends({ granularity: 'week' });

      expect(mockGet).toHaveBeenCalledWith('/analytics/sales/trends', {
        params: { granularity: 'week' },
      });
      expect(result.data).toHaveLength(1);
    });
  });

  describe('getContentVolume', () => {
    it('calls GET /analytics/content-volume', async () => {
      const volumes = [{ content_type: 'article', count: 42 }];
      const envelope = { data: volumes, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getContentVolume();

      expect(mockGet).toHaveBeenCalledWith('/analytics/content-volume');
      expect(result.data[0].count).toBe(42);
    });
  });
});
