/* ------------------------------------------------------------------ */
/*  useOptimisticUpdate — optimistic concurrency with If-Match         */
/* ------------------------------------------------------------------ */

import { useCallback } from 'react';
import apiClient from '@/api/client';
import type { AxiosError } from 'axios';

interface ConflictResult {
  conflict: true;
  message: string;
}

interface SuccessResult<T> {
  conflict: false;
  data: T;
}

type UpdateResult<T> = ConflictResult | SuccessResult<T>;

export function useOptimisticUpdate<T = unknown>() {
  const update = useCallback(
    async (url: string, data: unknown, version: number): Promise<UpdateResult<T>> => {
      try {
        const res = await apiClient.put<T>(url, data, {
          headers: { 'If-Match': String(version) },
        });
        return { conflict: false, data: res.data };
      } catch (err: unknown) {
        const axiosErr = err as AxiosError<{ error?: string }>;
        if (axiosErr.response?.status === 409) {
          return {
            conflict: true,
            message:
              axiosErr.response.data?.error ??
              'This record was modified by another user.',
          };
        }
        throw err;
      }
    },
    [],
  );

  return { update };
}
