/* ------------------------------------------------------------------ */
/*  MutationQueue — manages the offline mutation queue lifecycle       */
/* ------------------------------------------------------------------ */

import type { QueuedMutation } from './types';
import {
  addMutation,
  getAllMutations,
  removeMutation,
  updateMutation,
  getCount,
} from './db';

const INITIAL_BACKOFF_MS = 30_000; // 30 seconds
const MAX_BACKOFF_MS = 30 * 60 * 1000; // 30 minutes

/** Simple UUID v4 generator (no dependency). */
function uuid(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

type QueueEventType = 'rejected';
type QueueEventHandler = (mutation: QueuedMutation, error: string) => void;

export class MutationQueue {
  private listeners = new Map<QueueEventType, Set<QueueEventHandler>>();

  /* ---------------------------------------------------------------- */
  /*  Public API                                                       */
  /* ---------------------------------------------------------------- */

  async enqueue(
    entityType: string,
    entityId: string | null,
    operation: QueuedMutation['operation'],
    payload: unknown,
  ): Promise<string> {
    const now = Date.now();
    const mutation: QueuedMutation = {
      id: uuid(),
      entityType,
      entityId,
      operation,
      payload,
      createdAt: now,
      retryCount: 0,
      nextRetryAt: now,
      lastError: null,
    };

    await addMutation(mutation);
    return mutation.id;
  }

  async dequeueReady(): Promise<QueuedMutation[]> {
    const all = await getAllMutations();
    const now = Date.now();
    return all
      .filter((m) => m.nextRetryAt <= now)
      .sort((a, b) => a.createdAt - b.createdAt);
  }

  async markSucceeded(id: string): Promise<void> {
    await removeMutation(id);
  }

  async markFailed(id: string, error: string, isRetryable: boolean): Promise<void> {
    if (!isRetryable) {
      // Retrieve mutation before removing so we can emit event
      const all = await getAllMutations();
      const mutation = all.find((m) => m.id === id);
      await removeMutation(id);
      if (mutation) {
        this.emit('rejected', mutation, error);
      }
      return;
    }

    // Retryable: exponential backoff
    const all = await getAllMutations();
    const mutation = all.find((m) => m.id === id);
    const currentRetry = mutation?.retryCount ?? 0;
    const newRetryCount = currentRetry + 1;
    const backoff = Math.min(INITIAL_BACKOFF_MS * Math.pow(2, currentRetry), MAX_BACKOFF_MS);

    await updateMutation(id, {
      retryCount: newRetryCount,
      nextRetryAt: Date.now() + backoff,
      lastError: error,
    });
  }

  async getQueueSize(): Promise<number> {
    return getCount();
  }

  /* ---------------------------------------------------------------- */
  /*  Event emitter (minimal)                                          */
  /* ---------------------------------------------------------------- */

  on(event: QueueEventType, handler: QueueEventHandler): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event)!.add(handler);
  }

  off(event: QueueEventType, handler: QueueEventHandler): void {
    this.listeners.get(event)?.delete(handler);
  }

  private emit(event: QueueEventType, mutation: QueuedMutation, error: string): void {
    this.listeners.get(event)?.forEach((handler) => handler(mutation, error));
  }
}
