import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, getToken } from '@spa/api/client';

// --- Types (mirror openapi.yaml #/components/schemas/Mail*) -------------------

export interface MailAddress { name: string | null; email: string }

export interface MailAccount {
  id: number;
  name: string;
  host: string;
  port: number;
  username: string;
  encryption: 'ssl' | 'tls' | 'starttls' | 'none';
  // SMTP (compose/reply/forward). The password is never returned —
  // `has_smtp_password` tells the client whether one is stored.
  smtp_host: string | null;
  smtp_port: number | null;
  smtp_username: string | null;
  smtp_encryption: 'ssl' | 'tls' | 'starttls' | 'none' | null;
  from_name: string | null;
  from_email: string | null;
  has_smtp_password: boolean;
  folders: string[] | null;
  backfill_since: string | null;
  delete_after_import: boolean;
  write_back_flags: boolean;
  write_back_deletes: boolean;
  trash_folder: string | null;
  skip_spam: boolean;
  enabled: boolean;
  sync_interval_minutes: number | null;
  status: 'idle' | 'syncing' | 'error';
  last_error: string | null;
  last_synced_at: string | null;
  message_count: number;
}

export interface MailAutoconfigCandidate { host: string; port: number; encryption: 'ssl' | 'tls' | 'starttls' | 'none'; username: string | null }
export interface MailAutoconfig {
  email: string; domain: string; domain_resolves: boolean; imap: MailAutoconfigCandidate | null; smtp: MailAutoconfigCandidate | null;
  sources: string[]; outlook_autodiscover: boolean;
}

/** Whether an account can send — mirrors the backend MailAccount::hasSmtp(). */
export function accountCanSend(a: MailAccount): boolean {
  return !!(a.smtp_host && a.smtp_host.trim() && a.from_email && a.from_email.trim());
}

export interface MailAttachment {
  id: string;
  filename: string | null;
  content_type: string | null;
  size: number;
  inline: boolean;
}
export interface MailSignature { id: number; name: string; html: string | null; account_ids: number[]; default_account_ids: number[] }
export interface VirusTotalResult { known: boolean; sha256: string; stats?: { malicious: number; suspicious: number; harmless: number; undetected: number } }

export interface ServerFolder {
  name: string;
  delimiter: string | null;
  /** False for \\Noselect: it holds folders, not mail. */
  selectable: boolean;
}

export interface MailLabel { id: number; name: string; color: string; message_count?: number }

export interface MailMessage {
  id: string;
  /** Starred (the IMAP \Flagged flag). */
  flagged: boolean;
  /** Set automatically when a reply to this message goes out. */
  answered: boolean;
  /** First unquoted line of the body, so the list can be scanned. */
  snippet: string | null;
  account_id: number | null;
  folder: string;
  message_id: string | null;
  in_reply_to: string | null;
  references: string | null;
  thread_id: string | null;
  subject: string | null;
  from_name: string | null;
  from_email: string | null;
  to: MailAddress[];
  cc: MailAddress[];
  reply_to: string | null;
  date: string | null;
  size: number;
  has_attachment: boolean;
  attachment_count: number;
  seen: boolean;
  trashed: boolean;
  removed_from_server?: boolean;
  spam: boolean;
  spf: string | null;
  dkim: string | null;
  dmarc: string | null;
  encrypted_type: 'pgp' | 'smime' | null;
  decrypt_status: 'ok' | 'nokey' | 'fail' | null;
  created_at: string | null;
  // show-only
  text_body?: string | null;
  html?: string | null;
  headers_raw?: string | null;
  attachments?: MailAttachment[];
  labels?: MailLabel[];
}

