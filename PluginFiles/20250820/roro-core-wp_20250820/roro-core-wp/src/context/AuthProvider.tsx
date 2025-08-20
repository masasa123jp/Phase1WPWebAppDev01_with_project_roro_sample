/**
 * JWT + HttpOnly Cookie を想定した認証プロバイダー。
 * ログイン後の `accessToken` はレスポンスヘッダから読み込み、axios interceptors に注入。
 */
import { createContext, useEffect, useState, ReactNode } from 'react';
import axios from 'axios';

export interface User { id: number; name: string; }

export const AuthCtx = createContext<{ user: User | null }>({ user: null });

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);

  useEffect(() => {
    axios.get('/wp-json/roro/v1/me')
      .then(res => setUser(res.data))
      .catch(() => setUser(null));

    axios.interceptors.request.use(cfg => {
      cfg.withCredentials = true; // send cookies
      return cfg;
    });
  }, []);

  return <AuthCtx.Provider value={{ user }}>{children}</AuthCtx.Provider>;
}
