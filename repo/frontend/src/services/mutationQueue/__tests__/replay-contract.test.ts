import { vi, describe, it, expect, beforeEach } from 'vitest';
import type { QueuedMutation } from '../types';

/* ------------------------------------------------------------------ */
/*  Mock the MutationQueue so replay() can dequeue/mark mutations      */
/* ------------------------------------------------------------------ */
const mockDequeueReady = vi.fn();
const mockMarkSucceeded = vi.fn();
const mockMarkFailed = vi.fn();

vi.mock('../MutationQueue', () => ({
  MutationQueue: vi.fn().mockImplementation(() => ({
    dequeueReady: mockDequeueReady,
    markSucceeded: mockMarkSucceeded,
    markFailed: mockMarkFailed,
  })),
}));

import { MutationReplay } from '../MutationReplay';
import { MutationQueue } from '../MutationQueue';

/**
 * Tests that the offline mutation replay wire contract is aligned between
 * the frontend MutationReplay class and the backend MutationQueueController.
 *
 * Backend expects snake_case: mutation_id, entity_type, entity_id, operation, payload
 * Frontend local model uses camelCase: id, entityType, entityId, operation, payload
 * The MutationReplay class must map between these explicitly.
 */
describe('Mutation replay wire contract', () => {
  let mockAxios: { post: ReturnType<typeof vi.fn> };
  let replay: MutationReplay;

  const sampleMutation: QueuedMutation = {
    id: 'local-uuid-1',
    entityType: 'store',
    entityId: null,
    operation: 'CREATE',
    payload: { code: 'STORE-001', name: 'Test Store' },
    createdAt: Date.now(),
    retryCount: 0,
    nextRetryAt: 0,
    lastError: null,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    mockAxios = { post: vi.fn() };
    const queue = new MutationQueue() as unknown as MutationQueue;
    replay = new MutationReplay(mockAxios as never, queue);
  });

  /* ================================================================== */
  /*  Original contract shape assertions (preserved)                     */
  /* ================================================================== */

  describe('Request payload mapping (camelCase -> snake_case)', () => {
    it('maps local queue id to wire mutation_id', () => {
      // Simulate the toWirePayload mapping
      const local = { id: 'local-uuid-1', entityType: 'store', entityId: null, operation: 'CREATE', payload: {} };
      const wire = {
        mutation_id: local.id,
        entity_type: local.entityType,
        entity_id: local.entityId,
        operation: local.operation,
        payload: local.payload,
      };
      expect(wire.mutation_id).toBe('local-uuid-1');
      expect(wire.entity_type).toBe('store');
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((wire as any).id).toBeUndefined();
      expect((wire as any).entityType).toBeUndefined();
    });

    it('sends snake_case keys that backend expects', () => {
      const wirePayload = {
        mutation_id: 'uuid',
        client_id: 'client',
        entity_type: 'store',
        entity_id: null,
        operation: 'CREATE',
        payload: { code: 'STORE-001' },
      };
      const expectedKeys = ['mutation_id', 'client_id', 'entity_type', 'entity_id', 'operation', 'payload'];
      for (const key of expectedKeys) {
        expect(wirePayload).toHaveProperty(key);
      }
    });

    it('rejects sending camelCase keys directly', () => {
      const badPayload = {
        id: 'uuid',
        entityType: 'store',
        entityId: null,
        operation: 'CREATE',
        payload: {},
      };
      // Backend reads mutation_id which would be undefined -> empty string -> rejected
      const mutationId = (badPayload as Record<string, unknown>)['mutation_id'] ?? '';
      expect(mutationId).toBe('');
    });
  });

  describe('Response envelope unwrapping (snake_case -> camelCase)', () => {
    it('unwraps ApiEnvelope to get result array', () => {
      // Backend returns { data: [...], meta: {...}, error: null }
      const envelope = {
        data: [{ mutation_id: 'uuid-1', status: 'APPLIED' }],
        meta: { request_id: 'req-1', timestamp: '2026-01-01' },
        error: null,
      };
      // Frontend must read envelope.data (not envelope directly)
      const results = envelope.data;
      expect(results).toHaveLength(1);
      expect(results[0].mutation_id).toBe('uuid-1');
    });

    it('maps wire mutation_id back to camelCase mutationId', () => {
      const wireResult = { mutation_id: 'uuid-1', status: 'APPLIED' as const };
      const mapped = {
        mutationId: wireResult.mutation_id,
        status: wireResult.status,
        detail: null,
      };
      expect(mapped.mutationId).toBe('uuid-1');
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      expect((mapped as any).mutation_id).toBeUndefined();
    });
  });

  /* ================================================================== */
  /*  Behavior tests — call real MutationReplay with mocked HTTP         */
  /* ================================================================== */

  describe('replay() sends correct wire format', () => {
    it('transforms camelCase mutations to snake_case and POSTs to /mutations/replay', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);

      const wireResponse = {
        data: {
          data: [{ mutation_id: 'local-uuid-1', status: 'APPLIED' }],
          meta: { request_id: 'req-1', timestamp: '2026-01-01' },
          error: null,
        },
      };
      mockAxios.post.mockResolvedValueOnce(wireResponse);

      await replay.replay();

      expect(mockAxios.post).toHaveBeenCalledWith('/mutations/replay', {
        mutations: [
          {
            mutation_id: 'local-uuid-1',
            client_id: 'local-uuid-1',
            entity_type: 'store',
            entity_id: null,
            operation: 'CREATE',
            payload: { code: 'STORE-001', name: 'Test Store' },
          },
        ],
      });
    });

    it('does NOT send camelCase keys in the wire payload', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);

      const wireResponse = {
        data: {
          data: [{ mutation_id: 'local-uuid-1', status: 'APPLIED' }],
          meta: { request_id: 'req-1', timestamp: '2026-01-01' },
          error: null,
        },
      };
      mockAxios.post.mockResolvedValueOnce(wireResponse);

      await replay.replay();

      const sentBody = mockAxios.post.mock.calls[0][1];
      const sentMutation = sentBody.mutations[0];
      expect(sentMutation).toHaveProperty('mutation_id');
      expect(sentMutation).toHaveProperty('entity_type');
      expect(sentMutation).toHaveProperty('entity_id');
      expect(sentMutation).not.toHaveProperty('id');
      expect(sentMutation).not.toHaveProperty('entityType');
      expect(sentMutation).not.toHaveProperty('entityId');
    });
  });

  describe('replay() processes APPLIED results', () => {
    it('marks APPLIED mutations as succeeded', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockResolvedValueOnce({
        data: {
          data: [{ mutation_id: 'local-uuid-1', status: 'APPLIED' }],
          meta: { request_id: 'req-1', timestamp: '2026-01-01' },
          error: null,
        },
      });

      await replay.replay();

      expect(mockMarkSucceeded).toHaveBeenCalledWith('local-uuid-1');
      expect(mockMarkFailed).not.toHaveBeenCalled();
    });
  });

  describe('replay() processes CONFLICT results', () => {
    it('marks CONFLICT mutations as failed with retryable=true', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockResolvedValueOnce({
        data: {
          data: [{ mutation_id: 'local-uuid-1', status: 'CONFLICT', detail: 'Version mismatch' }],
          meta: { request_id: 'req-2', timestamp: '2026-01-01' },
          error: null,
        },
      });

      await replay.replay();

      expect(mockMarkFailed).toHaveBeenCalledWith('local-uuid-1', 'Version mismatch', true);
      expect(mockMarkSucceeded).not.toHaveBeenCalled();
    });
  });

  describe('replay() processes REJECTED results', () => {
    it('marks REJECTED mutations as failed with retryable=false', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockResolvedValueOnce({
        data: {
          data: [{ mutation_id: 'local-uuid-1', status: 'REJECTED', detail: 'Invalid payload' }],
          meta: { request_id: 'req-3', timestamp: '2026-01-01' },
          error: null,
        },
      });

      await replay.replay();

      expect(mockMarkFailed).toHaveBeenCalledWith('local-uuid-1', 'Invalid payload', false);
      expect(mockMarkSucceeded).not.toHaveBeenCalled();
    });
  });

  describe('replay() handles network errors', () => {
    it('marks all mutations as failed with retryable=true on network error', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockRejectedValueOnce({ message: 'Network Error' });

      await replay.replay();

      expect(mockMarkFailed).toHaveBeenCalledWith('local-uuid-1', 'Network Error', true);
    });

    it('marks as retryable on 429 Too Many Requests', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockRejectedValueOnce({
        message: 'Too Many Requests',
        response: { status: 429, data: {} },
      });

      await replay.replay();

      expect(mockMarkFailed).toHaveBeenCalledWith('local-uuid-1', 'Too Many Requests', true);
    });

    it('marks as retryable on 503 Service Unavailable', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockRejectedValueOnce({
        message: 'Service Unavailable',
        response: { status: 503, data: {} },
      });

      await replay.replay();

      expect(mockMarkFailed).toHaveBeenCalledWith('local-uuid-1', 'Service Unavailable', true);
    });

    it('marks as NOT retryable on 400 Bad Request', async () => {
      mockDequeueReady.mockResolvedValueOnce([sampleMutation]);
      mockAxios.post.mockRejectedValueOnce({
        message: 'Bad Request',
        response: { status: 400, data: {} },
      });

      await replay.replay();

      expect(mockMarkFailed).toHaveBeenCalledWith('local-uuid-1', 'Bad Request', false);
    });
  });

  describe('replay() with empty queue', () => {
    it('does not call the API when there are no queued mutations', async () => {
      mockDequeueReady.mockResolvedValueOnce([]);

      await replay.replay();

      expect(mockAxios.post).not.toHaveBeenCalled();
      expect(mockMarkSucceeded).not.toHaveBeenCalled();
      expect(mockMarkFailed).not.toHaveBeenCalled();
    });
  });
});
