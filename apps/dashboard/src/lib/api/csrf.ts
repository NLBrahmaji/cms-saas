import { apiUrl } from "@/lib/api/config";

let csrfCookieInitialized = false;

function readXsrfTokenFromDocument(): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

  if (!match?.[1]) {
    return null;
  }

  return decodeURIComponent(match[1]);
}

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfCookieInitialized) {
    return;
  }

  const response = await fetch(apiUrl("/sanctum/csrf-cookie"), {
    method: "GET",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });

  if (!response.ok) {
    throw new Error("Unable to initialize CSRF protection.");
  }

  csrfCookieInitialized = true;
}

export function getXsrfTokenHeader(): string | null {
  return readXsrfTokenFromDocument();
}

export function resetCsrfCookieState(): void {
  csrfCookieInitialized = false;
}
