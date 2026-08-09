<template>
  <div class="grid min-h-[60vh] place-items-center text-center">
    <div>
      <Icon name="close" :size="48" class="mx-auto mb-3 text-[var(--ll-muted)]" />
      <div class="mb-1 text-4xl font-bold text-[var(--ll-muted)]">404</div>
      <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('common.not_found') }}</p>
      <Btn variant="soft" @click="router.push('/')">{{ t('common.back') }}</Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { useRouter } from 'vue-router';
import { Icon, Btn } from '@spa/ui';

const router = useRouter();
</script>
