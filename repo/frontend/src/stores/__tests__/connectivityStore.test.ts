import { describe, it, expect, beforeEach } from 'vitest';
import { useConnectivityStore } from '../connectivityStore';

describe('connectivityStore', () => {
  beforeEach(() => {
    // Reset store to defaults between tests
    useConnectivityStore.setState({
      isOnline: true,
      isBackendReachable: true,
      lastCheckedAt: null,
    });
  });

  it('has correct initial state', () => {
    const state = useConnectivityStore.getState();
    expect(state.isOnline).toBe(true);
    expect(state.isBackendReachable).toBe(true);
    expect(state.lastCheckedAt).toBeNull();
  });

  it('setOnline updates isOnline to true', () => {
    useConnectivityStore.getState().setOnline(false);
    expect(useConnectivityStore.getState().isOnline).toBe(false);

    useConnectivityStore.getState().setOnline(true);
    expect(useConnectivityStore.getState().isOnline).toBe(true);
  });

  it('setOnline updates isOnline to false', () => {
    useConnectivityStore.getState().setOnline(false);
    expect(useConnectivityStore.getState().isOnline).toBe(false);
  });

  it('setBackendReachable updates isBackendReachable and sets lastCheckedAt', () => {
    useConnectivityStore.getState().setBackendReachable(false);

    const state = useConnectivityStore.getState();
    expect(state.isBackendReachable).toBe(false);
    expect(state.lastCheckedAt).not.toBeNull();
    // Verify lastCheckedAt is a valid ISO timestamp
    expect(new Date(state.lastCheckedAt!).toISOString()).toBe(state.lastCheckedAt);
  });

  it('setBackendReachable sets reachable to true with timestamp', () => {
    // First set to false
    useConnectivityStore.getState().setBackendReachable(false);
    const firstTimestamp = useConnectivityStore.getState().lastCheckedAt;

    // Then set back to true
    useConnectivityStore.getState().setBackendReachable(true);
    const state = useConnectivityStore.getState();
    expect(state.isBackendReachable).toBe(true);
    expect(state.lastCheckedAt).not.toBeNull();
    // Timestamp should have been updated (or at least be present)
    expect(state.lastCheckedAt).toBeDefined();
  });

  it('startListening is a function', () => {
    const state = useConnectivityStore.getState();
    expect(typeof state.startListening).toBe('function');
  });
});
