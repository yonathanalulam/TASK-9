import { create } from 'zustand';
import axios from 'axios';

interface ConnectivityState {
  isOnline: boolean;
  isBackendReachable: boolean;
  lastCheckedAt: string | null;
  setOnline: (v: boolean) => void;
  setBackendReachable: (v: boolean) => void;
  startListening: () => () => void;
}

const HEALTH_URL =
  (import.meta.env.VITE_API_URL || 'http://localhost:8080/api/v1') + '/health';
const PING_INTERVAL = 30_000;

export const useConnectivityStore = create<ConnectivityState>()((set) => ({
  isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
  isBackendReachable: true,
  lastCheckedAt: null,

  setOnline: (v) => set({ isOnline: v }),
  setBackendReachable: (v) => set({ isBackendReachable: v, lastCheckedAt: new Date().toISOString() }),

  startListening: () => {
    const handleOnline = () => set({ isOnline: true });
    const handleOffline = () => set({ isOnline: false });

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Periodic health check
    const checkHealth = async () => {
      try {
        await axios.get(HEALTH_URL, { timeout: 5000 });
        set({ isBackendReachable: true, lastCheckedAt: new Date().toISOString() });
      } catch {
        set({ isBackendReachable: false, lastCheckedAt: new Date().toISOString() });
      }
    };

    checkHealth();
    const intervalId = setInterval(checkHealth, PING_INTERVAL);

    // Return cleanup function
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      clearInterval(intervalId);
    };
  },
}));
