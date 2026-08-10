import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, ApiError } from '@spa/api/client';

export interface DisplayPreferences {
  unit_distance: 'km' | 'mi';
  unit_elevation: 'm' | 'ft';
  unit_weight: 'kg' | 'lb';
  unit_temp: 'c' | 'f';
  unit_glucose: 'mgdl' | 'mmoll';
  time_format: '24h' | '12h';
  mail_load_remote: boolean;
  mail_signature: string | null;
}

export interface DeviceToken {
  id: number;
  name: string;
  meta: string;
  version: string | null;
  installId: string | null;
  syncing?: boolean;
  syncDetail?: string | null;
  syncSeen?: string | null;
  wipeRequested?: boolean;
  current?: boolean;
  pushHost?: string | null;
}

/** Enrollment QR + secret, returned only while a pending (unconfirmed) 2FA secret exists. */
export interface TwoFactorEnrollment {
  pending: boolean;
  svg?: string;
  secret?: string;
  uri?: string;
}

export interface PairingSession {
  id: number;
  qr: string;
  expires_at: string | null;
}

export interface PairingStatus {
  status: string;
  device_name: string | null;
}

export interface Session {
  id: string;
  ip: string | null;
  user_agent: string | null;
  last_active: string | null;
  current: boolean;
}

export interface WebDavAccess {
  enabled: boolean;
  username: string;
  url: string;
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
    const r = await api.get<{ devices: DeviceToken[] }>('/api/v1/devices');
    devices.value = r.devices ?? [];
  }

  async function revokeDevice(id: number) {
    await api.delete(`/api/v1/devices/${id}`);
    devices.value = devices.value.filter((d) => d.id !== id);
  }

  async function wipeDevice(id: number) {
    await api.post(`/api/v1/devices/${id}/wipe`);
    const d = devices.value.find((x) => x.id === id);
    if (d) d.wipeRequested = true;
  }

  async function removeDevicePush(id: number) {
    await api.delete(`/api/v1/devices/${id}/push`);
    const d = devices.value.find((x) => x.id === id);
    if (d) d.pushHost = null;
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

  // Account deletion is GDPR erasure: DELETE /api/v1/account expects the user's
  // typed email as `confirmation` (matched server-side against the account email),
  // NOT a password.
  async function deleteAccount(confirmation: string) {
    await api.delete('/api/v1/account', { confirmation });
  }

  // --- Two-factor authentication ---------------------------------------------
  // Confirmed on/off state is read from /me (auth.user.two_factor) — callers must
  // rely on that on mount and NEVER probe the QR endpoint passively. The enrollment
  // QR endpoint is enrollment-only: it returns 200 ONLY while a secret exists but is
  // unconfirmed, and 404s otherwise (off or already-confirmed). Probing it on a
  // normal load would log a spurious console 404, so twoFactorState() below is only
  // called AFTER an explicit "Enable 2FA" (post-POST /enable), never on mount.

  /** GET the enrollment QR/secret. `pending:false` when 2FA is not mid-setup (404). */
  async function twoFactorState(): Promise<TwoFactorEnrollment> {
    try {
      const r = await api.get<{ svg: string; secret: string; uri: string }>('/api/v1/user/two-factor/qr');
      return { pending: true, svg: r.svg, secret: r.secret, uri: r.uri };
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) return { pending: false };
      throw e;
    }
  }

  /**
   * Enable 2FA (step-up: requires the current password), then fetch the QR.
   * `pending:true` => a fresh secret was generated (show the QR to confirm).
   * `pending:false` => the account was already confirmed (enable was a no-op).
   */
  async function enable2fa(currentPassword: string): Promise<TwoFactorEnrollment> {
    await api.post('/api/v1/user/two-factor/enable', { current_password: currentPassword });
    return twoFactorState();
  }

  /** Confirm the pending secret with a live TOTP code (422 {errors:{code}} on bad code). */
  async function confirm2fa(code: string) {
    await api.post('/api/v1/user/two-factor/confirm', { code });
  }

  /**
   * Read the current recovery codes. The endpoint is GET and needs `current_password`;
   * browsers cannot send a body on GET, so it travels as a query parameter. The UI
   * prefers regenerateRecovery (POST) to reveal codes, so this stays out of the
   * request URL in normal use.
   */
  async function recoveryCodes(currentPassword: string): Promise<string[]> {
    // POST with the password in the body — never in the query string (URL/log leak).
    const r = await api.post<{ recovery_codes: string[] }>(
      '/api/v1/user/two-factor/recovery-codes',
      { current_password: currentPassword },
    );
    return r.recovery_codes;
  }

  /** Regenerate recovery codes (step-up) and return the fresh set. */
  async function regenerateRecovery(currentPassword: string): Promise<string[]> {
    const r = await api.post<{ recovery_codes: string[] }>(
      '/api/v1/user/two-factor/recovery-codes/regenerate',
      { current_password: currentPassword },
    );
    return r.recovery_codes;
  }

  /** Disable 2FA entirely (step-up: requires the current password). */
  async function disable2fa(currentPassword: string) {
    await api.delete('/api/v1/user/two-factor', { current_password: currentPassword });
  }

  // --- QR device pairing ------------------------------------------------------
  /** Begin an app pairing; returns its id + a scannable QR (data-uri) + expiry. */
  async function startPairing(): Promise<PairingSession> {
    return api.post<PairingSession>('/api/v1/device-pairings');
  }

  /** Poll a pairing's state (pending_scan → pending_approval → approved/…). */
  async function pairingStatus(id: number): Promise<PairingStatus> {
    return api.get<PairingStatus>(`/api/v1/device-pairings/${id}`);
  }

  /** Approve the claimed device (it then collects its token). */
  async function approvePairing(id: number): Promise<{ status: string }> {
    return api.post<{ status: string }>(`/api/v1/device-pairings/${id}/approve`);
  }

  /** Decline the claimed device. */
  async function rejectPairing(id: number): Promise<{ status: string }> {
    return api.post<{ status: string }>(`/api/v1/device-pairings/${id}/reject`);
  }

  // --- Web sessions -----------------------------------------------------------
  const sessions = ref<Session[]>([]);

  /** Load the browser sessions signed in to this account. */
  async function loadSessions() {
    const r = await api.get<{ sessions: Session[] }>('/api/v1/account/sessions');
    sessions.value = r.sessions ?? [];
  }

  /** Revoke (sign out) a single web session. */
  async function revokeSession(id: string) {
    await api.delete(`/api/v1/account/sessions/${id}`);
    sessions.value = sessions.value.filter((s) => s.id !== id);
  }

  // --- WebDAV access ----------------------------------------------------------
  /** Read the current WebDAV access state (separate revocable credential). */
  async function getWebdav(): Promise<WebDavAccess> {
    return api.get<WebDavAccess>('/api/v1/account/webdav');
  }

  /** Set/rotate the WebDAV password (min 12 chars). */
  async function setWebdav(password: string): Promise<WebDavAccess> {
    return api.put<WebDavAccess>('/api/v1/account/webdav', { webdav_password: password });
  }

  /** Disable WebDAV access entirely. */
  async function clearWebdav() {
    await api.delete('/api/v1/account/webdav');
  }

  return {
    prefs, devices, sessions,
    loadPrefs, savePrefs, setTheme, setLocale,
    loadDevices, revokeDevice, wipeDevice, removeDevicePush,
    changePassword, uploadAvatar, removeAvatar, deleteAccount,
    twoFactorState, enable2fa, confirm2fa, recoveryCodes, regenerateRecovery, disable2fa,
    startPairing, pairingStatus, approvePairing, rejectPairing,
    loadSessions, revokeSession, getWebdav, setWebdav, clearWebdav,
  };
});
