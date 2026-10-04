import type { AccountsListResponse } from "@/features/accounts/types";
import { apiFetch } from "@/lib/api/client";

export async function listAccounts(): Promise<AccountsListResponse> {
  return apiFetch<AccountsListResponse>("/accounts");
}
