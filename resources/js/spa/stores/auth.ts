import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, ensureCsrf, ApiError } from '@spa/api/client';

export interface MeUser {
  id: number;
  name: string;
  email: string;
  locale: string;
  groups: string[];
  modules: string[];
  has_avatar: boolean;
  preferences?: Record<string, unknown>;
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<MeUser | null>(null);
  const ready = ref(false);

  /** Load the current user; null when unauthenticated (401). */
  async function bootstrap(): Promise<MeUser | null> {
    try {
      const me = await api.get<{ user: MeUser }>('/api/v1/me');
      user.value = me.user;
    } catch (e) {
      if (e instanceof ApiError && (e.status === 401 || e.status === 419)) {
        user.value = null;
      } else {
        throw e;
      }
    } finally {
      ready.value = true;
    }
    return user.value;
  }

  /** POST credentials to Fortify (headless JSON). Returns true if 2FA is required. */
  async function login(email: string, password: string, remember = false): Promise<{ twoFactor: boolean }> {
    await ensureCsrf();
    const res = await api.post<{ two_factor?: boolean }>('/login', { email, password, remember });
    if (res && res.two_factor) return { twoFactor: true };
    await bootstrap();
    return { twoFactor: false };
  }

  async function twoFactor(payload: { code?: string; recovery_code?: string }): Promise<void> {
    await api.post('/two-factor-challenge', payload);
    await bootstrap();
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/logout');
    } finally {
      user.value = null;
    }
  }

  function can(module: string): boolean {
    return user.value?.modules?.includes(module) ?? false;
  }

  const isAdmin = () => user.value?.groups?.includes('admin') ?? false;

  return { user, ready, bootstrap, login, twoFactor, logout, can, isAdmin };
});
