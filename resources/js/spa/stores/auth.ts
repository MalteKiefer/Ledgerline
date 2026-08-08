import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, getToken, setToken, ApiError } from '@spa/api/client';

export interface MeUser {
  id: number;
  name: string;
  email: string;
  locale: string;
  groups: string[];
  modules: string[];
  has_avatar: boolean;
  two_factor?: boolean;
  preferences?: Record<string, unknown>;
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
      const res = await api.post<{ token: string; user: MeUser }>('/api/v1/auth/login', { email, password, code, recovery_code });
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

  async function logout(): Promise<void> {
    try { await api.post('/api/v1/auth/logout'); } catch { /* token may already be gone */ }
    setToken(null);
    user.value = null;
  }

  function can(module: string): boolean {
    return user.value?.modules?.includes(module) ?? false;
  }

  const isAdmin = () => user.value?.groups?.includes('admin') ?? false;

  return { user, ready, bootstrap, login, logout, can, isAdmin };
});
