/** Zustand グローバルストア（認証 & UI） */
import { create } from 'zustand';

interface AuthState {
  token: string | null;
  setToken: (t: string | null) => void;
  loading: boolean;
  setLoading: (v: boolean) => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  token: null,
  setToken: (token) => set({ token }),
  loading: false,
  setLoading: (loading) => set({ loading }),
}));