export interface MailFolder { account_id: number | null; folder: string; total: number; unread: number }
export interface MailLog { id: number; level: 'info' | 'warn' | 'error'; event: string; folder: string | null; message: string | null; created_at: string | null }
export interface MailRuleMatch { from?: string | null; to?: string | null; subject?: string | null; folder?: string | null; has_attachment?: boolean | null }
export interface MailRuleAction {
  add_label?: number | null;
  mark_read?: boolean | null;
  trash?: boolean | null;
  skip?: boolean | null;
  /** File the message's attachments in the finance receipt inbox. */
  file_receipt?: boolean | null;
}
export interface MailRule { id?: number; name: string; enabled: boolean; priority: number; match: MailRuleMatch; action: MailRuleAction }
export interface MailAttachmentRow {
  id: string;
  message_id: string;
  filename: string | null;
  content_type: string | null;
  size: number;
  subject: string | null;
  from: string | null;
  folder: string | null;
  date: string | null;
}
export interface MailSavedSearch { id: number; name: string; filters: Record<string, unknown> }
/**
 * A user id parsed off a key/certificate at import/generate time — every part
 * is best-effort and may be missing (e.g. a PGP uid with no <email> bracket).
 * Distinct from MailKeyIdentity below (a generate-REQUEST identity, where
 * email is mandatory).
 */
export interface MailKeyParsedIdentity { name: string | null; email: string | null; comment: string | null }

export interface MailKey {
  id: number; type: 'pgp' | 'smime'; label: string;
  key_fingerprint: string | null; key_id: string | null; public_key: string | null;
  identities: MailKeyParsedIdentity[] | null; has_cert: boolean; cert_pem: string | null;
  algorithm: string | null; key_length: number | null; curve: string | null;
  issuer: string | null; serial: string | null;
  valid_from: string | null; expires_at: string | null; created_at: string | null;
}

/** A user id for key generation — first identity is the primary UID. */
export interface MailKeyIdentity { name?: string | null; email: string; comment?: string | null }

/** Import body: inline upload (armored/p12) OR a stored Files entry (source=files). */
export interface MailKeyImportBody {
  type: 'pgp' | 'smime';
  label: string;
  passphrase?: string | null;
  source?: 'upload' | 'files';
  armored_private_key?: string | null; // pgp + upload
  p12_base64?: string | null; // smime + upload
  file_id?: number | null; // source=files
}

export type MailKeyCurve = 'ed25519' | 'nistp256' | 'nistp384' | 'nistp521' | 'brainpoolP256r1' | 'brainpoolP384r1' | 'brainpoolP512r1';

/** Server-side key-generation options (PGP fields ignored for smime). */
export interface MailKeyGenerateBody {
  type: 'pgp' | 'smime';
  label: string;
  identities: MailKeyIdentity[];
  passphrase?: string | null;
  expire_years?: number | null;
  algorithm?: 'rsa' | 'ecc' | null; // pgp
  key_length?: number | null; // pgp rsa OR smime
  curve?: MailKeyCurve | null; // pgp ecc
  signing_subkey?: boolean | null; // pgp
}

export interface MailFilters {
  accountId: number | null;
  folder: string | null;
  q: string;
  seen: boolean | null;
  spam: boolean | null;
  label: number | null;
  dateFrom: string | null;
  dateTo: string | null;
  trashed: boolean;
  threadId: string | null;
  /** Column the list is ordered by; empty = newest sent first. */
  sort: string;
  dir: 'asc' | 'desc';
}

export interface PageMeta { total: number; per_page: number; current_page: number; last_page: number }

export interface AccountBody {
  name: string; host: string; port: number; username: string; password?: string | null;
  encryption: string; folders: string[] | null; backfill_since: string | null;
  delete_after_import: boolean; write_back_flags: boolean; write_back_deletes: boolean; trash_folder: string | null; skip_spam: boolean; enabled: boolean; sync_interval_minutes: number | null;
  // SMTP — optional; smtp_password is blank-kept on update (KeepBlankSecrets).
  smtp_host: string | null; smtp_port: number | null; smtp_username: string | null;
  smtp_password?: string | null; smtp_encryption: string; from_name: string | null; from_email: string | null;
}

/** Result of a compose/reply/forward send. */
export interface SendResult { ok: boolean; message_id: string | null; appended_to_sent: boolean }
export interface CryptoPayload { crypto_mode?: 'none' | 'sign' | 'encrypt' | 'sign_encrypt'; crypto_type?: 'pgp' | 'smime' | null; signing_key_id?: number | null; recipient_key_ids?: number[]; }

