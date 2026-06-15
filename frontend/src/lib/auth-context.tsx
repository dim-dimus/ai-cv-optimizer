"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import * as authApi from "@/lib/auth-api";
import type { AuthResponse, LoginPayload, RegisterPayload, User } from "@/types/api";

const TOKEN_STORAGE_KEY = "ai-cv-optimizer.token";

interface AuthContextValue {
  user: User | null;
  token: string | null;
  status: "loading" | "authenticated" | "unauthenticated";
  login: (payload: LoginPayload) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [status, setStatus] = useState<AuthContextValue["status"]>("loading");

  // On mount, restore a persisted token and validate it against /me.
  useEffect(() => {
    let cancelled = false;

    void (async () => {
      const stored = window.localStorage.getItem(TOKEN_STORAGE_KEY);
      if (!stored) {
        if (!cancelled) setStatus("unauthenticated");
        return;
      }

      try {
        const me = await authApi.fetchMe(stored);
        if (cancelled) return;
        setUser(me);
        setToken(stored);
        setStatus("authenticated");
      } catch {
        if (cancelled) return;
        window.localStorage.removeItem(TOKEN_STORAGE_KEY);
        setStatus("unauthenticated");
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const applySession = useCallback((res: AuthResponse) => {
    window.localStorage.setItem(TOKEN_STORAGE_KEY, res.token);
    setUser(res.user);
    setToken(res.token);
    setStatus("authenticated");
  }, []);

  const login = useCallback(
    async (payload: LoginPayload) => {
      applySession(await authApi.login(payload));
    },
    [applySession],
  );

  const register = useCallback(
    async (payload: RegisterPayload) => {
      applySession(await authApi.register(payload));
    },
    [applySession],
  );

  const logout = useCallback(async () => {
    if (token) {
      await authApi.logout(token).catch(() => {
        // Even if the server call fails, clear the local session.
      });
    }
    window.localStorage.removeItem(TOKEN_STORAGE_KEY);
    setUser(null);
    setToken(null);
    setStatus("unauthenticated");
  }, [token]);

  const value = useMemo<AuthContextValue>(
    () => ({ user, token, status, login, register, logout }),
    [user, token, status, login, register, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return ctx;
}
