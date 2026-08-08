// Backend-agnostic API client. Auth is a bearer token (Authorization header),
// NOT a session cookie — so this SPA is portable to any API host (Laravel now,
// a Go API later) by only changing the base URL. No CSRF, no credentials.

const TOKEN_KEY = 'll_token';
// Configurable API base so the built bundle can point at any host. Empty =
// same-origin (current Laravel deployment). Set VITE_API_URL for a split host.
const BASE = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') ?? '';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}
export function setToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export class ApiError extends Error {
  status: number;
  body: unknown;
  fields?: Record<string, string[]>;
  constructor(status: number, body: unknown) {
    super(`API ${status}`);
    this.status = status;
    this.body = body;
    if (body && typeof body === 'object' && 'errors' in body) {
      this.fields = (body as { errors: Record<string, string[]> }).errors;
    }
  }
}

export class VersionConflict extends Error {
  version: number | null;
  constructor(version: number | null) {
    super('version_conflict');
    this.version = version;
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestOptions {
  json?: unknown;
  form?: FormData;
  headers?: Record<string, string>;
}

// Called on any 401 so the app can drop the token + route to login.
let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(fn: () => void): void { onUnauthorized = fn; }

function apiUrl(path: string): string {
  if (path.startsWith('http')) return path;
  if (path.startsWith('/api/') || path === '/up') return BASE + path;
  return `${BASE}/api/v1/${path}`;
}

async function request<T>(method: Method, path: string, opts: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(opts.headers ?? {}),
  };
  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  let body: BodyInit | undefined;
  if (opts.form) {
    body = opts.form;
  } else if (opts.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opts.json);
  }

  const res = await fetch(apiUrl(path), { method, headers, body });

  if (res.status === 401) {
    setToken(null);
    if (onUnauthorized) onUnauthorized();
    throw new ApiError(401, null);
  }
  if (res.status === 204) return undefined as T;

  let parsed: unknown = null;
  const text = await res.text();
  if (text) {
    try { parsed = JSON.parse(text); } catch { parsed = text; }
  }
  if (res.ok) return parsed as T;

  if (res.status === 409 && parsed && typeof parsed === 'object' && (parsed as { error?: string }).error === 'version_conflict') {
    throw new VersionConflict((parsed as { version?: number }).version ?? null);
  }
  throw new ApiError(res.status, parsed);
}

export const api = {
  get: <T>(path: string, headers?: Record<string, string>) => request<T>('GET', path, { headers }),
  post: <T>(path: string, json?: unknown) => request<T>('POST', path, { json }),
  put: <T>(path: string, json?: unknown) => request<T>('PUT', path, { json }),
  patch: <T>(path: string, json?: unknown) => request<T>('PATCH', path, { json }),
  delete: <T>(path: string, json?: unknown) => request<T>('DELETE', path, { json }),
  upload: <T>(path: string, form: FormData) => request<T>('POST', path, { form }),
  // Absolute URL for a raw/stream endpoint with the token as a query param
  // (for <img>/<iframe>/<a> that can't set an Authorization header).
  streamUrl: (path: string) => {
    const t = getToken();
    const u = apiUrl(path);
    return t ? `${u}${u.includes('?') ? '&' : '?'}_token=${encodeURIComponent(t)}` : u;
  },
};

// Back-compat no-op (old code called ensureCsrf; bearer auth needs no CSRF).
export async function ensureCsrf(): Promise<void> { /* no-op */ }
