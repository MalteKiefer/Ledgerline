<template>
  <DialogRoot :open="modelValue" @update:open="$emit('update:modelValue', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/40 backdrop-blur-sm data-[state=open]:animate-[fade_.15s_ease]" :style="{ zIndex }" />
      <DialogContent
        class="fixed left-1/2 top-1/2 w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-xl focus:outline-none"
        :style="{ maxWidth: width ?? '520px', zIndex: zIndex + 1 }"
        @pointer-down-outside="persistent && $event.preventDefault()"
        @interact-outside="persistent && $event.preventDefault()"
        @escape-key-down="persistent && $event.preventDefault()"
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

const props = defineProps<{
  modelValue: boolean;
  title?: string;
  width?: string;
  /**
   * Only the close button and the footer buttons dismiss this dialog. For a
   * multi-step form, a click on the backdrop or a stray Escape would throw away
   * everything the user has entered — including a generated key they have
   * already installed on a server.
   */
  persistent?: boolean;
}>();
defineEmits<{ 'update:modelValue': [boolean] }>();

// Shared modal stacking: each newly-opened modal sits above the previous one, so
// a modal opened from within another modal (e.g. Versions from the file preview)
// is never rendered behind its opener. Base 2400 keeps modals — including the
// app-wide confirm/prompt dialog — ABOVE every hand-rolled overlay (gallery
// lightbox 2100, edit/bulk 2200, name/merge 2300). The toast (z-3000) stays on top.
const BASE_Z = 2400;
let topZ = BASE_Z;
const zIndex = ref(BASE_Z);

watch(() => props.modelValue, (open) => {
  if (open) { topZ += 10; zIndex.value = topZ; }
  else if (zIndex.value === topZ) { topZ = Math.max(BASE_Z, topZ - 10); }
}, { immediate: true });
</script>
