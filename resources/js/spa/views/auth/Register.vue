<template>
  <div class="grid min-h-screen place-items-center bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>
      <Card :body-class="'p-6'">
        <template v-if="verifyEmail">
          <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.verify_title') }}</h1>
          <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('auth_ui.verify_intro') }}</p>
          <Btn variant="solid" size="lg" class="w-full" @click="router.push({ name: 'login' })">{{ t('auth_ui.login_link') }}</Btn>
        </template>
        <template v-else-if="disabled">
          <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.register_title') }}</h1>
          <div class="mb-4 mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">{{ t('auth_ui.register_disabled') }}</div>
          <RouterLink :to="{ name: 'login' }" class="text-sm text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.back_to_login') }}</RouterLink>
        </template>
        <template v-else>
          <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.register_title') }}</h1>
          <div v-if="error" class="mb-4 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
          <form class="space-y-4" @submit.prevent="submit">
            <TextField v-model="name" :label="t('auth_ui.name')" icon="person" autocomplete="name" :error="err.name?.[0]" @enter="submit" />
            <TextField v-model="email" :label="t('auth_ui.email')" type="email" icon="mail" autocomplete="username" :error="err.email?.[0]" @enter="submit" />
            <TextField v-model="password" :label="t('auth_ui.password')" type="password" icon="lock" autocomplete="new-password" :error="err.password?.[0]" @enter="submit" />
            <TextField v-model="passwordConfirm" :label="t('auth_ui.password_confirm')" type="password" icon="lock" autocomplete="new-password" @enter="submit" />
            <Btn type="submit" variant="solid" size="lg" class="w-full" :loading="busy">{{ t('auth_ui.create_account') }}</Btn>
          </form>
          <p class="mt-5 text-center text-sm text-[var(--ll-muted)]">
            {{ t('auth_ui.have_account') }}
            <RouterLink :to="{ name: 'login' }" class="text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.login_link') }}</RouterLink>
          </p>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirm = ref('');
const busy = ref(false);
const error = ref('');
const verifyEmail = ref(false);
const disabled = ref(false);
const err = reactive<Record<string, string[] | undefined>>({});

async function submit() {
  busy.value = true;
  error.value = '';
  Object.keys(err).forEach((k) => (err[k] = undefined));
  try {
    const { verifyEmail: needsVerify } = await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirm.value,
    });
    if (needsVerify) { verifyEmail.value = true; return; }
    router.push({ name: 'home' });
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) disabled.value = true;
    else if (e instanceof ApiError && e.fields) Object.assign(err, e.fields);
    else error.value = t('common.error');
  } finally {
    busy.value = false;
  }
}
</script>
