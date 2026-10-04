import type { RegisterPayload, RegisterResponse } from "@/features/auth/types";
import { apiFetch } from "@/lib/api/client";

export async function register(
  payload: RegisterPayload,
): Promise<RegisterResponse> {
  return apiFetch<RegisterResponse>("/auth/register", {
    method: "POST",
    body: payload,
  });
}
