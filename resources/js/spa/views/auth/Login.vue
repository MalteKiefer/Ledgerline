<template>
  <v-container class="fill-height" fluid>
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="5" lg="4">
        <v-card class="pa-6" rounded="xl" border flat>
          <h1 class="text-h5 mb-6">Ledgerline</h1>
          <v-alert v-if="error" type="error" variant="tonal" class="mb-4" :text="error" />
          <v-form @submit.prevent="submit">
            <v-text-field v-model="email" :label="t('account.email')" type="email" variant="outlined" autocomplete="username" required />
            <v-text-field v-model="password" :label="t('account.password')" type="password" variant="outlined" autocomplete="current-password" required />
            <v-checkbox v-model="remember" :label="t('auth.remember_me')" density="compact" />
            <v-btn type="submit" color="primary" block size="large" :loading="busy">{{ t('auth.login') }}</v-btn>
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
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const router = useRouter();
const email = ref('');
const password = ref('');
const remember = ref(false);
const busy = ref(false);
const error = ref('');

async function submit() {
  busy.value = true;
  error.value = '';
  try {
    const { twoFactor } = await auth.login(email.value, password.value, remember.value);
    router.push(twoFactor ? { name: 'two-factor' } : { name: 'home' });
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 422 ? t('auth.failed') : String(e);
  } finally {
    busy.value = false;
  }
}
</script>
