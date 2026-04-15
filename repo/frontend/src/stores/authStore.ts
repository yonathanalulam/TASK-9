import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User, RoleAssignment } from '@/api/types';

interface AuthState {
  token: string | null;
  user: User | null;
  roles: RoleAssignment[];
  isAuthenticated: boolean;
  setAuth: (token: string, user: User, roles: RoleAssignment[]) => void;
  clearAuth: () => void;
  initialize: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      roles: [],
      isAuthenticated: false,

      setAuth: (token: string, user: User, roles: RoleAssignment[]) => {
        set({ token, user, roles, isAuthenticated: true });
      },

      clearAuth: () => {
        set({ token: null, user: null, roles: [], isAuthenticated: false });
      },

      initialize: () => {
        // The persist middleware automatically rehydrates from localStorage.
        // This method allows eager initialisation in main.tsx if needed.
        const { token, user } = get();
        if (token && user) {
          set({ isAuthenticated: true });
        }
      },
    }),
    {
      name: 'meridian-auth',
      partialize: (state) => ({ token: state.token, user: state.user, roles: state.roles }),
    },
  ),
);
