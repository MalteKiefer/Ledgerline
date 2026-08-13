<template>
  <div>
    <Card class="mb-4">
      <template #header>
        <Icon name="folder" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.files_limits_heading') }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField
          v-model="form.files_max_upload_mb"
          :label="t('settings.files_max_upload')"
          type="number"
        />
      </div>
    </Card>

    <!-- Sticky save bar -->
    <div class="sticky bottom-3 z-10 flex justify-end rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 shadow-sm">
      <Btn variant="solid" :loading="saving" :disabled="loading" @click="save">
        {{ t('settings.save') }}
      </Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField } from '@spa/ui';

interface FilesLimitsResponse {
  files_max_upload_mb: number;
}

const { success, error } = useToast();

const form = reactive<{ files_max_upload_mb: number | null }>({
  files_max_upload_mb: null,
});

const loading = ref(true);
const saving = ref(false);

function apply(c: FilesLimitsResponse) {
  form.files_max_upload_mb = c.files_max_upload_mb;
}

async function save() {
  saving.value = true;
  try {
    apply(
      await api.put<FilesLimitsResponse>('/api/v1/admin/files-limits', {
        files_max_upload_mb: form.files_max_upload_mb,
      }),
    );
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    apply(await api.get<FilesLimitsResponse>('/api/v1/admin/files-limits'));
  } catch {
    error(t('common.error'));
  } finally {
    loading.value = false;
  }
});
</script>
