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
  listClassifications,
  createClassification,
  updateClassification,
  createConsent,
  getUserConsent,
  listRetentionCases,
  scheduleRetention,
  getRetentionStats,
} from '../governance';

describe('Governance API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  /* ================================================================ */
  /*  Classifications                                                  */
  /* ================================================================ */

  describe('listClassifications', () => {
    it('calls GET /classifications with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listClassifications();

      expect(mockGet).toHaveBeenCalledWith('/classifications', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards query params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listClassifications({ page: 2, entity_type: 'content' });

      expect(mockGet).toHaveBeenCalledWith('/classifications', {
        params: { page: 2, entity_type: 'content' },
      });
    });
  });

  describe('createClassification', () => {
    it('calls POST /classifications with payload', async () => {
      const classification = {
        id: 'cls-1',
        entity_type: 'content',
        entity_id: 'c-1',
        entity_name: 'Article',
        classification: 'PII',
        justification: null,
        classified_by: 'user-1',
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      };
      const envelope = { data: classification, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { entity_type: 'content', entity_id: 'c-1', classification: 'PII' };
      const result = await createClassification(payload);

      expect(mockPost).toHaveBeenCalledWith('/classifications', payload);
      expect(result.data.id).toBe('cls-1');
    });
  });

  describe('updateClassification', () => {
    it('calls PUT /classifications/:id with partial data', async () => {
      const updated = {
        id: 'cls-1',
        entity_type: 'content',
        entity_id: 'c-1',
        entity_name: 'Article',
        classification: 'SENSITIVE',
        justification: 'Updated',
        classified_by: 'user-1',
        created_at: '2026-01-01',
        updated_at: '2026-01-02',
      };
      const envelope = { data: updated, meta: {}, error: null };
      mockPut.mockResolvedValueOnce({ data: envelope });

      const result = await updateClassification('cls-1', { classification: 'SENSITIVE' });

      expect(mockPut).toHaveBeenCalledWith('/classifications/cls-1', { classification: 'SENSITIVE' });
      expect(result.data.classification).toBe('SENSITIVE');
    });
  });

  /* ================================================================ */
  /*  Consent                                                          */
  /* ================================================================ */

  describe('createConsent', () => {
    it('calls POST /consent with payload', async () => {
      const consent = {
        id: 'con-1',
        user_id: 'u-1',
        user_name: 'Alice',
        purpose: 'marketing',
        status: 'active',
        granted_at: '2026-01-01',
        revoked_at: null,
        expires_at: null,
        created_at: '2026-01-01',
      };
      const envelope = { data: consent, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const payload = { user_id: 'u-1', purpose: 'marketing' };
      const result = await createConsent(payload);

      expect(mockPost).toHaveBeenCalledWith('/consent', payload);
      expect(result.data.id).toBe('con-1');
    });
  });

  describe('getUserConsent', () => {
    it('calls GET /consent/user/:userId', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getUserConsent('u-1');

      expect(mockGet).toHaveBeenCalledWith('/consent/user/u-1');
      expect(result.data).toEqual([]);
    });
  });

  /* ================================================================ */
  /*  Retention                                                        */
  /* ================================================================ */

  describe('listRetentionCases', () => {
    it('calls GET /retention/cases with default empty params', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await listRetentionCases();

      expect(mockGet).toHaveBeenCalledWith('/retention/cases', { params: {} });
      expect(result.data).toEqual([]);
    });

    it('forwards status filter', async () => {
      const envelope = { data: [], meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      await listRetentionCases({ status: 'pending' });

      expect(mockGet).toHaveBeenCalledWith('/retention/cases', {
        params: { status: 'pending' },
      });
    });
  });

  describe('scheduleRetention', () => {
    it('calls POST /retention/cases/:id/schedule', async () => {
      const retentionCase = {
        id: 'ret-1',
        entity_type: 'user',
        entity_id: 'u-1',
        entity_name: 'Alice',
        reason: 'GDPR request',
        status: 'scheduled',
        scheduled_for: '2026-02-01',
        executed_at: null,
        executed_by: null,
        created_by: 'admin',
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
      };
      const envelope = { data: retentionCase, meta: {}, error: null };
      mockPost.mockResolvedValueOnce({ data: envelope });

      const result = await scheduleRetention('ret-1');

      expect(mockPost).toHaveBeenCalledWith('/retention/cases/ret-1/schedule');
      expect(result.data.status).toBe('scheduled');
    });
  });

  describe('getRetentionStats', () => {
    it('calls GET /retention/stats', async () => {
      const stats = {
        total_cases: 10,
        pending_count: 3,
        scheduled_count: 2,
        executing_count: 1,
        completed_count: 3,
        failed_count: 1,
        next_scheduled_deletion: '2026-02-01',
      };
      const envelope = { data: stats, meta: {}, error: null };
      mockGet.mockResolvedValueOnce({ data: envelope });

      const result = await getRetentionStats();

      expect(mockGet).toHaveBeenCalledWith('/retention/stats');
      expect(result.data.total_cases).toBe(10);
      expect(result.data.pending_count).toBe(3);
    });
  });
});