export interface ComposePayload extends CryptoPayload {
  account_id: number;
  to: string[]; cc?: string[]; bcc?: string[];
  subject?: string | null; text?: string | null; html?: string | null;
  attachment_ids?: string[]; signature_id?: number | null; sent_folder?: string | null;
  file_ids?: number[]; gallery_photo_ids?: number[];
  read_receipt?: boolean; high_priority?: boolean;
  files?: File[];
}
export interface MailDraft {
  id: string; mail_account_id: number | null; mode: 'compose' | 'reply' | 'forward'; source_message_id: string | null;
  to: string[] | null; cc: string[] | null; bcc: string[] | null; subject: string | null;
  text_body: string | null; html_body: string | null; mail_signature_id: number | null; sent_folder: string | null;
  file_ids: number[] | null; gallery_photo_ids: number[] | null; read_receipt: boolean; high_priority: boolean; updated_at: string;
  crypto_mode?: 'none' | 'sign' | 'encrypt' | 'sign_encrypt'; crypto_type?: 'pgp' | 'smime' | null; signing_key_id?: number | null; recipient_key_ids?: number[] | null;
}
export interface ReplyPayload extends CryptoPayload {
  text?: string | null; html?: string | null; signature_id?: number | null; all?: boolean; sent_folder?: string | null;
  attachment_ids?: string[]; file_ids?: number[]; gallery_photo_ids?: number[];
  read_receipt?: boolean; high_priority?: boolean; files?: File[];
}
export interface ForwardPayload extends CryptoPayload {
  to: string[]; cc?: string[]; text?: string | null; html?: string | null; signature_id?: number | null; sent_folder?: string | null;
  attachment_ids?: string[]; file_ids?: number[]; gallery_photo_ids?: number[];
  read_receipt?: boolean; high_priority?: boolean; files?: File[];
}

function defaultFilters(): MailFilters {
  return { accountId: null, folder: null, q: '', seen: null, spam: null, label: null, dateFrom: null, dateTo: null, trashed: false, threadId: null, sort: 'date', dir: 'desc' };
}

