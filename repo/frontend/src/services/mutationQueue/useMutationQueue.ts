/* ------------------------------------------------------------------ */
/*  useMutationQueue — React hook for the offline mutation queue       */
/* ------------------------------------------------------------------ */

import { useEffect, useRef, useState, useCallback, useMemo } from 'react';
import { MutationQueue } from './MutationQueue';
import { MutationReplay } from './MutationReplay';
import { useConnectivityStore } from '@/stores/connectivityStore';
import apiClient from '@/api/client';

const REPLAY_INTERVAL_MS = 30_000;

export interface UseMutationQueueReturn {
  enqueue: (
    entityType: string,
    entityId: string | null,
    operation: 'CREATE' | 'UPDATE' | 'DELETE',
    payload: unknown,
  ) => Promise<string>;
  queueSize: number;
  isReplaying: boolean;
}

export function useMutationQueue(): UseMutationQueueReturn {
  const queueRef = useRef<MutationQueue | null>(null);
  const replayRef = useRef<MutationReplay | null>(null);
  const [queueSize, setQueueSize] = useState(0);
  const [isReplaying, setIsReplaying] = useState(false);

  const isOnline = useConnectivityStore((s) => s.isOnline);
  const isBackendReachable = useConnectivityStore((s) => s.isBackendReachable);

  // Lazily initialise queue + replay
  if (!queueRef.current) {
    queueRef.current = new MutationQueue();
  }
  if (!replayRef.current) {
    replayRef.current = new MutationReplay(apiClient, queueRef.current);
  }

  const refreshSize = useCallback(async () => {
    const size = await queueRef.current!.getQueueSize();
    setQueueSize(size);
  }, []);

  const doReplay = useCallback(async () => {
    if (!replayRef.current) return;
    setIsReplaying(true);
    try {
      await replayRef.current.replay();
    } finally {
      setIsReplaying(false);
      await refreshSize();
    }
  }, [refreshSize]);

  // Initial size check
  useEffect(() => {
    refreshSize();
  }, [refreshSize]);

  // When connectivity is restored, trigger replay
  const prevConnected = useRef(false);
  useEffect(() => {
    const connected = isOnline && isBackendReachable;
    if (connected && !prevConnected.current) {
      doReplay();
    }
    prevConnected.current = connected;
  }, [isOnline, isBackendReachable, doReplay]);

  // Periodic replay while there are items in the queue
  useEffect(() => {
    if (!isOnline || !isBackendReachable) return;

    const interval = setInterval(() => {
      if (queueSize > 0) {
        doReplay();
      }
    }, REPLAY_INTERVAL_MS);

    return () => clearInterval(interval);
  }, [isOnline, isBackendReachable, queueSize, doReplay]);

  const enqueue = useCallback(
    async (
      entityType: string,
      entityId: string | null,
      operation: 'CREATE' | 'UPDATE' | 'DELETE',
      payload: unknown,
    ): Promise<string> => {
      const id = await queueRef.current!.enqueue(entityType, entityId, operation, payload);
      await refreshSize();
      return id;
    },
    [refreshSize],
  );

  return useMemo(
    () => ({ enqueue, queueSize, isReplaying }),
    [enqueue, queueSize, isReplaying],
  );
}
