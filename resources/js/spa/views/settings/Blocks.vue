<template>
  <div>
    <Card class="mb-4" body-class="p-0">
      <template #header>
        <Icon name="block" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.blocks_title') }}</h2>
      </template>

      <!-- Add form -->
      <form class="grid grid-cols-1 gap-2 border-b border-[var(--ll-border)] px-4 py-3 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="add">
        <TextField v-model="cidr" :placeholder="t('settings.blocks_cidr_placeholder')" icon="lan" :error="cidrErr" />
        <TextField v-model="reason" :placeholder="t('settings.blocks_reason_placeholder')" />
        <Btn variant="solid" size="md" icon="add" type="submit" :loading="saving" :disabled="!cidr.trim()">{{ t('settings.blocks_add') }}</Btn>
      </form>

      <div v-if="loading" class="h-0.5 w-full overflow-hidden bg-primary-500/15"><div class="h-full w-1/3 animate-pulse bg-primary-500" /></div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wide text-[var(--ll-muted)]">
            <tr class="border-b border-[var(--ll-border)]">
              <th class="px-4 py-2.5 font-medium">{{ t('settings.blocks_col_cidr') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.blocks_col_reason') }}</th>
              <th class="px-4 py-2.5 font-medium">{{ t('settings.blocks_col_created') }}</th>
              <th class="px-4 py-2.5 font-medium text-right"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in sec.blocks" :key="b.id" class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5">
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ b.cidr }}</td>
              <td class="px-4 py-2.5 text-[var(--ll-muted)]">{{ b.reason }}</td>
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ fmtDate(b.created_at) }}</td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end">
                  <button class="grid h-8 w-8 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('settings.unblock')" @click="remove(b.id)">
                    <Icon name="delete" :size="18" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && !sec.blocks.length"><td colspan="4" class="px-4 py-8 text-center text-[var(--ll-muted)]">{{ t('common.none') }}</td></tr>
          </tbody>
        </table>
      </div>
    </Card>

    <p class="px-1 text-xs text-[var(--ll-muted)]">{{ t('settings.blocks_user_note') }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card, TextField } from '@spa/ui';
import { useSecurityStore } from '@spa/stores/security';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';
import { ApiError } from '@spa/api/client';

const sec = useSecurityStore();
const { success, error } = useToast();
const loading = ref(false);
const saving = ref(false);
const cidr = ref('');
const reason = ref('');
const cidrErr = ref('');

function fmtDate(v: string | null): string {
  if (!v) return '';
  const d = new Date(v);
  return isNaN(d.getTime()) ? String(v) : d.toLocaleString(document.documentElement.lang || 'de');
}

async function load() { loading.value = true; try { await sec.loadBlocks(); } catch { error(t('common.error')); } finally { loading.value = false; } }

async function add() {
  cidrErr.value = '';
  if (!cidr.value.trim()) return;
  saving.value = true;
  try {
    await sec.blockIp(cidr.value.trim(), reason.value.trim() || undefined);
    cidr.value = ''; reason.value = '';
    await load();
    success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.fields?.cidr?.[0]) cidrErr.value = e.fields.cidr[0];
    else error(t('common.error'));
  } finally { saving.value = false; }
}

async function remove(id: number) {
  if (!await confirmAsk(t('settings.blocks_remove_confirm'), { danger: true })) return;
  try { await sec.unblockIp(id); await load(); } catch { error(t('common.error')); }
}

onMounted(() => { void load(); });
</script>
