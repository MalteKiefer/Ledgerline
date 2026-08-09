<template>
  <DialogRoot :open="modelValue" @update:open="$emit('update:modelValue', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/40 backdrop-blur-sm data-[state=open]:animate-[fade_.15s_ease]" :style="{ zIndex }" />
      <DialogContent
        class="fixed left-1/2 top-1/2 w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-xl focus:outline-none"
        :style="{ maxWidth: width ?? '520px', zIndex: zIndex + 1 }"
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
import { ref, watch } from 'vue';
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogClose } from 'reka-ui';
import Icon from './Icon.vue';

const props = defineProps<{ modelValue: boolean; title?: string; width?: string }>();
defineEmits<{ 'update:modelValue': [boolean] }>();

// Shared modal stacking: each newly-opened modal sits above the previous one, so
// a modal opened from within another modal (e.g. Versions from the file preview)
// is never rendered behind its opener. Base 1000 keeps modals below the app-wide
// confirm dialog (z-1700) and toast (z-2000); dropdown menus float at z-1600.
let topZ = 1000;
const zIndex = ref(1000);

watch(() => props.modelValue, (open) => {
  if (open) { topZ += 10; zIndex.value = topZ; }
  else if (zIndex.value === topZ) { topZ = Math.max(1000, topZ - 10); }
}, { immediate: true });
</script>
