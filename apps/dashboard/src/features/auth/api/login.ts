import { apiFetch } from "@/lib/api/client";
import type { UserEnvelope } from "@/features/auth/types";

export type LoginCredentials = {
  email: string;
  password: string;
};

export async function login(
  credentials: LoginCredentials,
): Promise<UserEnvelope> {
  return apiFetch<UserEnvelope>("/auth/login", {
    method: "POST",
    body: credentials,
  });
}
