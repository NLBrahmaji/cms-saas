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
import type { LoginCredentials } from "@/features/auth/api/login";
import type { AuthenticatedUser } from "@/features/auth/types";
import { ApiError } from "@/lib/api/errors";
import { resetCsrfCookieState } from "@/lib/api/csrf";

type AuthStatus = "loading" | "authenticated" | "unauthenticated";

type AuthContextValue = {
  status: AuthStatus;
  user: AuthenticatedUser | null;
  login: (credentials: LoginCredentials) => Promise<void>;
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
      logout,
      refreshUser,
    }),
    [status, user, login, logout, refreshUser],
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
  if (error instanceof ApiError) {
    if (error.isValidationError()) {
      return "Unable to sign in with the provided credentials.";
    }

    if (error.status === 0) {
      return "Unable to reach the server. Check that the API is running.";
    }

    if (error.status === 419) {
      return "Your session could not be verified. Refresh the page and try again.";
    }

    if (error.status === 401) {
      return "Unable to sign in with the provided credentials.";
    }

    return "Unable to sign in. Please try again.";
  }

  if (error instanceof Error && error.message.includes("NEXT_PUBLIC_API_URL")) {
    return error.message;
  }

  return "Unable to sign in. Please try again.";
}
