<template>
  <div>
    <!-- Export -->
    <Card :title="t('account.export_heading')" class="mb-4">
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('account.export_hint') }}</p>
      <Btn variant="soft" icon="download" tag="a" href="/api/v1/account/export">{{ t('account.export_button') }}</Btn>
    </Card>

    <!-- Active web sessions -->
    <Card :title="t('account.sessions_heading')" body-class="p-0" class="mb-4">
      <p class="px-5 py-3 text-sm text-[var(--ll-muted)]">{{ t('account.sessions_hint') }}</p>
      <div v-for="s in p.sessions" :key="s.id" class="flex items-center gap-3 border-t border-[var(--ll-border)] px-5 py-3">
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium">{{ s.user_agent || t('account.sessions_unknown') }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ sessionSub(s) }}</div>
        </div>
        <Badge v-if="s.current" tone="primary">{{ t('account.sessions_current') }}</Badge>
        <button v-else class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('common.delete')" @click="onRevokeSession(s.id)">
          <Icon name="logout" :size="18" />
        </button>
      </div>
      <div v-if="!p.sessions.length" class="border-t border-[var(--ll-border)] px-5 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('account.sessions_none') }}</div>
    </Card>

    <!-- Delete account -->
    <Card :title="t('account.delete_heading')">
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('account.delete_hint') }}</p>
      <Btn variant="ghost" icon="delete" class="text-red-600 hover:bg-red-500/10" @click="confirmDelete = true">{{ t('account.delete_button') }}</Btn>
    </Card>

    <!-- Delete account: type the account email to confirm -->
    <Modal v-model="confirmDelete" :title="t('account.delete_modal_title')" width="480px">
      <p class="mb-3 text-sm">{{ t('account.delete_modal_warning') }}</p>
      <TextField
        v-model="delConfirm"
        :label="t('account.delete_confirm_label')"
        :error="delMismatch ? t('account.delete_confirm_mismatch') : ''"
        autocomplete="off"
      />
      <template #footer>
        <Btn variant="ghost" @click="confirmDelete = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="danger" :loading="delBusy" :disabled="!deleteReady" @click="onDelete">{{ t('account.delete_button') }}</Btn>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Badge, Icon, Modal, TextField } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore, type Session } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';

const auth = useAuthStore();
const p = useProfileStore();
const router = useRouter();
const { success, error } = useToast();

const confirmDelete = ref(false);
const delConfirm = ref('');
const delBusy = ref(false);
const deleteReady = computed(() => delConfirm.value.trim().toLowerCase() === (auth.user?.email ?? '').toLowerCase() && !!auth.user?.email);
const delMismatch = computed(() => delConfirm.value.length > 0 && !deleteReady.value);

onMounted(() => { void p.loadSessions(); });

function sessionSub(s: Session): string {
  return [s.ip, s.last_active].filter(Boolean).join(' · ');
}

async function onRevokeSession(id: string) {
  try {
    await p.revokeSession(id);
    success(t('account.session_revoked'));
  } catch {
    error(t('common.error'));
  }
}

async function onDelete() {
  if (!deleteReady.value) return;
  delBusy.value = true;
  try {
    await p.deleteAccount(delConfirm.value.trim());
    await auth.logout();
    router.push({ name: 'login' });
  } catch {
    error(t('common.error'));
  } finally {
    delBusy.value = false;
  }
}
</script>
