export function getApiBaseUrl(): string {
  const configured = process.env.NEXT_PUBLIC_API_URL?.trim();

  if (!configured) {
    throw new Error(
      "NEXT_PUBLIC_API_URL is not set. Copy .env.example to .env.local and set the Laravel API origin.",
    );
  }

  return configured.replace(/\/+$/, "");
}

export function apiUrl(path: string): string {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;

  return `${getApiBaseUrl()}${normalizedPath}`;
}
