import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, getToken, setToken, ApiError } from '@spa/api/client';
import { setDateTimePrefs, type DateTimePrefs } from '@spa/lib/datetime';

export interface MeUser {
  id: number;
  name: string;
  email: string;
  locale: string;
  groups: string[];
  modules: string[];
  has_avatar: boolean;
  two_factor?: boolean;
  two_factor_required?: boolean;
  preferences?: Record<string, unknown>;
}

// A stable, non-secret per-browser id so this browser counts as ONE device
// against the shared cap (web + CLI + app), and re-logging-in from the same
// browser replaces its slot instead of stacking a new device each time.
function browserInstallId(): string {
  const KEY = 'll_install_id';
  let id = localStorage.getItem(KEY);
  if (!id) {
    id = (crypto?.randomUUID?.() ?? `b-${Date.now()}-${Math.random().toString(36).slice(2)}`);
    localStorage.setItem(KEY, id);
  }
  return id;
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<MeUser | null>(null);
  const ready = ref(false);

  /** Load the current user from the stored bearer token; null when unauthenticated. */
  async function bootstrap(): Promise<MeUser | null> {
    if (!getToken()) { ready.value = true; user.value = null; return null; }
    try {
      const me = await api.get<{ user: MeUser }>('/api/v1/me');
      user.value = me.user;
      setDateTimePrefs(me.user.preferences as DateTimePrefs | undefined);
    } catch (e) {
      if (e instanceof ApiError && (e.status === 401 || e.status === 419)) {
        user.value = null;
        setToken(null);
      } else {
        throw e;
      }
    } finally {
      ready.value = true;
    }
    return user.value;
  }

  /** email+password (+2FA) → bearer token. Returns twoFactor:true when a code is required. */
  async function login(email: string, password: string, code?: string, recovery_code?: string): Promise<{ twoFactor: boolean }> {
    try {
      const res = await api.post<{ token: string; user: MeUser }>('/api/v1/auth/login', { email, password, code, recovery_code, install_id: browserInstallId(), device_name: 'Web browser' });
      setToken(res.token);
      user.value = res.user;
      return { twoFactor: false };
    } catch (e) {
      // 422 {two_factor:true} → the account needs a TOTP/recovery code.
      if (e instanceof ApiError && e.status === 422 && (e.body as { two_factor?: boolean })?.two_factor) {
        return { twoFactor: true };
      }
      throw e;
    }
  }

  /** Request a password-reset link (public). Always resolves — the API answers generically. */
  async function forgotPassword(email: string): Promise<void> {
    await api.post('/api/v1/auth/forgot-password', { email });
  }

  /** Consume a reset token (public). Throws ApiError(422) on an invalid token/email. */
  async function resetPassword(payload: {
    token: string; email: string; password: string; password_confirmation: string;
  }): Promise<void> {
    await api.post('/api/v1/auth/reset-password', payload);
  }

  /**
   * Self-register (public). Returns `verifyEmail:true` when the account must verify
   * its address before a token is issued; otherwise logs in immediately. Throws
   * ApiError(403) when self-registration is disabled.
   */
  async function register(payload: {
    name: string; email: string; password: string; password_confirmation: string;
  }): Promise<{ verifyEmail: boolean }> {
    const res = await api.post<{ status?: string; token?: string; user?: MeUser }>('/api/v1/auth/register', payload);
    if (res.token && res.user) {
      setToken(res.token);
      user.value = res.user;
      return { verifyEmail: false };
    }
    return { verifyEmail: true };
  }

  /** Validate an invite/reset link (public). Throws ApiError(404) when invalid/expired/used. */
  async function inviteShow(invite: string, token: string): Promise<{ valid: boolean; email?: string; expiresAt?: string | null }> {
    return api.get(`/api/v1/invite/${encodeURIComponent(invite)}/${encodeURIComponent(token)}`);
  }

  /** Consume an invite link (public): set the password and log in with the returned token. */
  async function inviteConsume(invite: string, token: string, password: string, password_confirmation: string): Promise<void> {
    const res = await api.post<{ token: string; user: MeUser }>(
      `/api/v1/invite/${encodeURIComponent(invite)}/${encodeURIComponent(token)}`,
      { password, password_confirmation },
    );
    setToken(res.token);
    user.value = res.user;
  }

  /** Sign in with a passkey / hardware key. Mints a bearer token on success. */
  async function passkeyLogin(): Promise<void> {
    const { getAssertion, passkeysSupported } = await import('@spa/lib/webauthn');
    if (!passkeysSupported()) throw new Error('unsupported');
    const start = await api.post<{ handle: string; options: Record<string, unknown> }>('/api/v1/auth/passkey/options');
    const credential = await getAssertion(start.options as never);
    const res = await api.post<{ token: string; user: MeUser }>('/api/v1/auth/passkey/verify', { handle: start.handle, credential, install_id: browserInstallId() });
    setToken(res.token);
    user.value = res.user;
  }

  async function logout(): Promise<void> {
    try { await api.post('/api/v1/auth/logout'); } catch { /* token may already be gone */ }
    setToken(null);
    user.value = null;
  }

  function can(module: string): boolean {
    return user.value?.modules?.includes(module) ?? false;
  }

  const isAdmin = () => user.value?.groups?.includes('admin') ?? false;

  return { user, ready, bootstrap, login, passkeyLogin, forgotPassword, resetPassword, register, inviteShow, inviteConsume, logout, can, isAdmin };
});
