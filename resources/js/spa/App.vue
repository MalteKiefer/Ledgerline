<template>
  <div class="min-h-screen bg-[var(--ll-bg)] text-[var(--ll-fg)]">
    <router-view />
    <Transition
      enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0"
      leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="toastState.show"
        class="fixed bottom-4 right-4 z-[2000] flex items-center gap-2 rounded-xl px-4 py-3 text-sm text-white shadow-lg"
        :class="toastClass"
      >
        <Icon :name="toastIcon" :size="18" />
        <span>{{ toastState.text }}</span>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { toastState } from '@spa/composables/useToast';
import { Icon } from '@spa/ui';

const toastClass = computed(() => ({
  success: 'bg-emerald-600',
  error: 'bg-red-600',
  warning: 'bg-amber-600',
  info: 'bg-primary-600',
}[toastState.color as string] ?? 'bg-neutral-800'));
const toastIcon = computed(() => ({
  success: 'check_circle',
  error: 'error',
  warning: 'warning',
  info: 'info',
}[toastState.color as string] ?? 'info'));
</script>
