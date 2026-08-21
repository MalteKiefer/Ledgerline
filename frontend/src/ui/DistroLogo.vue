<template>
  <span
    class="inline-flex shrink-0 items-center justify-center rounded-lg"
    :style="{ width: `${size}px`, height: `${size}px`, background: mark.color + '1f' }"
    :title="title ?? undefined"
  >
    <svg
      :viewBox="DISTRO_VIEWBOX"
      :width="Math.round(size * 0.62)"
      :height="Math.round(size * 0.62)"
      :fill="mark.color"
      aria-hidden="true"
      role="img"
    >
      <path :d="mark.path" />
    </svg>
  </span>
</template>

<script setup lang="ts">
/**
 * The distribution mark for a host, so the fleet is scannable by shape and
 * colour rather than by reading twenty os-release strings.
 *
 * Tinted background rather than a bare glyph: brand colours vary wildly in
 * contrast (Alpine's navy against Ubuntu's orange), and a low-opacity tile makes
 * them all legible on either theme without recolouring anyone's mark.
 */
import { computed } from 'vue';
import { DISTRO_VIEWBOX, distroMark } from '@spa/lib/distro-logos';

const props = withDefaults(
  defineProps<{ id?: string | null; idLike?: string | null; size?: number; title?: string | null }>(),
  { id: null, idLike: null, size: 32, title: null },
);

const mark = computed(() => distroMark(props.id, props.idLike));
</script>
