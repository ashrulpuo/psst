/**
 * Fetch wrapper for /api (same origin, cookie session, Sanctum CSRF).
 * Admin responses carry a machine-readable `code` when the second factor is missing; the router reacts to it.
 */

export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly code: string | null = null,
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }

  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

let csrfReady: Promise<void> | null = null;
let onAuthLost: (error: ApiError) => void = () => {};

/** Called for 401s and for 403s that mean "second factor needed", so the app can route to the right screen. */
export function setAuthLostHandler(handler: (error: ApiError) => void) {
  onAuthLost = handler;
}

export function xsrfToken(): string | undefined {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match?.[1] ? decodeURIComponent(match[1]) : undefined;
}

function ensureCsrf(): Promise<void> {
  csrfReady ??= fetch("/sanctum/csrf-cookie", { credentials: "include" })
    .then(() => undefined)
    .catch(() => {
      csrfReady = null;
    });
  return csrfReady;
}

export interface RequestOptions {
  body?: unknown;
  query?: Record<string, string | undefined> | undefined;
  /** Don't trigger the global auth-lost handler (used by the bootstrap checks). */
  quiet?: boolean;
}

export async function request<T>(
  method: Method,
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  if (method !== "GET") await ensureCsrf();

  const url = new URL(`/api${path}`, window.location.origin);
  for (const [key, value] of Object.entries(options.query ?? {})) {
    if (value !== undefined && value !== "") url.searchParams.set(key, value);
  }

  const headers: Record<string, string> = { Accept: "application/json" };
  if (options.body !== undefined) headers["Content-Type"] = "application/json";
  const token = xsrfToken();
  if (token) headers["X-XSRF-TOKEN"] = token;

  const init: RequestInit = { method, headers, credentials: "include" };
  if (options.body !== undefined) init.body = JSON.stringify(options.body);

  let response: Response;
  try {
    response = await fetch(url, init);
  } catch {
    throw new ApiError(0, "Can't reach the server. Check your connection and try again.");
  }

  if (response.status === 204) return undefined as T;
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 419) csrfReady = null;
    const error = new ApiError(
      response.status,
      payload?.message ?? "Something went wrong. Please try again.",
      payload?.code ?? null,
      payload?.errors ?? {},
    );
    const lost =
      response.status === 401 || (response.status === 403 && error.code?.startsWith("two_factor"));
    if (lost && !options.quiet) onAuthLost(error);
    throw error;
  }

  return payload as T;
}

export const api = {
  get: <T>(path: string, query?: RequestOptions["query"], quiet = false) =>
    request<T>("GET", path, { query, quiet }),
  post: <T>(path: string, body: unknown = {}, quiet = false) =>
    request<T>("POST", path, { body, quiet }),
  put: <T>(path: string, body: unknown = {}) => request<T>("PUT", path, { body }),
  patch: <T>(path: string, body: unknown) => request<T>("PATCH", path, { body }),
  delete: <T>(path: string) => request<T>("DELETE", path),
};
