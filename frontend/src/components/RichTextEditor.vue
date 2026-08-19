<template>
  <div class="overflow-hidden rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-sm focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/20">
    <div class="flex flex-wrap items-center gap-0.5 border-b border-[var(--ll-border)] bg-black/[0.02] p-1.5 dark:bg-white/[0.03]" role="toolbar" :aria-label="labels.toolbar">
      <select class="h-8 rounded-md bg-transparent px-1 text-xs outline-none hover:bg-black/[0.05] dark:hover:bg-white/10" :title="labels.format" @change="formatBlock">
        <option value="p">{{ labels.text }}</option>
        <option value="h3">{{ labels.heading }}</option>
      </select>
      <span class="mx-1 h-5 border-l border-[var(--ll-border)]" />
      <button v-for="action in formatActions" :key="action.command" type="button" class="editor-action" :title="labels[action.label]" :aria-label="labels[action.label]" @click="command(action.command)">
        <Icon :name="action.icon" :size="17" />
      </button>
      <span class="mx-1 h-5 border-l border-[var(--ll-border)]" />
      <button v-for="action in listActions" :key="action.command" type="button" class="editor-action" :title="labels[action.label]" :aria-label="labels[action.label]" @click="command(action.command)">
        <Icon :name="action.icon" :size="17" />
      </button>
      <input v-model="color" type="color" class="mx-1 h-7 w-7 cursor-pointer rounded border-0 bg-transparent p-0" :title="labels.color" :aria-label="labels.color" @input="command('foreColor', color)">
      <span class="mx-1 h-5 border-l border-[var(--ll-border)]" />
      <button type="button" class="editor-action" :title="labels.link" :aria-label="labels.link" @click="addLink"><Icon name="link" :size="17" /></button>
      <button type="button" class="editor-action" :title="labels.image" :aria-label="labels.image" @click="addImage"><Icon name="image" :size="17" /></button>
      <button type="button" class="editor-action" :title="labels.clear" :aria-label="labels.clear" @click="command('removeFormat')"><Icon name="format_clear" :size="17" /></button>
    </div>
    <div
      ref="editor" contenteditable="true" role="textbox" aria-multiline="true"
      class="signature-editor min-h-40 px-4 py-3 text-sm leading-6 text-[var(--ll-fg)] outline-none"
      :data-placeholder="placeholder" @input="emitValue" @blur="emitValue"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import DOMPurify from 'dompurify';
import Icon from '@spa/ui/Icon.vue';

type LabelKey = 'toolbar' | 'format' | 'text' | 'heading' | 'bold' | 'italic' | 'underline' | 'bullets' | 'numbers' | 'color' | 'link' | 'image' | 'clear';
const props = defineProps<{ modelValue: string | null; placeholder: string; labels: Record<LabelKey, string> }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const editor = ref<HTMLDivElement | null>(null);
const color = ref('#6750a4');
const formatActions = [
  { command: 'bold', icon: 'format_bold', label: 'bold' as const },
  { command: 'italic', icon: 'format_italic', label: 'italic' as const },
  { command: 'underline', icon: 'format_underlined', label: 'underline' as const },
];
const listActions = [
  { command: 'insertUnorderedList', icon: 'format_list_bulleted', label: 'bullets' as const },
  { command: 'insertOrderedList', icon: 'format_list_numbered', label: 'numbers' as const },
];
const sanitizer = { ALLOWED_TAGS: ['a', 'b', 'br', 'div', 'em', 'h3', 'img', 'li', 'ol', 'p', 'span', 'strong', 'u', 'ul'], ALLOWED_ATTR: ['alt', 'href', 'src', 'style', 'target', 'title'] };

function clean(value: string | null | undefined): string { return DOMPurify.sanitize(value ?? '', sanitizer).trim(); }
function setContent(value: string | null | undefined) { if (editor.value) editor.value.innerHTML = clean(value); }
function emitValue() { emit('update:modelValue', clean(editor.value?.innerHTML)); }
function command(name: string, value?: string) { editor.value?.focus(); document.execCommand(name, false, value); emitValue(); }
function formatBlock(event: Event) { command('formatBlock', (event.target as HTMLSelectElement).value); }
function addLink() {
  const href = window.prompt(props.labels.link);
  if (href) command('createLink', href.trim());
}
function addImage() {
  const src = window.prompt(props.labels.image);
  if (src) command('insertImage', src.trim());
}

onMounted(() => setContent(props.modelValue));
watch(() => props.modelValue, (value) => { if (document.activeElement !== editor.value) setContent(value); });
</script>

<style scoped>
.editor-action { display:grid; height:2rem; width:2rem; place-items:center; border-radius:.375rem; color:var(--ll-muted); }
.editor-action:hover { background:rgb(0 0 0 / .06); color:var(--ll-fg); }
.signature-editor:empty::before { content:attr(data-placeholder); color:var(--ll-muted); pointer-events:none; }
.signature-editor :deep(img) { display:inline-block; max-height:5rem; max-width:12rem; vertical-align:middle; }
.signature-editor :deep(h3) { margin:.25rem 0; font-size:1.125rem; font-weight:650; }
.signature-editor :deep(p) { margin:.2rem 0; }
.signature-editor :deep(ul), .signature-editor :deep(ol) { margin:.25rem 0; padding-left:1.5rem; }
.dark .editor-action:hover { background:rgb(255 255 255 / .1); }
</style>
