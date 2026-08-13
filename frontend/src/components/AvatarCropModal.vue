<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[2400] flex items-center justify-center bg-black/50 p-4" @click.self="cancel">
      <div class="w-full max-w-md rounded-2xl bg-[var(--ll-elevated)] shadow-xl">
        <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
          <h3 class="text-sm font-semibold">{{ t('contacts.ui.crop_title') }}</h3>
          <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="cancel"><Icon name="close" :size="18" /></button>
        </div>
        <div class="p-5">
          <!-- Square crop viewport: drag to pan, slider to zoom. -->
          <div
            ref="viewport"
            class="relative mx-auto aspect-square w-full max-w-xs cursor-move touch-none overflow-hidden rounded-xl bg-black/[0.06] dark:bg-white/5"
            @pointerdown="onDown" @pointermove="onMove" @pointerup="onUp" @pointercancel="onUp" @wheel.prevent="onWheel"
          >
            <img
              v-if="src" :src="src" alt="" draggable="false"
              class="pointer-events-none absolute left-0 top-0 origin-top-left select-none"
              :style="imgStyle"
              @load="onImgLoad"
            >
            <!-- Circular mask overlay (avatars render round). -->
            <div class="pointer-events-none absolute inset-0 rounded-xl" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.35) inset; -webkit-mask: radial-gradient(circle at center, transparent 49%, #000 50%); mask: radial-gradient(circle at center, transparent 49%, #000 50%);" />
          </div>
          <label class="mt-4 flex items-center gap-2">
            <Icon name="zoom_out" :size="16" class="text-[var(--ll-muted)]" />
            <input type="range" min="1" max="4" step="0.01" :value="scale" class="flex-1 accent-primary-500" @input="setScale(($event.target as HTMLInputElement).valueAsNumber)">
            <Icon name="zoom_in" :size="16" class="text-[var(--ll-muted)]" />
          </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-[var(--ll-border)] px-5 py-3">
          <Btn variant="ghost" size="sm" @click="cancel">{{ t('common.cancel') }}</Btn>
          <Btn variant="solid" size="sm" :loading="busy" @click="apply">{{ t('common.save') }}</Btn>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn } from '@spa/ui';

// A pan/zoom square-crop modal for choosing the avatar framing. Emits a 512px
// square JPEG blob. Works with any image source (device upload, gallery, files).
const props = defineProps<{ open: boolean; blob: Blob | null; busy?: boolean }>();
const emit = defineEmits<{ 'update:open': [boolean]; cropped: [Blob] }>();

const OUT = 512;
const src = ref<string>('');
const viewport = ref<HTMLElement | null>(null);
const img = reactive({ w: 0, h: 0 });
const scale = ref(1);
const offset = reactive({ x: 0, y: 0 }); // top-left of the drawn image, in viewport px
const drag = reactive({ active: false, sx: 0, sy: 0, ox: 0, oy: 0 });
let objectUrl = '';

// Base scale that makes the image COVER the square viewport at zoom 1.
function baseScale(): number {
  const vp = viewport.value?.clientWidth || 1;
  return Math.max(vp / (img.w || 1), vp / (img.h || 1));
}
const drawW = computed(() => img.w * baseScale() * scale.value);
const drawH = computed(() => img.h * baseScale() * scale.value);
const imgStyle = computed(() => ({
  width: `${drawW.value}px`,
  height: `${drawH.value}px`,
  transform: `translate(${offset.x}px, ${offset.y}px)`,
}));

function clamp() {
  const vp = viewport.value?.clientWidth || 0;
  // Keep the image covering the viewport (no empty edges).
  offset.x = Math.min(0, Math.max(vp - drawW.value, offset.x));
  offset.y = Math.min(0, Math.max(vp - drawH.value, offset.y));
}
function center() {
  const vp = viewport.value?.clientWidth || 0;
  offset.x = (vp - drawW.value) / 2;
  offset.y = (vp - drawH.value) / 2;
}

function onImgLoad(e: Event) {
  const el = e.target as HTMLImageElement;
  img.w = el.naturalWidth; img.h = el.naturalHeight;
  scale.value = 1;
  nextTick(() => { center(); clamp(); });
}
function setScale(v: number) {
  const vp = viewport.value?.clientWidth || 0;
  // Zoom around the viewport centre.
  const cx = vp / 2, cy = vp / 2;
  const rx = (cx - offset.x) / drawW.value;
  const ry = (cy - offset.y) / drawH.value;
  scale.value = Math.min(4, Math.max(1, v));
  nextTick(() => {
    offset.x = cx - rx * drawW.value;
    offset.y = cy - ry * drawH.value;
    clamp();
  });
}
function onWheel(e: WheelEvent) { setScale(scale.value * (e.deltaY < 0 ? 1.08 : 0.92)); }
function onDown(e: PointerEvent) {
  drag.active = true; drag.sx = e.clientX; drag.sy = e.clientY; drag.ox = offset.x; drag.oy = offset.y;
  (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
}
function onMove(e: PointerEvent) {
  if (!drag.active) return;
  offset.x = drag.ox + (e.clientX - drag.sx);
  offset.y = drag.oy + (e.clientY - drag.sy);
  clamp();
}
function onUp() { drag.active = false; }

function cancel() { emit('update:open', false); }

async function apply() {
  const vp = viewport.value?.clientWidth || 1;
  const s = baseScale() * scale.value; // image px → viewport px
  // The viewport square maps back to source pixels:
  const sx = (-offset.x) / s;
  const sy = (-offset.y) / s;
  const side = vp / s;
  const canvas = document.createElement('canvas');
  canvas.width = OUT; canvas.height = OUT;
  const ctx = canvas.getContext('2d');
  const el = viewport.value?.querySelector('img') as HTMLImageElement | null;
  if (!ctx || !el) return;
  ctx.drawImage(el, sx, sy, side, side, 0, 0, OUT, OUT);
  const out = await new Promise<Blob | null>((r) => canvas.toBlob((b) => r(b), 'image/jpeg', 0.9));
  if (out) emit('cropped', out);
}

watch(() => props.blob, (b) => {
  if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = ''; }
  if (b) { objectUrl = URL.createObjectURL(b); src.value = objectUrl; }
  else src.value = '';
}, { immediate: true });
</script>
