export function getApiBaseUrl(): string {
  const configured = process.env.NEXT_PUBLIC_API_URL?.trim();

  if (!configured) {
    throw new Error(
      "NEXT_PUBLIC_API_URL is not set. Copy .env.example to .env.local and set the Laravel API origin.",
    );
  }

  return configured.replace(/\/+$/, "");
}

function withApplicationApiVersion(path: string): string {
  if (path.startsWith("/sanctum/")) {
    return path;
  }

  if (path.startsWith("/v1/")) {
    return path;
  }

  return `/v1${path}`;
}

export function apiUrl(path: string): string {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const versionedPath = withApplicationApiVersion(normalizedPath);

  return `${getApiBaseUrl()}${versionedPath}`;
}
