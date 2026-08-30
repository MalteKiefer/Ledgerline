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

export class VersionConflict<T = unknown> extends Error {
  version: number | null;
  current: T | null;
  etag: string | null;
  constructor(version: number | null, current: T | null = null, etag: string | null = null) {
    super('version_conflict');
    this.version = version;
    this.current = current;
    this.etag = etag;
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestOptions {
  json?: unknown;
  form?: FormData;
  headers?: Record<string, string>;
  signal?: AbortSignal;
}

export interface ApiResponse<T> {
  data: T;
  status: number;
  etag: string | null;
}

// Called on any 401 so the app can drop the token + route to login.
let onUnauthorized: (() => void) | null = null;
export function setUnauthorizedHandler(fn: () => void): void { onUnauthorized = fn; }

function apiUrl(path: string): string {
  if (path.startsWith('http')) return path;
  if (path.startsWith('/api/') || path === '/up') return BASE + path;
  return `${BASE}/api/v1/${path}`;
}

async function requestResponse<T>(method: Method, path: string, opts: RequestOptions = {}): Promise<ApiResponse<T>> {
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

  const res = await fetch(apiUrl(path), { method, headers, body, signal: opts.signal });

  if (res.status === 401) {
    setToken(null);
    if (onUnauthorized) onUnauthorized();
    throw new ApiError(401, null);
  }
  if (res.status === 204) return { data: undefined as T, status: res.status, etag: res.headers.get('ETag') };

  let parsed: unknown = null;
  const text = await res.text();
  if (text) {
    try { parsed = JSON.parse(text); } catch { parsed = text; }
  }
  if (res.ok) return { data: parsed as T, status: res.status, etag: res.headers.get('ETag') };

  if (res.status === 409 && parsed && typeof parsed === 'object' && (parsed as { error?: string }).error === 'version_conflict') {
    const body = parsed as { version?: unknown; current?: unknown };
    const current = body.current ?? null;
    const currentVersion = current && typeof current === 'object' && 'version' in current
      ? (current as { version?: unknown }).version
      : null;
    const version = typeof body.version === 'number'
      ? body.version
      : typeof currentVersion === 'number' ? currentVersion : null;
    throw new VersionConflict(version, current, res.headers.get('ETag'));
  }
  throw new ApiError(res.status, parsed);
}

async function request<T>(method: Method, path: string, opts: RequestOptions = {}): Promise<T> {
  return (await requestResponse<T>(method, path, opts)).data;
}

export const api = {
  get: <T>(path: string, headers?: Record<string, string>, signal?: AbortSignal) => request<T>('GET', path, { headers, signal }),
  getResponse: <T>(path: string, headers?: Record<string, string>, signal?: AbortSignal) => requestResponse<T>('GET', path, { headers, signal }),
  post: <T>(path: string, json?: unknown, headers?: Record<string, string>, signal?: AbortSignal) => request<T>('POST', path, { json, headers, signal }),
  put: <T>(path: string, json?: unknown, headers?: Record<string, string>, signal?: AbortSignal) => request<T>('PUT', path, { json, headers, signal }),
  patch: <T>(path: string, json?: unknown, headers?: Record<string, string>, signal?: AbortSignal) => request<T>('PATCH', path, { json, headers, signal }),
  delete: <T>(path: string, json?: unknown, headers?: Record<string, string>, signal?: AbortSignal) => request<T>('DELETE', path, { json, headers, signal }),
  postResponse: <T>(path: string, json?: unknown, headers?: Record<string, string>, signal?: AbortSignal) => requestResponse<T>('POST', path, { json, headers, signal }),
  upload: <T>(path: string, form: FormData, headers?: Record<string, string>) => request<T>('POST', path, { form, headers }),
  // Absolute, base-prefixed URL for a path — use for raw fetch() calls (blob
  // downloads/streams) so they respect VITE_API_URL just like the JSON client.
  url: (path: string) => apiUrl(path),
  // Absolute URL for a raw/stream endpoint with the token as a query param
  // (for <img>/<iframe>/<a> that can't set an Authorization header).
  streamUrl: (path: string) => {
    const t = getToken();
    const u = apiUrl(path);
    return t ? `${u}${u.includes('?') ? '&' : '?'}_token=${encodeURIComponent(t)}` : u;
  },
  // Bearer-authed raw text fetch for a non-JSON GET body (e.g. a text/code
  // file's raw bytes for an inline preview). request()'s JSON.parse-if-it-
  // looks-like-JSON fallback would silently turn a .json file's raw text
  // into a re-serialized object instead of the original bytes — this always
  // returns the response body as-is.
  text: async (path: string): Promise<string> => {
    const token = getToken();
    const res = await fetch(apiUrl(path), { headers: token ? { Authorization: `Bearer ${token}` } : {} });
    if (res.status === 401) {
      setToken(null);
      if (onUnauthorized) onUnauthorized();
      throw new ApiError(401, null);
    }
    if (!res.ok) throw new ApiError(res.status, null);
    return res.text();
  },
};

// Multipart upload with byte-level progress (fetch can't observe upload
// progress, so this uses XHR). Same bearer auth + JSON contract as api.upload.
export function uploadWithProgress<T>(path: string, form: FormData, onProgress?: (fraction: number) => void): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', apiUrl(path));
    xhr.responseType = 'json';
    const t = getToken();
    if (t) xhr.setRequestHeader('Authorization', `Bearer ${t}`);
    xhr.setRequestHeader('Accept', 'application/json');
    if (xhr.upload && onProgress) {
      xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(e.loaded / e.total); };
    }
    xhr.onload = () => {
      const body = xhr.response as unknown;
      if (xhr.status >= 200 && xhr.status < 300) { resolve(body as T); return; }
      if (xhr.status === 401) { setToken(null); onUnauthorized?.(); }
      if (xhr.status === 409) {
        const conflict = body as { version?: unknown; current?: unknown } | null;
        const current = conflict?.current ?? null;
        const currentVersion = current && typeof current === 'object' && 'version' in current
          ? (current as { version?: unknown }).version
          : null;
        const version = typeof conflict?.version === 'number'
          ? conflict.version
          : typeof currentVersion === 'number' ? currentVersion : null;
        reject(new VersionConflict(version, current, xhr.getResponseHeader('ETag')));
        return;
      }
      reject(new ApiError(xhr.status, body));
    };
    xhr.onerror = () => reject(new ApiError(0, null));
    xhr.send(form);
  });
}
