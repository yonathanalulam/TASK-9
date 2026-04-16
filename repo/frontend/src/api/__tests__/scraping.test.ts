import { vi, describe, it, expect, beforeEach } from 'vitest';

/* ------------------------------------------------------------------ */
/*  Mock the API client                                                */
/* ------------------------------------------------------------------ */
const { mockGet, mockPost, mockPut } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
}));
vi.mock('../client', () => ({
  default: { get: mockGet, post: mockPost, put: mockPut },
}));

import {
  listSources,
  getSource,
  createSource,
  updateSource,
  pauseSource,
  resumeSource,
  disableSource,
  getSourceHealth,
  getHealthDashboard,
  listScrapeRuns,
  getScrapeRun,
  triggerScrape,
} from '../scraping';

describe('Scraping API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================ */
  /*  Source CRUD                                                       */
  /* ================================================================ */

  describe('listSources', () => {
    it('calls GET /sources with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listSources();

      expect(mockGet).toHaveBeenCalledWith('/sources', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listSources({ page: 2, per_page: 10 });

      expect(mockGet).toHaveBeenCalledWith('/sources', {
        params: { page: 2, per_page: 10 },
      });
    });
  });

  describe('getSource', () => {
    it('calls GET /sources/:id', async () => {
      const source = {
        id: 'src-1',
        name: 'Test Source',
        base_url: 'https://example.com',
        type: 'web',
        status: 'active',
        rate_limit: 10,
        schedule: null,
        config: {},
        last_scrape_at: null,
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      };
      const envelope = { data: source, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getSource('src-1');

      expect(mockGet).toHaveBeenCalledWith('/sources/src-1');
      expect(result.data.name).toBe('Test Source');
    });
  });

  describe('createSource', () => {
    it('calls POST /sources with payload', async () => {
      const source = {
        id: 'src-new',
        name: 'New Source',
        base_url: 'https://new.example.com',
        type: 'api',
        status: 'active',
        rate_limit: 5,
        schedule: null,
        config: {},
        last_scrape_at: null,
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      };
      const envelope = { data: source, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { name: 'New Source', base_url: 'https://new.example.com', type: 'api' };
      const result = await createSource(payload);

      expect(mockPost).toHaveBeenCalledWith('/sources', payload);
      expect(result.data.id).toBe('src-new');
    });
  });

  describe('updateSource', () => {
    it('calls PUT /sources/:id with payload', async () => {
      const source = {
        id: 'src-1',
        name: 'Updated',
        base_url: 'https://example.com',
        type: 'web',
        status: 'active',
        rate_limit: 20,
        schedule: null,
        config: {},
        last_scrape_at: null,
        created_at: '2026-01-01',
        updated_at: '2026-01-02',
      };
      const envelope = { data: source, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const payload = { name: 'Updated', rate_limit: 20 };
      const result = await updateSource('src-1', payload);

      expect(mockPut).toHaveBeenCalledWith('/sources/src-1', payload);
      expect(result.data.name).toBe('Updated');
    });
  });

  /* ================================================================ */
  /*  Source actions                                                    */
  /* ================================================================ */

  describe('pauseSource', () => {
    it('calls POST /sources/:id/pause', async () => {
      const source = { id: 'src-1', status: 'paused' };
      const envelope = { data: source, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await pauseSource('src-1');

      expect(mockPost).toHaveBeenCalledWith('/sources/src-1/pause');
      expect(result.data.status).toBe('paused');
    });
  });

  describe('resumeSource', () => {
    it('calls POST /sources/:id/resume', async () => {
      const source = { id: 'src-1', status: 'active' };
      const envelope = { data: source, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await resumeSource('src-1');

      expect(mockPost).toHaveBeenCalledWith('/sources/src-1/resume');
      expect(result.data.status).toBe('active');
    });
  });

  describe('disableSource', () => {
    it('calls POST /sources/:id/disable', async () => {
      const source = { id: 'src-1', status: 'disabled' };
      const envelope = { data: source, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await disableSource('src-1');

      expect(mockPost).toHaveBeenCalledWith('/sources/src-1/disable');
      expect(result.data.status).toBe('disabled');
    });
  });

  /* ================================================================ */
  /*  Health                                                           */
  /* ================================================================ */

  describe('getSourceHealth', () => {
    it('calls GET /sources/:id/health', async () => {
      const health = {
        source_id: 'src-1',
        source_name: 'Test',
        status: 'healthy',
        uptime: 99.9,
        avg_response_ms: 200,
        error_rate: 0.01,
        recent_events: [],
      };
      const envelope = { data: health, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getSourceHealth('src-1');

      expect(mockGet).toHaveBeenCalledWith('/sources/src-1/health');
      expect(result.data.source_id).toBe('src-1');
    });
  });

  describe('getHealthDashboard', () => {
    it('calls GET /sources/health/dashboard', async () => {
      const dashboard = {
        active: 5,
        degraded: 1,
        paused: 0,
        disabled: 0,
        sources: [],
        recent_events: [],
      };
      const envelope = { data: dashboard, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getHealthDashboard();

      expect(mockGet).toHaveBeenCalledWith('/sources/health/dashboard');
      expect(result.data.active).toBe(5);
    });
  });

  /* ================================================================ */
  /*  Scrape runs                                                      */
  /* ================================================================ */

  describe('listScrapeRuns', () => {
    it('calls GET /scrape-runs with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listScrapeRuns();

      expect(mockGet).toHaveBeenCalledWith('/scrape-runs', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards pagination params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listScrapeRuns({ page: 3, per_page: 5 });

      expect(mockGet).toHaveBeenCalledWith('/scrape-runs', {
        params: { page: 3, per_page: 5 },
      });
    });
  });

  describe('getScrapeRun', () => {
    it('calls GET /scrape-runs/:id', async () => {
      const run = {
        id: 'run-1',
        source_id: 'src-1',
        source_name: 'Test',
        status: 'completed',
        items_found: 100,
        items_new: 50,
        items_updated: 30,
        items_failed: 0,
        started_at: '2026-01-01T00:00:00Z',
        completed_at: '2026-01-01T00:01:00Z',
        duration_ms: 60000,
        error: null,
        created_at: '2026-01-01',
      };
      const envelope = { data: run, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getScrapeRun('run-1');

      expect(mockGet).toHaveBeenCalledWith('/scrape-runs/run-1');
      expect(result.data.items_found).toBe(100);
    });
  });

  describe('triggerScrape', () => {
    it('calls POST /scrape-runs/trigger/:sourceId', async () => {
      const run = {
        id: 'run-new',
        source_id: 'src-1',
        source_name: 'Test',
        status: 'running',
        items_found: 0,
        items_new: 0,
        items_updated: 0,
        items_failed: 0,
        started_at: '2026-01-01T00:00:00Z',
        completed_at: null,
        duration_ms: null,
        error: null,
        created_at: '2026-01-01',
      };
      const envelope = { data: run, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await triggerScrape('src-1');

      expect(mockPost).toHaveBeenCalledWith('/scrape-runs/trigger/src-1');
      expect(result.data.status).toBe('running');
    });
  });
});
