import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface DisplayPreferences {
  unit_distance: 'km' | 'mi';
  unit_elevation: 'm' | 'ft';
  unit_weight: 'kg' | 'lb';
  unit_temp: 'c' | 'f';
  unit_glucose: 'mgdl' | 'mmoll';
  time_format: '24h' | '12h';
}

export interface DeviceToken {
  id: number;
  name: string;
  last_used_at: string | null;
  last_ip: string | null;
  version: string | null;
  installId: string | null;
  wipe_pending?: boolean;
  current?: boolean;
}

export const useProfileStore = defineStore('profile', () => {
  const prefs = ref<DisplayPreferences | null>(null);
  const devices = ref<DeviceToken[]>([]);

  async function loadPrefs() {
    const me = await api.get<{ user: { preferences?: DisplayPreferences } }>('/api/v1/me');
    prefs.value = me.user.preferences ?? null;
  }

  async function savePrefs(patch: Partial<DisplayPreferences>) {
    await api.post('/api/v1/preferences', patch);
    prefs.value = { ...(prefs.value as DisplayPreferences), ...patch };
  }

  async function setTheme(theme: 'light' | 'dark' | 'system') {
    await api.post('/api/v1/theme', { theme });
  }

  async function setLocale(locale: string) {
    await api.post('/api/v1/locale', { locale });
  }

  async function loadDevices() {
    devices.value = await api.get<DeviceToken[]>('/api/v1/devices');
  }

  async function revokeDevice(id: number) {
    await api.delete(`/api/v1/devices/${id}`);
    devices.value = devices.value.filter((d) => d.id !== id);
  }

  async function wipeDevice(id: number) {
    await api.post(`/api/v1/devices/${id}/wipe`);
    const d = devices.value.find((x) => x.id === id);
    if (d) d.wipe_pending = true;
  }

  async function changePassword(current_password: string, password: string, password_confirmation: string) {
    await api.put('/api/v1/user/password', { current_password, password, password_confirmation });
  }

  async function uploadAvatar(file: File) {
    const form = new FormData();
    form.append('avatar', file);
    await api.upload('/api/v1/avatar', form);
  }

  async function removeAvatar() {
    await api.delete('/api/v1/avatar');
  }

  async function deleteAccount(password: string) {
    await api.delete('/api/v1/account', { password });
  }

  return {
    prefs, devices,
    loadPrefs, savePrefs, setTheme, setLocale,
    loadDevices, revokeDevice, wipeDevice,
    changePassword, uploadAvatar, removeAvatar, deleteAccount,
  };
});