export const useMailStore = defineStore('mail', () => {
  const accounts = ref<MailAccount[]>([]);
  const folders = ref<MailFolder[]>([]);
  const folderTotals = ref<{ total: number; unread: number }>({ total: 0, unread: 0 });
  const messages = ref<MailMessage[]>([]);
  const meta = ref<PageMeta>({ total: 0, per_page: 50, current_page: 1, last_page: 1 });
  const selected = ref<string[]>([]);
  const filters = ref<MailFilters>(defaultFilters());
  const openMessage = ref<MailMessage | null>(null);
  const labels = ref<MailLabel[]>([]);
  const savedSearches = ref<MailSavedSearch[]>([]);
  const rules = ref<MailRule[]>([]);
  const logs = ref<MailLog[]>([]);

  // --- Accounts -------------------------------------------------------------
  async function loadAccounts() {
    const r = await api.get<{ accounts: MailAccount[] }>('/api/v1/mail/accounts');
    accounts.value = r.accounts;
  }
  async function saveAccount(body: AccountBody, id: number | null): Promise<MailAccount> {
    const r = id
      ? await api.put<{ account: MailAccount }>(`/api/v1/mail/accounts/${id}`, body)
      : await api.post<{ account: MailAccount }>('/api/v1/mail/accounts', body);
    return r.account;
  }
  const autoconfig = (email: string) => api.post<{ configuration: MailAutoconfig }>('/api/v1/mail/accounts/autoconfig', { email });
  const deleteAccount = (id: number) => api.delete(`/api/v1/mail/accounts/${id}`);
  const testAccount = (id: number) => api.post<{ ok: boolean; detail: string }>(`/api/v1/mail/accounts/${id}/test`);
  const syncNow = (id: number) => api.post<{ dispatched: boolean }>(`/api/v1/mail/accounts/${id}/sync`);
  const cancelSync = (id: number) => api.post<{ cancelled: boolean }>(`/api/v1/mail/accounts/${id}/sync/cancel`);
  const accountStatus = (id: number) => api.get<{ status: MailAccount['status']; last_error: string | null; last_synced_at: string | null; message_count: number }>(`/api/v1/mail/accounts/${id}/status`);

  /** Refresh every account's live sync status (rail badges). */
  async function pollStatus() {
    await Promise.all(accounts.value.map(async (a) => {
      try {
        const s = await accountStatus(a.id);
        a.status = s.status; a.last_error = s.last_error; a.last_synced_at = s.last_synced_at; a.message_count = s.message_count;
      } catch { /* ignore transient */ }
    }));
  }

  // --- Folders + messages ---------------------------------------------------
  async function loadFolders(accountId: number | null) {
    const qs = accountId != null ? `?account_id=${accountId}` : '';
    const r = await api.get<{ folders: MailFolder[]; total: number; unread: number }>(`/api/v1/mail/folders${qs}`);
    folders.value = r.folders;
    folderTotals.value = { total: r.total, unread: r.unread };
  }

  async function loadMessages(page = 1) {
    const f = filters.value;
    const qs = new URLSearchParams();
    if (f.accountId != null) qs.set('account_id', String(f.accountId));
    if (f.folder) qs.set('folder', f.folder);
    if (f.trashed) qs.set('trashed', '1');
    if (f.seen != null) qs.set('seen', f.seen ? '1' : '0');
    if (f.spam != null) qs.set('spam', f.spam ? '1' : '0');
    if (f.label != null) qs.set('label', String(f.label));
    if (f.threadId) qs.set('thread_id', f.threadId);
    if (f.q.trim()) qs.set('q', f.q.trim());
    if (f.dateFrom) qs.set('from', f.dateFrom);
    if (f.dateTo) qs.set('to', f.dateTo);
    if (f.sort) qs.set('sort', f.sort);
    if (f.dir) qs.set('dir', f.dir);
    qs.set('per_page', String(meta.value.per_page));
    qs.set('page', String(page));
    const r = await api.get<{ data: MailMessage[]; meta: PageMeta }>(`/api/v1/mail/messages?${qs}`);
    messages.value = r.data;
    meta.value = r.meta;
  }

  /**
   * The envelope rows of one conversation, oldest first — a conversation reads
   * downward. Bounded at 200: beyond that it is a mailing list, not a thread.
   */
  async function thread(threadId: string): Promise<MailMessage[]> {
    const r = await api.get<{ data: MailMessage[] }>(`/api/v1/mail/messages?thread_id=${encodeURIComponent(threadId)}&sort=date&dir=asc&per_page=200`);
    return r.data ?? [];
  }

  async function show(id: string): Promise<MailMessage> {
    const r = await api.get<{ message: MailMessage }>(`/api/v1/mail/messages/${id}`);
    openMessage.value = r.message;
    return r.message;
  }

  const bodyUrl = (id: string, remote = false) => api.streamUrl(`/api/v1/mail/messages/${id}/body${remote ? '?remote=1' : ''}`);
  const rawUrl = (id: string, download = false) => api.streamUrl(`/api/v1/mail/raw/${id}${download ? '?download=1' : ''}`);
  /** One row of the attachment overview: the file plus the message it came from. */
  const attachments = (params: { q?: string; type?: string; accountId?: number | null; folder?: string | null; page?: number }) => {
    const qs = new URLSearchParams();
    if (params.q?.trim()) qs.set('q', params.q.trim());
    if (params.type) qs.set('type', params.type);
    if (params.accountId != null) qs.set('account_id', String(params.accountId));
    if (params.folder) qs.set('folder', params.folder);
    qs.set('page', String(params.page ?? 1));
    qs.set('per_page', '100');
    return api.get<{ data: MailAttachmentRow[]; meta: PageMeta }>(`/api/v1/mail/attachments?${qs}`);
  };

  const attachmentRawUrl = (attId: string, download = false) => api.streamUrl(`/api/v1/mail/attachments/${attId}/raw${download ? '?download=1' : ''}`);
  const virusTotalAttachment = (attId: string) => api.post<VirusTotalResult>(`/api/v1/mail/attachments/${attId}/virustotal`);

  const saveAttachment = (attId: string, target: 'files' | 'paperless' | 'finance', folderId?: number | null) =>
    api.post<{ ok: boolean; target: string; file_id?: number; task?: string }>(`/api/v1/mail/attachments/${attId}/save`, { target, folder_id: folderId ?? null });

  // --- Message state (bulk, metadata-only) ----------------------------------
  const setSeen = (ids: string[], seen: boolean) => api.post<{ updated: number }>('/api/v1/mail/messages/seen', { ids, seen });
  const setFlagged = (ids: string[], flagged: boolean) => api.post<{ updated: number }>('/api/v1/mail/messages/flag', { ids, flagged });

  /**
   * Every unread id under the current filter — for "mark all as read", which
   * must mean the folder and not the page the client happens to have loaded.
   */
  async function unreadIds(): Promise<string[]> {
    return matchingIds({ unreadOnly: true });
  }

  /**
   * Every id under the current filter, paged through with the ordinary listing.
   *
   * Used where "all" has to mean the folder rather than the page the client
   * happens to hold — marking a folder read, selecting a whole search result.
   * Bounded at 20 pages of 1000: twenty thousand is a stop, not a target, and
   * without one a mistyped filter would walk the entire archive.
   */
  async function matchingIds(opts: { unreadOnly?: boolean } = {}): Promise<string[]> {
    const f = filters.value;
    const qs = new URLSearchParams();
    if (f.accountId != null) qs.set('account_id', String(f.accountId));
    if (f.folder) qs.set('folder', f.folder);
    if (f.label != null) qs.set('label', String(f.label));
    if (f.threadId) qs.set('thread_id', f.threadId);
    if (f.trashed) qs.set('trashed', '1');
    if (f.spam != null) qs.set('spam', f.spam ? '1' : '0');
    if (f.dateFrom) qs.set('from', f.dateFrom);
    if (f.dateTo) qs.set('to', f.dateTo);
    if (f.q.trim()) qs.set('q', f.q.trim());
    if (opts.unreadOnly) qs.set('seen', '0');
    else if (f.seen != null) qs.set('seen', f.seen ? '1' : '0');
    qs.set('per_page', '1000');
    const out: string[] = [];
    for (let page = 1; page <= 20; page++) {
      qs.set('page', String(page));
      const r = await api.get<{ data: MailMessage[]; meta: PageMeta }>(`/api/v1/mail/messages?${qs}`);
      out.push(...(r.data ?? []).map((m) => m.id));
      if (page >= r.meta.last_page) break;
    }
    return out;
  }
  const trash = (ids: string[]) => api.post<{ updated: number }>('/api/v1/mail/messages/trash', { ids });
  /** File mail into another folder, here and on the server. */
  /**
   * Pictures for a page of senders, in one request.
   *
   * Fifty little images would otherwise be fifty requests. What the server may
   * look at is the account's own setting; an address with no picture is simply
   * absent from the answer and keeps its initials.
   */
  const avatars = (emails: string[]) =>
    api.post<{ avatars: Record<string, string> }>('/api/v1/mail/avatars', { emails });

  const move = (ids: string[], folder: string) => api.post<{ updated: number }>('/api/v1/mail/messages/move', { ids, folder });

  /**
   * The folders that exist on the mailbox — not the ones the archive holds mail
   * in. An empty folder is invisible in the latter, and a folder cannot be
   * filed into until something is already in it.
   */
  const serverFolders = (accountId: number) =>
    api.get<{ folders: ServerFolder[] }>(`/api/v1/mail/server-folders?account_id=${accountId}`);
  const createFolder = (accountId: number, name: string) =>
    api.post<{ ok: boolean }>('/api/v1/mail/server-folders', { account_id: accountId, name });
  const renameFolder = (accountId: number, from: string, to: string) =>
    api.post<{ ok: boolean }>('/api/v1/mail/server-folders/rename', { account_id: accountId, from, to });
  const deleteFolder = (accountId: number, name: string) =>
    api.post<{ ok: boolean }>('/api/v1/mail/server-folders/delete', { account_id: accountId, name });
  const restore = (ids: string[]) => api.post<{ updated: number }>('/api/v1/mail/messages/restore', { ids });
  const pushBack = (id: string, folder?: string | null) => api.post<{ ok: boolean }>(`/api/v1/mail/messages/${id}/pushback`, { folder: folder ?? null });
  const deleteOrigin = (id: string, folder?: string | null) => api.post<{ ok: boolean; expunged: number }>(`/api/v1/mail/messages/${id}/delete-origin`, { folder: folder ?? null });
  const setLabels = (ids: string[], add: number[], remove: number[]) => api.post<{ updated: number }>('/api/v1/mail/messages/labels', { ids, add, remove });

  // --- Compose / reply / forward (SMTP send) --------------------------------
  // Business rejections come back as ApiError(422/502) with body { ok:false, error }.
  async function compose(p: ComposePayload): Promise<SendResult> {
    if (p.files && p.files.length) {
      const form = new FormData();
      form.append('account_id', String(p.account_id));
      for (const r of p.to) form.append('to[]', r);
      for (const r of p.cc ?? []) form.append('cc[]', r);
      for (const r of p.bcc ?? []) form.append('bcc[]', r);
      if (p.subject != null) form.append('subject', p.subject);
      if (p.text != null) form.append('text', p.text);
      if (p.html != null) form.append('html', p.html);
      if (p.signature_id != null) form.append('signature_id', String(p.signature_id));
      for (const id of p.attachment_ids ?? []) form.append('attachment_ids[]', id);
      for (const id of p.file_ids ?? []) form.append('file_ids[]', String(id));
      for (const id of p.gallery_photo_ids ?? []) form.append('gallery_photo_ids[]', String(id));
      if (p.read_receipt) form.append('read_receipt', '1');
      if (p.high_priority) form.append('high_priority', '1');
      appendCrypto(form, p);
      for (const f of p.files) form.append('attachments[]', f);
      if (p.sent_folder != null) form.append('sent_folder', p.sent_folder);
      return api.upload<SendResult>('/api/v1/mail/messages/compose', form);
    }
    return api.post<SendResult>('/api/v1/mail/messages/compose', {
      account_id: p.account_id, to: p.to, cc: p.cc ?? [], bcc: p.bcc ?? [],
      subject: p.subject ?? null, text: p.text ?? null, html: p.html ?? null, signature_id: p.signature_id ?? null,
      attachment_ids: p.attachment_ids ?? [], sent_folder: p.sent_folder ?? null,
      file_ids: p.file_ids ?? [], gallery_photo_ids: p.gallery_photo_ids ?? [],
      read_receipt: p.read_receipt ?? false, high_priority: p.high_priority ?? false,
      crypto_mode: p.crypto_mode ?? 'none', crypto_type: p.crypto_type ?? null, signing_key_id: p.signing_key_id ?? null, recipient_key_ids: p.recipient_key_ids ?? [],
    });
  }

  function appendAttachments(form: FormData, p: { attachment_ids?: string[]; file_ids?: number[]; gallery_photo_ids?: number[]; files?: File[] }) {
    for (const id of p.attachment_ids ?? []) form.append('attachment_ids[]', id);
    for (const id of p.file_ids ?? []) form.append('file_ids[]', String(id));
    for (const id of p.gallery_photo_ids ?? []) form.append('gallery_photo_ids[]', String(id));
    for (const file of p.files ?? []) form.append('attachments[]', file);
  }
  function appendCrypto(form: FormData, p: CryptoPayload) { if (p.crypto_mode && p.crypto_mode !== 'none') { form.append('crypto_mode', p.crypto_mode); if (p.crypto_type) form.append('crypto_type', p.crypto_type); if (p.signing_key_id != null) form.append('signing_key_id', String(p.signing_key_id)); for (const id of p.recipient_key_ids ?? []) form.append('recipient_key_ids[]', String(id)); } }
  const loadDrafts = () => api.get<{ drafts: MailDraft[] }>('/api/v1/mail/drafts').then((r) => r.drafts);
  const createDraft = (body: Omit<MailDraft, 'id' | 'updated_at'>) => api.post<{ draft: MailDraft }>('/api/v1/mail/drafts', body).then((r) => r.draft);
  const updateDraft = (id: string, body: Partial<Omit<MailDraft, 'id' | 'updated_at'>>) => api.put<{ draft: MailDraft }>(`/api/v1/mail/drafts/${id}`, body).then((r) => r.draft);
  const deleteDraft = (id: string) => api.delete(`/api/v1/mail/drafts/${id}`);
  async function reply(id: string, p: ReplyPayload): Promise<SendResult> {
    if (p.files?.length) {
      const form = new FormData();
      if (p.text != null) form.append('text', p.text);
      if (p.html != null) form.append('html', p.html);
      if (p.signature_id != null) form.append('signature_id', String(p.signature_id));
      if (p.all) form.append('all', '1');
      if (p.sent_folder != null) form.append('sent_folder', p.sent_folder);
      if (p.read_receipt) form.append('read_receipt', '1');
      if (p.high_priority) form.append('high_priority', '1');
      appendCrypto(form, p);
      appendAttachments(form, p);
      return api.upload<SendResult>(`/api/v1/mail/messages/${id}/reply`, form);
    }
    return api.post<SendResult>(`/api/v1/mail/messages/${id}/reply`, {
      text: p.text ?? null, html: p.html ?? null, signature_id: p.signature_id ?? null, all: p.all ?? false, sent_folder: p.sent_folder ?? null,
      attachment_ids: p.attachment_ids ?? [], file_ids: p.file_ids ?? [], gallery_photo_ids: p.gallery_photo_ids ?? [],
      read_receipt: p.read_receipt ?? false, high_priority: p.high_priority ?? false,
      crypto_mode: p.crypto_mode ?? 'none', crypto_type: p.crypto_type ?? null, signing_key_id: p.signing_key_id ?? null, recipient_key_ids: p.recipient_key_ids ?? [],
    });
  }
  async function forward(id: string, p: ForwardPayload): Promise<SendResult> {
    if (p.files?.length) {
      const form = new FormData();
      for (const recipient of p.to) form.append('to[]', recipient);
      for (const recipient of p.cc ?? []) form.append('cc[]', recipient);
      if (p.text != null) form.append('text', p.text);
      if (p.html != null) form.append('html', p.html);
      if (p.signature_id != null) form.append('signature_id', String(p.signature_id));
      if (p.sent_folder != null) form.append('sent_folder', p.sent_folder);
      if (p.read_receipt) form.append('read_receipt', '1');
      if (p.high_priority) form.append('high_priority', '1');
      appendCrypto(form, p);
      appendAttachments(form, p);
      return api.upload<SendResult>(`/api/v1/mail/messages/${id}/forward`, form);
    }
    return api.post<SendResult>(`/api/v1/mail/messages/${id}/forward`, {
      to: p.to, cc: p.cc ?? [], text: p.text ?? null, html: p.html ?? null, signature_id: p.signature_id ?? null, sent_folder: p.sent_folder ?? null,
      attachment_ids: p.attachment_ids ?? [], file_ids: p.file_ids ?? [], gallery_photo_ids: p.gallery_photo_ids ?? [],
      read_receipt: p.read_receipt ?? false, high_priority: p.high_priority ?? false,
      crypto_mode: p.crypto_mode ?? 'none', crypto_type: p.crypto_type ?? null, signing_key_id: p.signing_key_id ?? null, recipient_key_ids: p.recipient_key_ids ?? [],
    });
  }

  // --- Labels ---------------------------------------------------------------
  async function loadLabels() {
    const r = await api.get<{ labels: MailLabel[] }>('/api/v1/mail/labels');
    labels.value = r.labels;
  }
  const createLabel = (name: string, color: string | null) => api.post<{ label: MailLabel }>('/api/v1/mail/labels', { name, color });
  const updateLabel = (id: number, name: string, color: string | null) => api.put<{ label: MailLabel }>(`/api/v1/mail/labels/${id}`, { name, color });
  const deleteLabel = (id: number) => api.delete(`/api/v1/mail/labels/${id}`);

  // --- Rules ----------------------------------------------------------------
  async function loadRules() {
    const r = await api.get<{ rules: MailRule[] }>('/api/v1/mail/rules');
    rules.value = r.rules;
  }
  const createRule = (rule: MailRule) => api.post<{ rule: MailRule }>('/api/v1/mail/rules', rule);
  /**
   * Run rules over mail that is already archived (queued server-side). Omit the
   * id to run every enabled rule. Skip rules are left out server-side — they
   * mean "do not archive", which says nothing about what already is.
   */
  const applyRules = (ruleId?: number) => api.post<{ dispatched: boolean }>(
    ruleId ? `/api/v1/mail/rules/${ruleId}/apply` : '/api/v1/mail/rules/apply', {},
  );
  const updateRule = (id: number, rule: MailRule) => api.put<{ rule: MailRule }>(`/api/v1/mail/rules/${id}`, rule);
  const deleteRule = (id: number) => api.delete(`/api/v1/mail/rules/${id}`);

  // --- Saved searches -------------------------------------------------------
  async function loadSavedSearches() {
    const r = await api.get<{ saved_searches: MailSavedSearch[] }>('/api/v1/mail/saved-searches');
    savedSearches.value = r.saved_searches;
  }
  const saveSearch = (name: string, f: Record<string, unknown>) => api.post<{ saved_search: MailSavedSearch }>('/api/v1/mail/saved-searches', { name, filters: f });
  const deleteSavedSearch = (id: number) => api.delete(`/api/v1/mail/saved-searches/${id}`);

  // --- Logs -----------------------------------------------------------------
  async function loadLogs(accountId: number, level: string | null = null, perPage = 100) {
    const qs = new URLSearchParams();
    if (level) qs.set('level', level);
    qs.set('per_page', String(perPage));
    const r = await api.get<{ data: MailLog[]; meta: unknown }>(`/api/v1/mail/accounts/${accountId}/logs?${qs}`);
    logs.value = r.data;
  }

  // --- Keys (PGP / S-MIME) --------------------------------------------------
  // NOTE: these deliberately hit /crypto/keys, not /mail/keys — same controller
  // (MailKeyController), but the /crypto mount is NOT module:mail-gated (own
  // encryption keys are also used by Files encryption, independent of Mail).
  const loadKeys = () => api.get<{ keys: MailKey[] }>('/api/v1/crypto/keys');
  const importKey = (body: MailKeyImportBody) => api.post<{ key: MailKey }>('/api/v1/crypto/keys', body);
  const generateKey = (body: MailKeyGenerateBody) => api.post<{ key: MailKey }>('/api/v1/crypto/keys/generate', body);
  const deleteKey = (id: number) => api.delete(`/api/v1/crypto/keys/${id}`);
  const exportKey = (id: number, currentPassword: string) =>
    api.post<{ private_key: string; cert_pem?: string | null }>(`/api/v1/crypto/keys/${id}/export`, { current_password: currentPassword });

  // --- Stats + export (binary) ----------------------------------------------
  const loadStats = () => api.get<MailStats>('/api/v1/mail/stats');

  async function exportMessages(payload: { format: 'mbox' | 'zip'; ids?: string[]; folder?: string | null; label?: number | null }) {
    const token = getToken();
    const res = await fetch(api.url('/api/v1/mail/export'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error(`export ${res.status}`);
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `mail-${new Date().toISOString().slice(0, 10)}.${payload.format === 'mbox' ? 'mbox' : 'zip'}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  // Mutate in place (never reassign) so callers holding `store.filters` keep a live ref.
  function resetFilters() { Object.assign(filters.value, defaultFilters()); }

  return {
    accounts, folders, folderTotals, messages, meta, selected, filters, openMessage, labels, savedSearches, rules, logs,
    loadAccounts, saveAccount, autoconfig, deleteAccount, testAccount, syncNow, cancelSync, accountStatus, pollStatus,
    loadFolders, loadMessages, show, bodyUrl, rawUrl, attachmentRawUrl, saveAttachment,
    virusTotalAttachment,
    setSeen, setFlagged, unreadIds, matchingIds, thread, attachments, trash, move, restore, avatars, serverFolders, createFolder, renameFolder, deleteFolder, pushBack, deleteOrigin, setLabels,
    compose, reply, forward, loadDrafts, createDraft, updateDraft, deleteDraft,
    loadLabels, createLabel, updateLabel, deleteLabel,
    loadRules, createRule, applyRules, updateRule, deleteRule,
    loadSavedSearches, saveSearch, deleteSavedSearch,
    loadLogs, loadKeys, importKey, generateKey, deleteKey, exportKey, loadStats, exportMessages, resetFilters,
  };
});

export interface MailStats {
  total_messages: number;
  total_bytes: number;
  per_account: { account_id: number | null; count: number; bytes: number }[];
  per_folder: { account_id: number | null; folder: string; count: number; bytes: number }[];
}
