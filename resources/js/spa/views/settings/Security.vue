<template>
  <div class="mx-auto" style="max-width: 720px">
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiShieldAccountOutline" size="small" />
        {{ t('settings.security_devices_heading') }}
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.max_connected_devices"
              :label="t('settings.security_max_devices')"
              type="number"
              min="1"
              max="100"
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
import { mdiShieldAccountOutline, mdiContentSave } from '@mdi/js';

interface SecurityResponse {
  max_connected_devices: number;
}

const { success, error } = useToast();

const form = reactive<{ max_connected_devices: number | null }>({ max_connected_devices: null });

const loading = ref(true);
const saving = ref(false);

async function save() {
  saving.value = true;
  try {
    const res = await api.put<SecurityResponse>('/api/v1/admin/security', {
      max_connected_devices: form.max_connected_devices,
    });
    form.max_connected_devices = res.max_connected_devices;
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await api.get<SecurityResponse>('/api/v1/admin/security');
    form.max_connected_devices = res.max_connected_devices;
  } catch {
    error(t('common.error'));
  } finally {
    loading.value = false;
  }
});
</script>
