import { useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';
import * as authApi from '@/api/auth';

export function useAuth() {
  const { user, isAuthenticated, setAuth, clearAuth } = useAuthStore();
  const navigate = useNavigate();

  const login = useCallback(
    async (username: string, password: string) => {
      const envelope = await authApi.login(username, password);
      setAuth(envelope.data.token, envelope.data.user, envelope.data.user.roles);
      navigate('/');
    },
    [setAuth, navigate],
  );

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // Even if the server call fails, clear local state
    } finally {
      clearAuth();
      navigate('/login');
    }
  }, [clearAuth, navigate]);

  return { user, isAuthenticated, login, logout };
}
