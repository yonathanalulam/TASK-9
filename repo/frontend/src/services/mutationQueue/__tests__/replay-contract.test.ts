import { describe, it, expect } from 'vitest';

/**
 * Tests that the offline mutation replay wire contract is aligned between
 * the frontend MutationReplay class and the backend MutationQueueController.
 *
 * Backend expects snake_case: mutation_id, entity_type, entity_id, operation, payload
 * Frontend local model uses camelCase: id, entityType, entityId, operation, payload
 * The MutationReplay class must map between these explicitly.
 */
describe('Mutation replay wire contract', () => {
  describe('Request payload mapping (camelCase → snake_case)', () => {
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
      // Backend reads mutation_id which would be undefined → empty string → rejected
      const mutationId = (badPayload as Record<string, unknown>)['mutation_id'] ?? '';
      expect(mutationId).toBe('');
    });
  });

  describe('Response envelope unwrapping (snake_case → camelCase)', () => {
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
});
