<template>
  <DialogRoot :open="modelValue" @update:open="$emit('update:modelValue', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm data-[state=open]:animate-[fade_.15s_ease]" />
      <DialogContent
        class="fixed left-1/2 top-1/2 z-50 w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-xl focus:outline-none"
        :style="{ maxWidth: width ?? '520px' }"
      >
        <div class="flex items-center gap-2 px-5 py-3.5 border-b border-[var(--ll-border)]">
          <DialogTitle class="text-sm font-semibold">{{ title }}</DialogTitle>
          <DialogClose class="ml-auto grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10">
            <Icon name="close" :size="18" />
          </DialogClose>
        </div>
        <div class="max-h-[70vh] overflow-y-auto p-5"><slot /></div>
        <div v-if="$slots.footer" class="flex justify-end gap-2 px-5 py-3.5 border-t border-[var(--ll-border)]"><slot name="footer" /></div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogClose } from 'reka-ui';
import Icon from './Icon.vue';
defineProps<{ modelValue: boolean; title?: string; width?: string }>();
defineEmits<{ 'update:modelValue': [boolean] }>();
</script>
