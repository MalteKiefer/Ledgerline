import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '@spa/api/client';

export interface KeyEntry { id: number; type: string; label: string; fingerprint: string | null; has_private: boolean; is_own: boolean }
export interface Recipient { id: number; type: string; label: string; fingerprint: string | null }

/**
 * Shared encryption keyring (own PGP/S-MIME keys + saved recipients). Used by the
 * Files module to encrypt/decrypt, and managed from the keyring dialog.
 */
export const useCryptoStore = defineStore('crypto', () => {
  const keys = ref<KeyEntry[]>([]);
  const recipients = ref<Recipient[]>([]);

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

  return { keys, recipients, load, generatePgp, importPgp, importSmime, deleteKey, importRecipient, deleteRecipient };
});
