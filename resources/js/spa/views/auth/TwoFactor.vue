<template>
  <v-container class="fill-height" fluid>
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="5" lg="4">
        <v-card class="pa-6" rounded="xl" border flat>
          <h1 class="text-h6 mb-4">{{ t('auth.two_factor_title') }}</h1>
          <v-alert v-if="error" type="error" variant="tonal" class="mb-4" :text="error" />
          <v-form @submit.prevent="submit">
            <v-text-field v-if="!useRecovery" v-model="code" :label="t('auth.two_factor_code')" inputmode="numeric" variant="outlined" autofocus />
            <v-text-field v-else v-model="recovery" :label="t('auth.recovery_code')" variant="outlined" autofocus />
            <v-btn type="submit" color="primary" block size="large" :loading="busy">{{ t('auth.login') }}</v-btn>
            <v-btn variant="text" block class="mt-2" @click="useRecovery = !useRecovery">
              {{ useRecovery ? t('auth.use_code') : t('auth.use_recovery') }}
            </v-btn>
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { useAuthStore } from '@spa/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const code = ref('');
const recovery = ref('');
const useRecovery = ref(false);
const busy = ref(false);
const error = ref('');

async function submit() {
  busy.value = true;
  error.value = '';
  try {
    await auth.twoFactor(useRecovery.value ? { recovery_code: recovery.value } : { code: code.value });
    router.push({ name: 'home' });
  } catch (e) {
    error.value = t('auth.two_factor_failed');
  } finally {
    busy.value = false;
  }
}
</script>
