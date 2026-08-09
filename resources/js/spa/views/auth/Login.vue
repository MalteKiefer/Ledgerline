<template>
  <div class="grid min-h-screen place-items-center bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>
      <Card :body-class="'p-6'">
        <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.sign_in') }}</h1>
        <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('pages.login.subtitle') }}</p>
        <div v-if="error" class="mb-4 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
        <form class="space-y-4" @submit.prevent="submit">
          <TextField v-model="email" :label="t('auth_ui.email')" type="email" icon="mail" autocomplete="username" :disabled="needs2fa" @enter="submit" />
          <TextField v-model="password" :label="t('auth_ui.password')" type="password" icon="lock" autocomplete="current-password" :disabled="needs2fa" @enter="submit" />
          <TextField v-if="needs2fa" v-model="code" :label="t('auth_ui.twofa_code')" icon="pin" inputmode="numeric" autofocus @enter="submit" />
          <Btn type="submit" variant="solid" size="lg" class="w-full" :loading="busy">{{ t('auth_ui.sign_in') }}</Btn>
        </form>
        <div class="mt-5 flex flex-col gap-1.5 text-center text-sm">
          <RouterLink :to="{ name: 'forgot-password' }" class="text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.forgot') }}</RouterLink>
          <p class="text-[var(--ll-muted)]">
            {{ t('auth_ui.no_account') }}
            <RouterLink :to="{ name: 'register' }" class="text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.register_link') }}</RouterLink>
          </p>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const router = useRouter();
const email = ref('');
const password = ref('');
const code = ref('');
const needs2fa = ref(false);
const busy = ref(false);
const error = ref('');

async function submit() {
  busy.value = true;
  error.value = '';
  try {
    const { twoFactor } = await auth.login(email.value, password.value, code.value || undefined);
    if (twoFactor) { needs2fa.value = true; return; }
    router.push({ name: 'home' });
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 422 ? t('auth_ui.invalid_credentials') : String(e);
  } finally {
    busy.value = false;
  }
}
</script>
