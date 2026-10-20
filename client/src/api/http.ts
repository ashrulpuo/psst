/**
 * Minimal fetch wrapper for the Laravel/Sanctum API (same-origin, cookie session).
 * - Fetches the CSRF cookie once and echoes it back in X-XSRF-TOKEN.
 * - Turns non-2xx responses into ApiError with a user-presentable message.
 */

export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly errors: Record<string, string[]> = {}
  ) {
    super(message);
    this.name = "ApiError";
  }

  /** First validation message for a field, if any. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

import { isNativeApp } from "@/native/platform";
import { config } from "@/utils/config";

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

let csrfReady: Promise<void> | null = null;
/** Native apps authenticate with a bearer token instead of a cookie session. */
let authToken: string | null = null;
let onUnauthorized: () => void = () => {};

export function setAuthToken(token: string | null) {
  authToken = token;
}

export const getAuthToken = () => authToken;

/** Where API requests go: the configured API origin on native, otherwise the page's own origin. */
export const apiOrigin = () => config.apiUrl || window.location.origin;

/** Called when an authenticated request comes back 401 (session expired). */
export function setUnauthorizedHandler(handler: () => void) {
  onUnauthorized = handler;
}

export function xsrfToken(): string | undefined {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match?.[1] ? decodeURIComponent(match[1]) : undefined;
}

function ensureCsrf(): Promise<void> {
  // Bearer-token requests are not CSRF-prone (nothing is attached automatically), so native skips it.
  if (isNativeApp) return Promise.resolve();
  csrfReady ??= fetch("/sanctum/csrf-cookie", { credentials: "include" })
    .then(() => undefined)
    .catch(() => {
      csrfReady = null;
    });
  return csrfReady;
}

export async function request<T>(
  method: Method,
  path: string,
  options: {
    body?: unknown;
    query?: Record<string, string | undefined> | undefined;
    silent401?: boolean;
    headers?: Record<string, string>;
  } = {}
): Promise<T> {
  if (method !== "GET") await ensureCsrf();

  const url = new URL(`/api${path}`, apiOrigin());
  for (const [key, value] of Object.entries(options.query ?? {})) {
    if (value !== undefined) url.searchParams.set(key, value);
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    ...options.headers
  };
  if (options.body !== undefined) headers["Content-Type"] = "application/json";
  if (isNativeApp) {
    if (authToken) headers.Authorization = `Bearer ${authToken}`;
  } else {
    const token = xsrfToken();
    if (token) headers["X-XSRF-TOKEN"] = token;
  }

  let response: Response;
  try {
    const init: RequestInit = {
      method,
      headers,
      credentials: isNativeApp ? "omit" : "include"
    };
    if (options.body !== undefined) init.body = JSON.stringify(options.body);
    response = await fetch(url, init);
  } catch {
    throw new ApiError(
      0,
      "Can't reach Psst. Check your connection and try again."
    );
  }

  if (response.status === 204) return undefined as T;

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 401 && !options.silent401) onUnauthorized();
    // A stale CSRF cookie: fetch a fresh one on the next write.
    if (response.status === 419) csrfReady = null;
    throw new ApiError(
      response.status,
      payload?.message ?? "Something went wrong. Please try again.",
      payload?.errors ?? {}
    );
  }

  return payload as T;
}

export const http = {
  get: <T>(
    path: string,
    query?: Record<string, string | undefined>,
    silent401 = false
  ) => request<T>("GET", path, { query, silent401 }),
  post: <T>(path: string, body?: unknown) =>
    request<T>("POST", path, { body: body ?? {} }),
  put: <T>(path: string) => request<T>("PUT", path, { body: {} }),
  patch: <T>(path: string, body: unknown) =>
    request<T>("PATCH", path, { body }),
  delete: <T>(path: string) => request<T>("DELETE", path)
};
