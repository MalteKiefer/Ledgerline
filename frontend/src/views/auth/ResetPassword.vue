<template>
  <div class="grid min-h-screen place-items-center bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>
      <Card :body-class="'p-6'">
        <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.reset_title') }}</h1>

        <template v-if="done">
          <div class="mb-4 rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-600 dark:text-emerald-400">{{ t('auth_ui.reset_success') }}</div>
          <Btn variant="solid" size="lg" class="w-full" @click="router.push({ name: 'login' })">{{ t('auth_ui.sign_in') }}</Btn>
        </template>
        <template v-else>
          <div v-if="error" class="mb-4 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
          <form class="space-y-4" @submit.prevent="submit">
            <TextField v-model="email" :label="t('auth_ui.email')" type="email" icon="mail" autocomplete="username" :error="err.email?.[0]" @enter="submit" />
            <TextField v-model="password" :label="t('auth_ui.password')" type="password" icon="lock" autocomplete="new-password" :error="err.password?.[0]" @enter="submit" />
            <TextField v-model="passwordConfirm" :label="t('auth_ui.password_confirm')" type="password" icon="lock" autocomplete="new-password" @enter="submit" />
            <Btn type="submit" variant="solid" size="lg" class="w-full" :loading="busy">{{ t('auth_ui.reset_button') }}</Btn>
          </form>
          <div class="mt-5 text-center text-sm">
            <RouterLink :to="{ name: 'login' }" class="text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.back_to_login') }}</RouterLink>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const qs = (v: unknown): string => (typeof v === 'string' ? v : Array.isArray(v) ? (v[0] ?? '') : '');
const token = qs(route.query.token ?? route.params.token);
const email = ref(qs(route.query.email));
const password = ref('');
const passwordConfirm = ref('');
const busy = ref(false);
const done = ref(false);
const error = ref('');
const err = reactive<Record<string, string[] | undefined>>({});

async function submit() {
  busy.value = true;
  error.value = '';
  Object.keys(err).forEach((k) => (err[k] = undefined));
  try {
    await auth.resetPassword({
      token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirm.value,
    });
    done.value = true;
  } catch (e) {
    if (e instanceof ApiError && e.fields) Object.assign(err, e.fields);
    else error.value = t('auth_ui.reset_invalid');
  } finally {
    busy.value = false;
  }
}
</script>
