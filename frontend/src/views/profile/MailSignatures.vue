<template>
  <Card :title="t('account.mail_signatures_title')">
    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('account.mail_signatures_hint') }}</p>
    <div class="grid gap-4 lg:grid-cols-[13rem_minmax(0,1fr)]">
      <aside class="rounded-xl border border-[var(--ll-border)] p-2">
        <button v-for="signature in signatures" :key="signature.id" type="button" class="mb-1 flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm" :class="draft?.id === signature.id ? 'bg-primary-500/10 text-primary-700 dark:text-primary-200' : 'hover:bg-black/[0.04] dark:hover:bg-white/5'" @click="edit(signature)">
          <Icon name="draw" :size="16" /><span class="truncate">{{ signature.name }}</span>
        </button>
        <Btn variant="soft" size="sm" icon="add" block class="mt-2" @click="create">{{ t('account.mail_signature_add') }}</Btn>
      </aside>
      <div class="min-w-0">
        <template v-if="draft">
          <div class="mb-3 flex items-end gap-2">
            <div class="min-w-0 flex-1"><TextField v-model="draft.name" :label="t('account.mail_signature_name')" /></div>
            <Btn v-if="draft.id" variant="ghost" size="sm" icon="delete" class="text-red-600" @click="remove">{{ t('common.delete') }}</Btn>
          </div>
          <RichTextEditor v-model="draft.html" :placeholder="t('account.mail_signature_placeholder')" :labels="editorLabels" />
          <div class="mt-4 rounded-xl border border-[var(--ll-border)] p-3">
            <div class="mb-2 text-sm font-semibold">{{ t('account.mail_signature_accounts') }}</div>
            <div v-if="!accounts.length" class="text-sm text-[var(--ll-muted)]">{{ t('account.mail_signature_no_accounts') }}</div>
            <div v-for="account in accounts" :key="account.id" class="flex items-center gap-3 py-1.5 text-sm">
              <label class="flex min-w-0 flex-1 items-center gap-2"><input v-model="draft.account_ids" :value="account.id" type="checkbox" class="h-4 w-4 accent-primary-500"> <span class="truncate">{{ account.name }} · {{ account.from_email || account.username }}</span></label>
              <label v-if="draft.account_ids.includes(account.id)" class="flex items-center gap-1.5 text-xs text-[var(--ll-muted)]"><input :checked="draft.default_account_ids.includes(account.id)" type="checkbox" class="h-4 w-4 accent-primary-500" @change="toggleDefault(account.id)"> {{ t('account.mail_signature_default') }}</label>
            </div>
          </div>
          <div class="mt-4 flex justify-end"><Btn variant="solid" icon="save" :loading="saving" @click="save">{{ t('common.save') }}</Btn></div>
        </template>
        <div v-else class="grid min-h-56 place-items-center text-sm text-[var(--ll-muted)]">{{ t('account.mail_signature_empty') }}</div>
      </div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Icon, TextField } from '@spa/ui';
import RichTextEditor from '@spa/components/RichTextEditor.vue';
import { api } from '@spa/api/client';
import type { MailAccount, MailSignature } from '@spa/stores/mail';
import { confirmAsk } from '@spa/composables/useConfirm';
import { useToast } from '@spa/composables/useToast';

type Draft = Omit<MailSignature, 'id'> & { id: number | null };
const { success, error } = useToast();
const signatures = ref<MailSignature[]>([]);
const accounts = ref<MailAccount[]>([]);
const draft = ref<Draft | null>(null);
const saving = ref(false);
const editorLabels = computed(() => ({ toolbar: t('account.mail_signature_toolbar'), format: t('account.mail_signature_format'), text: t('account.mail_signature_text'), heading: t('account.mail_signature_heading'), bold: t('account.mail_signature_bold'), italic: t('account.mail_signature_italic'), underline: t('account.mail_signature_underline'), bullets: t('account.mail_signature_bullets'), numbers: t('account.mail_signature_numbers'), color: t('account.mail_signature_color'), link: t('account.mail_signature_link'), image: t('account.mail_signature_image'), clear: t('account.mail_signature_clear') }));
function edit(signature: MailSignature) { draft.value = { ...signature, account_ids: [...signature.account_ids], default_account_ids: [...signature.default_account_ids] }; }
function create() { draft.value = { id: null, name: '', html: '', account_ids: [], default_account_ids: [] }; }
function toggleDefault(accountId: number) {
  if (!draft.value) return;
  draft.value.default_account_ids = draft.value.default_account_ids.includes(accountId) ? draft.value.default_account_ids.filter((id) => id !== accountId) : [...draft.value.default_account_ids, accountId];
}
async function load() {
  const [signatureResponse, accountResponse] = await Promise.all([api.get<{ signatures: MailSignature[] }>('/api/v1/mail/signatures'), api.get<{ accounts: MailAccount[] }>('/api/v1/mail/accounts')]);
  signatures.value = signatureResponse.signatures; accounts.value = accountResponse.accounts;
}
async function save() {
  if (!draft.value || !draft.value.name.trim()) return;
  saving.value = true;
  try {
    const body = { name: draft.value.name.trim(), html: draft.value.html, account_ids: draft.value.account_ids, default_account_ids: draft.value.default_account_ids };
    const response = draft.value.id ? await api.put<{ signature: MailSignature }>(`/api/v1/mail/signatures/${draft.value.id}`, body) : await api.post<{ signature: MailSignature }>('/api/v1/mail/signatures', body);
    await load(); edit(response.signature); success(t('common.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}
async function remove() {
  if (!draft.value?.id || !await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await api.delete(`/api/v1/mail/signatures/${draft.value.id}`); draft.value = null; await load(); success(t('common.deleted')); } catch { error(t('common.error')); }
}
onMounted(() => { void load().catch(() => error(t('common.error'))); });
</script>
