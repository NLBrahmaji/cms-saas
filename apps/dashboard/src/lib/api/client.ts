import { apiUrl } from "@/lib/api/config";
import {
  ensureCsrfCookie,
  getXsrfTokenHeader,
  resetCsrfCookieState,
} from "@/lib/api/csrf";
import { ApiError, type ValidationErrors } from "@/lib/api/errors";

type ApiFetchOptions = Omit<RequestInit, "body"> & {
  body?: unknown;
  skipCsrf?: boolean;
};

async function parseResponseBody(response: Response): Promise<unknown> {
  if (response.status === 204) {
    return null;
  }

  const contentType = response.headers.get("content-type") ?? "";

  if (!contentType.includes("application/json")) {
    const text = await response.text();

    if (!text) {
      return null;
    }

    return text;
  }

  return response.json();
}

function messageFromBody(body: unknown, fallback: string): string {
  if (body && typeof body === "object" && "message" in body) {
    const message = (body as { message?: unknown }).message;

    if (typeof message === "string" && message.trim() !== "") {
      return message;
    }
  }

  return fallback;
}

function validationErrorsFromBody(body: unknown): ValidationErrors | undefined {
  if (!body || typeof body !== "object" || !("errors" in body)) {
    return undefined;
  }

  const errors = (body as { errors?: unknown }).errors;

  if (!errors || typeof errors !== "object") {
    return undefined;
  }

  const normalized: ValidationErrors = {};

  for (const [field, messages] of Object.entries(errors)) {
    if (Array.isArray(messages)) {
      normalized[field] = messages.filter(
        (message): message is string => typeof message === "string",
      );
    }
  }

  return Object.keys(normalized).length > 0 ? normalized : undefined;
}

export async function apiFetch<T>(
  path: string,
  options: ApiFetchOptions = {},
): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const isMutation = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (isMutation && !options.skipCsrf) {
    await ensureCsrfCookie();
  }

  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  headers.set("X-Requested-With", "XMLHttpRequest");

  if (options.body !== undefined) {
    headers.set("Content-Type", "application/json");
  }

  if (isMutation && !options.skipCsrf) {
    const xsrfToken = getXsrfTokenHeader();

    if (xsrfToken) {
      headers.set("X-XSRF-TOKEN", xsrfToken);
    }
  }

  let response: Response;

  try {
    response = await fetch(apiUrl(path), {
      ...options,
      method,
      credentials: "include",
      headers,
      body:
        options.body === undefined ? undefined : JSON.stringify(options.body),
    });
  } catch {
    throw new ApiError("Network request failed.", 0);
  }

  const body = await parseResponseBody(response);

  if (response.ok) {
    return body as T;
  }

  if (response.status === 419) {
    resetCsrfCookieState();
  }

  const validationErrors = validationErrorsFromBody(body);
  const message = messageFromBody(
    body,
    response.statusText || "Request failed.",
  );

  throw new ApiError(message, response.status, validationErrors);
}

export async function apiFetchOptional<T>(
  path: string,
  options: ApiFetchOptions = {},
): Promise<T | null> {
  try {
    return await apiFetch<T>(path, options);
  } catch (error) {
    if (error instanceof ApiError && error.isUnauthorized()) {
      return null;
    }

    throw error;
  }
}
