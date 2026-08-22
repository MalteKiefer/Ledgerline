<template>
  <div class="block">
    <span v-if="label" class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ label }}</span>
    <div ref="root" class="relative">
      <button
        ref="trigger"
        type="button"
        class="flex w-full items-center justify-between gap-2 rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-left text-sm text-[var(--ll-fg)] focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:opacity-60"
        :disabled="disabled"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        @click="toggle"
        @keydown.down.prevent="openWith(1)"
        @keydown.up.prevent="openWith(-1)"
        @keydown.enter.prevent="toggle"
        @keydown.space.prevent="toggle"
      >
        <span class="truncate">{{ selectedTitle }}</span>
        <Icon name="expand_more" :size="18" class="shrink-0 text-[var(--ll-muted)] transition-transform" :class="open ? 'rotate-180' : ''" />
      </button>

      <!-- A real listbox rather than a native select: the popup a browser draws
           for <select> is painted by the operating system and cannot be styled
           at all, which is why every dropdown in the app looked foreign. -->
      <ul
        v-if="open"
        ref="list"
        role="listbox"
        tabindex="-1"
        class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] py-1 shadow-lg"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="choose(options[active])"
        @keydown.esc.prevent="close"
      >
        <li
          v-for="(o, i) in options"
          :key="String(o.value)"
          role="option"
          :aria-selected="o.value === modelValue"
          class="cursor-pointer px-3 py-1.5 text-sm"
          :class="[
            i === active ? 'bg-primary-500/10' : '',
            o.value === modelValue ? 'font-medium text-primary-700 dark:text-primary-300' : 'text-[var(--ll-fg)]',
          ]"
          @mouseenter="active = i"
          @click="choose(o)"
        >
          {{ o.title }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * A select whose open list belongs to the page.
 *
 * Same props and events as the native-select version it replaces, so every
 * existing call site gains the styling without being touched.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';

interface Option { title: string; value: string | number }

const props = defineProps<{
  modelValue?: string | number | null;
  label?: string;
  options: Option[];
  disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const open = ref(false);
const active = ref(0);
const root = ref<HTMLElement | null>(null);
const list = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);

const selectedTitle = computed(
  () => props.options.find((o) => o.value === props.modelValue)?.title ?? '',
);

function toggle() {
  open.value ? close() : openWith(0);
}

function openWith(step: number) {
  if (props.disabled) return;
  const current = props.options.findIndex((o) => o.value === props.modelValue);
  active.value = Math.max(0, Math.min(props.options.length - 1, (current < 0 ? 0 : current) + step));
  open.value = true;
  void nextTick(() => list.value?.focus());
}

function move(step: number) {
  active.value = Math.max(0, Math.min(props.options.length - 1, active.value + step));
}

function choose(o: Option | undefined) {
  if (!o) return;
  emit('update:modelValue', String(o.value));
  close();
}

function close() {
  open.value = false;
  // Focus goes back where it came from, or a keyboard user is stranded.
  trigger.value?.focus();
}

function onDocumentClick(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false;
}

watch(open, (isOpen) => {
  if (isOpen) document.addEventListener('mousedown', onDocumentClick);
  else document.removeEventListener('mousedown', onDocumentClick);
});

onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick));
</script>
