import { apiFetch } from "@/lib/api/client";

type LogoutResponse = {
  message: string;
};

export async function logout(): Promise<LogoutResponse> {
  return apiFetch<LogoutResponse>("/auth/logout", {
    method: "POST",
    body: {},
  });
}
