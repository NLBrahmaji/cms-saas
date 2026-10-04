"use client";

import { useAuth } from "@/features/auth/components/auth-provider";
import { useRouter } from "next/navigation";
import { useState } from "react";

type DashboardPageHeaderProps = {
  title: string;
  subtitle?: string;
};

export function DashboardPageHeader({
  title,
  subtitle = "SitePro Dashboard",
}: DashboardPageHeaderProps) {
  const router = useRouter();
  const { logout } = useAuth();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

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
    <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
      <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <div>
          <p className="text-sm font-medium text-zinc-500 dark:text-zinc-400">
            {subtitle}
          </p>
          <h1 className="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
            {title}
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
  );
}
