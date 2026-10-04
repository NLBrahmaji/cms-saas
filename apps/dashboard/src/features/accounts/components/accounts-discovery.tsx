"use client";

import { listAccounts } from "@/features/accounts/api/list-accounts";
import { DashboardPageHeader } from "@/features/accounts/components/dashboard-page-header";
import type { AccessibleAccount } from "@/features/accounts/types";
import { useAuth } from "@/features/auth/components/auth-provider";
import { AuthLoadingScreen } from "@/features/auth/components/auth-loading-screen";
import { authFormErrorMessage } from "@/features/auth/utils/auth-form-errors";
import { ApiError } from "@/lib/api/errors";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

type DiscoveryPhase = "loading" | "empty" | "picker" | "redirecting" | "error";

export function AccountsDiscovery() {
  const router = useRouter();
  const { status } = useAuth();
  const [phase, setPhase] = useState<DiscoveryPhase>("loading");
  const [accounts, setAccounts] = useState<AccessibleAccount[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (status === "loading") {
      return;
    }

    if (status === "unauthenticated") {
      router.replace("/login");
      return;
    }

    let cancelled = false;

    async function discover() {
      setPhase("loading");
      setErrorMessage(null);

      try {
        const response = await listAccounts();

        if (cancelled) {
          return;
        }

        const items = response.data;

        if (items.length === 0) {
          setAccounts([]);
          setPhase("empty");
          return;
        }

        if (items.length === 1) {
          setPhase("redirecting");
          router.replace(`/accounts/${items[0].id}/websites`);
          return;
        }

        setAccounts(items);
        setPhase("picker");
      } catch (error) {
        if (cancelled) {
          return;
        }

        if (error instanceof ApiError && error.isUnauthorized()) {
          router.replace("/login");
          return;
        }

        setErrorMessage(
          authFormErrorMessage(error, "Unable to load your accounts."),
        );
        setPhase("error");
      }
    }

    void discover();

    return () => {
      cancelled = true;
    };
  }, [status, router]);

  if (status === "loading" || phase === "loading" || phase === "redirecting") {
    return (
      <AuthLoadingScreen
        message={
          phase === "redirecting" ? "Opening your account…" : "Loading accounts…"
        }
      />
    );
  }

  if (status === "unauthenticated") {
    return null;
  }

  return (
    <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950">
      <DashboardPageHeader title="Accounts" />

      <main className="mx-auto max-w-5xl px-6 py-10">
        {phase === "error" && errorMessage ? (
          <section
            className="rounded-2xl border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-950/40"
            role="alert"
          >
            <p className="text-sm text-red-800 dark:text-red-200">{errorMessage}</p>
          </section>
        ) : null}

        {phase === "empty" ? (
          <section className="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-50">
              No accounts available
            </h2>
            <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
              No accounts are available for this user.
            </p>
          </section>
        ) : null}

        {phase === "picker" ? (
          <section className="space-y-4">
            <div>
              <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                Choose an account
              </h2>
              <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Select which account you want to open.
              </p>
            </div>

            <ul className="space-y-3">
              {accounts.map((account) => (
                <li
                  key={account.id}
                  className="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div>
                    <p className="font-medium text-zinc-900 dark:text-zinc-50">
                      {account.name}
                    </p>
                    <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                      {account.role ? `Role: ${account.role}` : "Role unavailable"}
                      {" · "}
                      Status: {account.status}
                    </p>
                  </div>
                  <Link
                    href={`/accounts/${account.id}/websites`}
                    className="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                  >
                    Open
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </main>
    </div>
  );
}
