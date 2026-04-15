/* ------------------------------------------------------------------ */
/*  MutationReplay — replays queued mutations to the server            */
/* ------------------------------------------------------------------ */

import type { AxiosInstance, AxiosError } from 'axios';
import type { QueuedMutation, MutationResult } from './types';
import { MutationQueue } from './MutationQueue';

/** Status codes that are eligible for retry. */
const RETRYABLE_STATUS_CODES = new Set([429, 503]);

/** Server error code that signals a retryable busy state. */
const BUSY_RETRYABLE = 'BUSY_RETRYABLE';

/** Wire format the backend expects for each mutation (snake_case). */
interface MutationWirePayload {
  mutation_id: string;
  client_id: string;
  entity_type: string;
  entity_id: string | null;
  operation: string;
  payload: unknown;
}

/** Wire format the backend returns per mutation result (snake_case, inside ApiEnvelope). */
interface MutationWireResult {
  mutation_id: string;
  status: 'APPLIED' | 'CONFLICT' | 'REJECTED';
  detail?: string;
}

interface ApiEnvelope<T> {
  data: T;
  meta: Record<string, unknown>;
  error: null;
}

/** Map a frontend QueuedMutation to the backend wire format. */
function toWirePayload(m: QueuedMutation): MutationWirePayload {
  return {
    mutation_id: m.id,
    client_id: m.id,
    entity_type: m.entityType,
    entity_id: m.entityId,
    operation: m.operation,
    payload: m.payload,
  };
}

/** Map a backend wire result to the frontend MutationResult type. */
function fromWireResult(w: MutationWireResult): MutationResult {
  return {
    mutationId: w.mutation_id,
    status: w.status,
    detail: w.detail ?? null,
  };
}

export class MutationReplay {
  private client: AxiosInstance;
  private queue: MutationQueue;

  constructor(client: AxiosInstance, queue: MutationQueue) {
    this.client = client;
    this.queue = queue;
  }

  async replay(): Promise<void> {
    const ready = await this.queue.dequeueReady();
    if (ready.length === 0) return;

    try {
      const wirePayloads = ready.map(toWirePayload);

      const res = await this.client.post<ApiEnvelope<MutationWireResult[]>>(
        '/mutations/replay',
        { mutations: wirePayloads },
      );

      const wireResults: MutationWireResult[] = res.data.data;
      const results: MutationResult[] = wireResults.map(fromWireResult);

      for (const result of results) {
        if (result.status === 'APPLIED') {
          await this.queue.markSucceeded(result.mutationId);
        } else {
          const isRetryable = result.status === 'CONFLICT';
          await this.queue.markFailed(
            result.mutationId,
            result.detail ?? `Mutation ${result.status}`,
            isRetryable,
          );
        }
      }
    } catch (err: unknown) {
      // Network / transport-level failure — mark each mutation individually
      const axiosErr = err as AxiosError<{ code?: string }>;
      const status = axiosErr.response?.status;
      const errorCode = axiosErr.response?.data?.code;
      const isRetryable =
        !status || // network error (no response)
        RETRYABLE_STATUS_CODES.has(status) ||
        errorCode === BUSY_RETRYABLE;

      const errorMessage =
        axiosErr.message || 'Unknown error during mutation replay';

      for (const mutation of ready) {
        await this.queue.markFailed(mutation.id, errorMessage, isRetryable);
      }
    }
  }
}
