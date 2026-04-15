/* ------------------------------------------------------------------ */
/*  Offline Mutation Queue — Types                                     */
/* ------------------------------------------------------------------ */

export interface QueuedMutation {
  id: string;
  entityType: string;
  entityId: string | null;
  operation: 'CREATE' | 'UPDATE' | 'DELETE';
  payload: unknown;
  createdAt: number;
  retryCount: number;
  nextRetryAt: number;
  lastError: string | null;
}

export interface MutationResult {
  mutationId: string;
  status: 'APPLIED' | 'CONFLICT' | 'REJECTED';
  detail: string | null;
}
