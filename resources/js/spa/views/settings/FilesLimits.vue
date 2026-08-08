<template>
  <div class="mx-auto" style="max-width: 720px">
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiFileCogOutline" size="small" />
        {{ t('settings.files_limits_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.files_max_upload_mb"
              :label="t('settings.files_max_upload')"
              type="number"
              min="1"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.files_blob_orphan_grace_hours"
              :label="t('settings.files_orphan_grace')"
              type="number"
              min="0"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Sticky save bar -->
    <v-card rounded="xl" border flat color="surface" style="position: sticky; bottom: 12px; z-index: 2">
      <v-card-actions class="px-4 py-3">
        <v-spacer />
        <v-btn color="primary" variant="flat" :prepend-icon="mdiContentSave" :loading="saving" :disabled="loading" @click="save">
          {{ t('settings.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { mdiFileCogOutline, mdiContentSave } from '@mdi/js';

interface FilesLimitsResponse {
  files_max_upload_mb: number;
  files_blob_orphan_grace_hours: number;
}

const { success, error } = useToast();

const form = reactive<{ files_max_upload_mb: number | null; files_blob_orphan_grace_hours: number | null }>({
  files_max_upload_mb: null,
  files_blob_orphan_grace_hours: null,
});

const loading = ref(true);
const saving = ref(false);

function apply(c: FilesLimitsResponse) {
  form.files_max_upload_mb = c.files_max_upload_mb;
  form.files_blob_orphan_grace_hours = c.files_blob_orphan_grace_hours;
}

async function save() {
  saving.value = true;
  try {
    apply(
      await api.put<FilesLimitsResponse>('/api/v1/admin/files-limits', {
        files_max_upload_mb: form.files_max_upload_mb,
        files_blob_orphan_grace_hours: form.files_blob_orphan_grace_hours,
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
