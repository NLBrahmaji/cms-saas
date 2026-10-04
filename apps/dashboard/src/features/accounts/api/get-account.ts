import type { AccountShowResponse } from "@/features/accounts/types";
import { apiFetch } from "@/lib/api/client";

export async function getAccount(accountId: number): Promise<AccountShowResponse> {
  return apiFetch<AccountShowResponse>(`/accounts/${accountId}`);
}
