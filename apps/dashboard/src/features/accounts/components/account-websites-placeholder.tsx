"use client";

import { getAccount } from "@/features/accounts/api/get-account";
import { DashboardPageHeader } from "@/features/accounts/components/dashboard-page-header";
import type { AccountDetail } from "@/features/accounts/types";
import { useAuth } from "@/features/auth/components/auth-provider";
import { AuthLoadingScreen } from "@/features/auth/components/auth-loading-screen";
import { authFormErrorMessage } from "@/features/auth/utils/auth-form-errors";
import { ApiError } from "@/lib/api/errors";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";

type LoadState = "idle" | "loading" | "ready" | "forbidden" | "not-found" | "error";

type AccountWebsitesPlaceholderProps = {
  accountIdParam: string;
};

export function AccountWebsitesPlaceholder({
  accountIdParam,
}: AccountWebsitesPlaceholderProps) {
  const router = useRouter();
  const { status } = useAuth();
  const [loadState, setLoadState] = useState<LoadState>("idle");
  const [account, setAccount] = useState<AccountDetail | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const accountId = Number.parseInt(accountIdParam, 10);
  const isInvalidAccountId = !Number.isFinite(accountId) || accountId <= 0;

  useEffect(() => {
    if (status === "loading" || status === "unauthenticated") {
      return;
    }

    if (isInvalidAccountId) {
      return;
    }

    let cancelled = false;

    async function loadAccount() {
      setLoadState("loading");
      setErrorMessage(null);

      try {
        const response = await getAccount(accountId);

        if (cancelled) {
          return;
        }

        setAccount(response.data);
        setLoadState("ready");
      } catch (error) {
        if (cancelled) {
          return;
        }

        if (error instanceof ApiError) {
          if (error.isUnauthorized()) {
            router.replace("/login");
            return;
          }

          if (error.isForbidden()) {
            setLoadState("forbidden");
            return;
          }

          if (error.isNotFound()) {
            setLoadState("not-found");
            return;
          }
        }

        setErrorMessage(
          authFormErrorMessage(error, "Unable to load this account."),
        );
        setLoadState("error");
      }
    }

    void loadAccount();

    return () => {
      cancelled = true;
    };
  }, [status, accountId, isInvalidAccountId, router]);

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [status, router]);

  if (status === "loading") {
    return <AuthLoadingScreen message="Loading account…" />;
  }

  if (status === "unauthenticated") {
    return null;
  }

  if (isInvalidAccountId) {
    return (
      <AccountWebsitesLayout>
        <NotFoundPanel />
      </AccountWebsitesLayout>
    );
  }

  if (loadState === "idle" || loadState === "loading") {
    return <AuthLoadingScreen message="Loading account…" />;
  }

  return (
    <AccountWebsitesLayout>
      {loadState === "forbidden" ? <ForbiddenPanel /> : null}
      {loadState === "not-found" ? <NotFoundPanel /> : null}
      {loadState === "error" && errorMessage ? (
        <section
          className="rounded-2xl border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-950/40"
          role="alert"
        >
          <p className="text-sm text-red-800 dark:text-red-200">{errorMessage}</p>
        </section>
      ) : null}
      {loadState === "ready" && account ? <ReadyPanel account={account} /> : null}
    </AccountWebsitesLayout>
  );
}

function AccountWebsitesLayout({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950">
      <DashboardPageHeader title="My Websites" />
      <main className="mx-auto max-w-5xl px-6 py-10">
        <div className="mb-6">
          <Link
            href="/accounts"
            className="text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-50"
          >
            ← Choose another account
          </Link>
        </div>
        {children}
      </main>
    </div>
  );
}

function ForbiddenPanel() {
  return (
    <section className="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
      <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-50">
        Access denied
      </h2>
      <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        You do not have permission to view this account.
      </p>
    </section>
  );
}

function NotFoundPanel() {
  return (
    <section className="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
      <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-50">
        Account not found
      </h2>
      <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        This account is unavailable or you do not have access to it.
      </p>
    </section>
  );
}

function ReadyPanel({ account }: { account: AccountDetail }) {
  return (
    <section className="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
      <p className="text-sm text-zinc-500 dark:text-zinc-400">Account</p>
      <h2 className="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
        {account.name}
      </h2>
      <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        Status: {account.status}
      </p>
      <h3 className="mt-8 text-base font-semibold text-zinc-900 dark:text-zinc-50">
        My Websites
      </h3>
      <p className="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        Website management will be added in a later dashboard slice.
      </p>
    </section>
  );
}
