<template>
  <div class="grid min-h-screen place-items-center bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="w-full max-w-sm">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>
      <Card :body-class="'p-6'">
        <div v-if="loading" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</div>

        <template v-else-if="!valid">
          <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.invite_title') }}</h1>
          <div class="mb-4 mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ t('auth_ui.invite_invalid') }}</div>
          <RouterLink :to="{ name: 'login' }" class="text-sm text-primary-600 hover:underline dark:text-primary-300">{{ t('auth_ui.back_to_login') }}</RouterLink>
        </template>

        <template v-else>
          <h1 class="mb-1 text-lg font-semibold">{{ t('auth_ui.invite_title') }}</h1>
          <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('auth_ui.invite_subtitle', { email }) }}</p>
          <div v-if="error" class="mb-4 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</div>
          <form class="space-y-4" @submit.prevent="submit">
            <TextField v-model="password" :label="t('auth_ui.password')" type="password" icon="lock" autocomplete="new-password" :hint="t('auth_ui.invite_password_hint')" :error="err.password?.[0]" @enter="submit" />
            <TextField v-model="passwordConfirm" :label="t('auth_ui.password_confirm')" type="password" icon="lock" autocomplete="new-password" @enter="submit" />
            <Btn type="submit" variant="solid" size="lg" class="w-full" :loading="busy">{{ t('auth_ui.invite_button') }}</Btn>
          </form>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const param = (v: unknown): string => (typeof v === 'string' ? v : Array.isArray(v) ? (v[0] ?? '') : '');
const invite = param(route.params.invite);
const token = param(route.params.token);

const loading = ref(true);
const valid = ref(false);
const email = ref('');
const password = ref('');
const passwordConfirm = ref('');
const busy = ref(false);
const error = ref('');
const err = reactive<Record<string, string[] | undefined>>({});

onMounted(async () => {
  try {
    const r = await auth.inviteShow(invite, token);
    valid.value = r.valid;
    email.value = r.email ?? '';
  } catch { valid.value = false; } finally { loading.value = false; }
});

async function submit() {
  busy.value = true;
  error.value = '';
  Object.keys(err).forEach((k) => (err[k] = undefined));
  try {
    await auth.inviteConsume(invite, token, password.value, passwordConfirm.value);
    router.push({ name: 'home' });
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) { valid.value = false; }
    else if (e instanceof ApiError && e.fields) Object.assign(err, e.fields);
    else error.value = t('common.error');
  } finally {
    busy.value = false;
  }
}
</script>
