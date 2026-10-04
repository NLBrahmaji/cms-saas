import { apiFetchOptional } from "@/lib/api/client";
import type { UserEnvelope } from "@/features/auth/types";

export async function getAuthenticatedUser(): Promise<UserEnvelope | null> {
  return apiFetchOptional<UserEnvelope>("/user");
}
