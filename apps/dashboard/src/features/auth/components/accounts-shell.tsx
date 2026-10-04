"use client";

import { useAuth } from "@/features/auth/components/auth-provider";
import { AuthLoadingScreen } from "@/features/auth/components/auth-loading-screen";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

export function AccountsShell() {
  const router = useRouter();
  const { status, user, logout } = useAuth();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [status, router]);

  if (status === "loading") {
    return <AuthLoadingScreen />;
  }

  if (status === "unauthenticated" || !user) {
    return null;
  }

  async function handleLogout() {
    setIsLoggingOut(true);

    try {
      await logout();
      router.replace("/login");
    } finally {
      setIsLoggingOut(false);
    }
  }

  return (
    <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950">
      <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <div>
            <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">
              SitePro Dashboard
            </p>
            <h1 className="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
              Accounts
            </h1>
          </div>
          <button
            type="button"
            onClick={() => void handleLogout()}
            disabled={isLoggingOut}
            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-800 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
          >
            {isLoggingOut ? "Signing out…" : "Sign out"}
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-6 py-10">
        <section className="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
          <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-50">
            Signed in
          </h2>
          <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            You are authenticated with the Laravel session.
          </p>
          <dl className="mt-6 space-y-3 text-sm">
            <div>
              <dt className="font-medium text-zinc-500 dark:text-zinc-400">Name</dt>
              <dd className="text-zinc-900 dark:text-zinc-50">{user.name}</dd>
            </div>
            <div>
              <dt className="font-medium text-zinc-500 dark:text-zinc-400">Email</dt>
              <dd className="text-zinc-900 dark:text-zinc-50">{user.email}</dd>
            </div>
          </dl>
          <p className="mt-6 text-sm text-zinc-500 dark:text-zinc-400">
            Account discovery and My Websites will be added in the next dashboard slices.
          </p>
        </section>
      </main>
    </div>
  );
}
