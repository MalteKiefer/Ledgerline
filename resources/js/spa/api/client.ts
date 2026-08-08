// Same-origin API client for the Vue SPA. Sanctum cookie/session auth:
// the httpOnly session cookie carries auth (no token in JS); writes send the
// XSRF-TOKEN cookie back as X-XSRF-TOKEN. The client never sends user_id —
// owner-scope + optimistic `version` are enforced server-side.

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

function readCookie(name: string): string | null {
  const m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
  return m ? decodeURIComponent(m[1]) : null;
}

let csrfReady = false;

/** Bootstrap the XSRF-TOKEN cookie once before the first mutating request. */
export async function ensureCsrf(): Promise<void> {
  if (csrfReady) return;
  await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
  csrfReady = true;
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestOptions {
  json?: unknown;
  form?: FormData;
  headers?: Record<string, string>;
}

async function request<T>(method: Method, path: string, opts: RequestOptions = {}): Promise<T> {
  const mutating = method !== 'GET';
  if (mutating) await ensureCsrf();

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(opts.headers ?? {}),
  };
  const xsrf = readCookie('XSRF-TOKEN');
  if (mutating && xsrf) headers['X-XSRF-TOKEN'] = xsrf;

  let body: BodyInit | undefined;
  if (opts.form) {
    body = opts.form;
  } else if (opts.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opts.json);
  }

  const res = await fetch(path.startsWith('/') ? path : `/api/v1/${path}`, {
    method,
    credentials: 'same-origin',
    headers,
    body,
  });

  if (res.status === 204) return undefined as T;

  let parsed: unknown = null;
  const text = await res.text();
  if (text) {
    try {
      parsed = JSON.parse(text);
    } catch {
      parsed = text;
    }
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
};
