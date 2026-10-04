"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { getAuthenticatedUser } from "@/features/auth/api/get-authenticated-user";
import { login as loginRequest } from "@/features/auth/api/login";
import { logout as logoutRequest } from "@/features/auth/api/logout";
import { register as registerRequest } from "@/features/auth/api/register";
import type { LoginCredentials } from "@/features/auth/api/login";
import type { RegisterPayload } from "@/features/auth/types";
import type { AuthenticatedUser } from "@/features/auth/types";
import { authFormErrorMessage } from "@/features/auth/utils/auth-form-errors";
import { resetCsrfCookieState } from "@/lib/api/csrf";

type AuthStatus = "loading" | "authenticated" | "unauthenticated";

type AuthContextValue = {
  status: AuthStatus;
  user: AuthenticatedUser | null;
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>("loading");
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const bootstrapStarted = useRef(false);

  const refreshUser = useCallback(async () => {
    const envelope = await getAuthenticatedUser();

    if (envelope?.user) {
      setUser(envelope.user);
      setStatus("authenticated");
      return;
    }

    setUser(null);
    setStatus("unauthenticated");
  }, []);

  useEffect(() => {
    if (bootstrapStarted.current) {
      return;
    }

    bootstrapStarted.current = true;

    void refreshUser().catch(() => {
      setUser(null);
      setStatus("unauthenticated");
    });
  }, [refreshUser]);

  const login = useCallback(async (credentials: LoginCredentials) => {
    const envelope = await loginRequest(credentials);
    setUser(envelope.user);
    setStatus("authenticated");
  }, []);

  const register = useCallback(async (payload: RegisterPayload) => {
    const response = await registerRequest(payload);
    setUser(response.user);
    setStatus("authenticated");
  }, []);

  const logout = useCallback(async () => {
    try {
      await logoutRequest();
    } finally {
      resetCsrfCookieState();
      setUser(null);
      setStatus("unauthenticated");
    }
  }, []);

  const value = useMemo(
    () => ({
      status,
      user,
      login,
      register,
      logout,
      refreshUser,
    }),
    [status, user, login, register, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within AuthProvider.");
  }

  return context;
}

export function loginErrorMessage(error: unknown): string {
  return authFormErrorMessage(
    error,
    "Unable to sign in with the provided credentials.",
  );
}
