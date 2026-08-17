import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface KeyEntry {
  id: number; type: string; label: string; fingerprint: string | null;
  public_key: string | null; cert_pem: string | null;
  has_private: boolean; is_own: boolean;
}
export interface Recipient {
  id: number; type: string; label: string; fingerprint: string | null; key_id: string | null;
  public_key: string | null; cert_pem: string | null;
  key_server_id: number | null; refreshed_at: string | null;
}

export interface KeyServerEntry { id: number; name: string; url: string; enabled: boolean }

/** One candidate key from a keyserver search — not yet imported. */
export interface KeyserverCandidate {
  server_id: number; server_name: string;
  key_id: string; fingerprint: string | null;
  algorithm: string | null; bits: number | null;
  created_at: number | null; expires_at: number | null; revoked: boolean;
  uids: { name: string | null; email: string | null; comment: string | null }[];
}

export interface PresenceResult { server_id: number; server_name: string; present: boolean }

/**
 * Shared encryption keyring (own PGP/S-MIME keys + saved recipients + HKP
 * keyservers). Used by the Files module to encrypt/decrypt, and managed from
 * the profile "Encryption keys" page + the Files keyring dialog.
 */
export const useCryptoStore = defineStore('crypto', () => {
  const keys = ref<KeyEntry[]>([]);
  const recipients = ref<Recipient[]>([]);
  const keyServers = ref<KeyServerEntry[]>([]);

  const load = () => api.get<{ keys: KeyEntry[]; recipients: Recipient[] }>('/api/v1/crypto/keyring')
    .then((r) => { keys.value = r.keys ?? []; recipients.value = r.recipients ?? []; });

  // Generate a new own PGP keypair (server-side) for a single email identity.
  const generatePgp = (body: { label: string; email: string; name?: string; passphrase?: string }) =>
    api.post('/api/v1/crypto/keys/generate', {
      type: 'pgp', label: body.label, passphrase: body.passphrase || undefined,
      identities: [{ email: body.email, name: body.name || undefined }],
    });

  // Import an own key: armored PGP private key or an S/MIME PKCS#12 (base64).
  const importPgp = (body: { label: string; armored_private_key: string; passphrase?: string }) =>
    api.post('/api/v1/crypto/keys', { type: 'pgp', label: body.label, armored_private_key: body.armored_private_key, passphrase: body.passphrase || undefined });
  const importSmime = (body: { label: string; p12_base64: string; passphrase?: string }) =>
    api.post('/api/v1/crypto/keys', { type: 'smime', label: body.label, p12_base64: body.p12_base64, passphrase: body.passphrase || undefined });

  const deleteKey = (id: number) => api.delete(`/api/v1/crypto/keys/${id}`);

  // Import a recipient: an armored PGP public key or an S/MIME certificate (PEM).
  const importRecipient = (body: { type: string; label: string; material: string }) =>
    api.post<{ recipient: Recipient }>('/api/v1/crypto/recipients', body).then((r) => r.recipient);
  const deleteRecipient = (id: number) => api.delete(`/api/v1/crypto/recipients/${id}`);

  // ---- Keyservers (HKP) --------------------------------------------------
  const loadKeyServers = () => api.get<{ servers: KeyServerEntry[] }>('/api/v1/crypto/key-servers')
    .then((r) => { keyServers.value = r.servers ?? []; });
  const createKeyServer = (body: { name: string; url: string; enabled?: boolean }) =>
    api.post<{ server: KeyServerEntry }>('/api/v1/crypto/key-servers', body).then((r) => r.server);
  const updateKeyServer = (id: number, body: { name?: string; url?: string; enabled?: boolean }) =>
    api.put<{ server: KeyServerEntry }>(`/api/v1/crypto/key-servers/${id}`, body).then((r) => r.server);
  const deleteKeyServer = (id: number) => api.delete(`/api/v1/crypto/key-servers/${id}`);

  /** Search one server (serverId) or every enabled one (omitted). */
  const searchKeyservers = (query: string, serverId?: number) =>
    api.post<{ results: KeyserverCandidate[] }>('/api/v1/crypto/key-servers/search', {
      query, server_id: serverId ?? undefined,
    }).then((r) => r.results ?? []);

  /** Fetch a chosen search result from $serverId and save it as a recipient. */
  const importFromKeyserver = (serverId: number, keyId: string, label: string) =>
    api.post<{ recipient: Recipient }>(`/api/v1/crypto/key-servers/${serverId}/import`, { key_id: keyId, label })
      .then((r) => r.recipient);

  /** Re-fetch a recipient from the server it was originally imported from. */
  const refreshRecipient = (id: number) =>
    api.post<{ recipient: Recipient }>(`/api/v1/crypto/recipients/${id}/refresh`).then((r) => r.recipient);

  /** Publish an own PGP key's public part to a configured server. */
  const publishKey = (keyId: number, serverId: number) =>
    api.post<{ ok: boolean }>(`/api/v1/crypto/keys/${keyId}/publish`, { server_id: serverId });

  /** Whether an own PGP key is already published, checked against every enabled server. */
  const checkPresence = (keyId: number) =>
    api.post<{ results: PresenceResult[] }>(`/api/v1/crypto/keys/${keyId}/check-presence`).then((r) => r.results ?? []);

  return {
    keys, recipients, keyServers, load, generatePgp, importPgp, importSmime, deleteKey,
    importRecipient, deleteRecipient,
    loadKeyServers, createKeyServer, updateKeyServer, deleteKeyServer,
    searchKeyservers, importFromKeyserver, refreshRecipient, publishKey, checkPresence,
  };
});
