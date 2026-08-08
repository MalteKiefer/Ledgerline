<template>
  <div>
    <!-- Export -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title>{{ t('account.export_heading') }}</v-card-title>
      <v-card-text>
        <p class="text-medium-emphasis mb-4">{{ t('account.export_hint') }}</p>
        <v-btn variant="tonal" :prepend-icon="mdiDownload" href="/api/v1/account/export">{{ t('account.export_button') }}</v-btn>
      </v-card-text>
    </v-card>

    <!-- Active web sessions -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title>{{ t('account.sessions_heading') }}</v-card-title>
      <v-card-text class="pb-0">
        <p class="text-medium-emphasis">{{ t('account.sessions_hint') }}</p>
      </v-card-text>
      <v-list>
        <v-list-item v-for="s in p.sessions" :key="s.id" :title="s.user_agent || t('account.sessions_unknown')" :subtitle="sessionSub(s)">
          <template #append>
            <v-chip v-if="s.current" size="x-small" color="primary" variant="tonal" class="mr-2">{{ t('account.sessions_current') }}</v-chip>
            <v-btn v-else variant="text" size="small" color="error" :icon="mdiLogout" @click="onRevokeSession(s.id)" />
          </template>
        </v-list-item>
        <v-list-item v-if="!p.sessions.length" :title="t('account.sessions_none')" class="text-medium-emphasis" />
      </v-list>
    </v-card>

    <!-- Delete account -->
    <v-card rounded="xl" border flat>
      <v-card-title>{{ t('account.delete_heading') }}</v-card-title>
      <v-card-text>
        <p class="text-medium-emphasis mb-4">{{ t('account.delete_hint') }}</p>
        <v-btn variant="text" color="error" :prepend-icon="mdiDeleteAlert" @click="confirmDelete = true">{{ t('account.delete_button') }}</v-btn>
      </v-card-text>
    </v-card>

    <!-- Delete account: type the account email to confirm -->
    <v-dialog v-model="confirmDelete" max-width="480">
      <v-card rounded="xl">
        <v-card-title>{{ t('account.delete_modal_title') }}</v-card-title>
        <v-card-text>
          <p class="mb-3">{{ t('account.delete_modal_warning') }}</p>
          <v-text-field
            v-model="delConfirm"
            :label="t('account.delete_confirm_label')"
            :error-messages="delMismatch ? [t('account.delete_confirm_mismatch')] : []"
            variant="outlined"
            autocomplete="off"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="confirmDelete = false">{{ t('common.cancel') }}</v-btn>
          <v-btn color="error" :loading="delBusy" :disabled="!deleteReady" @click="onDelete">{{ t('account.delete_button') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { mdiDownload, mdiDeleteAlert, mdiLogout } from '@mdi/js';
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
