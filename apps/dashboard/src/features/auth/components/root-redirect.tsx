"use client";

import { useAuth } from "@/features/auth/components/auth-provider";
import { AuthLoadingScreen } from "@/features/auth/components/auth-loading-screen";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export function RootRedirect() {
  const router = useRouter();
  const { status } = useAuth();

  useEffect(() => {
    if (status === "loading") {
      return;
    }

    router.replace(status === "authenticated" ? "/accounts" : "/login");
  }, [status, router]);

  return <AuthLoadingScreen />;
}
