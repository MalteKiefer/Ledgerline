<template>
  <Card>
    <template #header><Icon name="security" :size="18" class="text-[var(--ll-muted)]" /><h2 class="text-sm font-semibold">{{ t('settings.virustotal_title') }}</h2></template>
    <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('settings.virustotal_hint') }}</p>
    <div v-if="configured" class="mb-3 text-xs text-emerald-600 dark:text-emerald-300">{{ t('settings.virustotal_configured') }}</div>
    <TextField v-model="apiKey" :label="t('settings.virustotal_api_key')" type="password" autocomplete="new-password" />
    <div class="mt-4 flex justify-end"><Btn variant="solid" :loading="saving" @click="save">{{ t('settings.save') }}</Btn></div>
  </Card>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api, ApiError } from '@spa/api/client';
import { Icon, Btn, Card, TextField } from '@spa/ui';
import { useToast } from '@spa/composables/useToast';
const apiKey = ref(''); const configured = ref(false); const saving = ref(false); const { success, error } = useToast();
onMounted(async () => { try { configured.value = (await api.get<{ configured: boolean }>('/api/v1/admin/virustotal')).configured; } catch { error(t('common.error')); } });
async function save() { saving.value = true; try { configured.value = (await api.put<{ configured: boolean }>('/api/v1/admin/virustotal', { api_key: apiKey.value })).configured; apiKey.value = ''; success(t('settings.virustotal_saved_verified')); } catch (e) { const code = e instanceof ApiError ? (e.body as { error?: string } | null)?.error : null; const messages: Record<string, string> = { virustotal_key_required: 'settings.virustotal_key_required', virustotal_invalid_api_key: 'settings.virustotal_invalid_api_key', virustotal_rate_limited: 'settings.virustotal_rate_limited', virustotal_unavailable: 'settings.virustotal_unavailable' }; error(t(messages[code ?? ''] ?? 'common.error')); } finally { saving.value = false; } }
</script>
