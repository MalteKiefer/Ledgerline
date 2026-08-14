<template>
  <div ref="hostEl" class="ll-chart w-full" :style="{ height: height + 'px' }" />
</template>

<script setup lang="ts">
// Thin, lazy-loading uPlot wrapper. uPlot (+ its CSS) is only fetched when a chart
// is actually mounted, keeping it out of the main bundle. Re-creates the chart
// whenever `data`/`options` change (cheap enough for the small series this app
// renders — a handful of months/categories, not a live-tick time series).
import { ref, shallowRef, watch, onMounted, onBeforeUnmount } from 'vue';
import type { default as UplotType, Options, AlignedData } from 'uplot';

const props = defineProps<{
  data: AlignedData;
  options: Omit<Options, 'width' | 'height'>;
  height?: number;
  // Render the (single) data series as bars instead of uPlot's default line —
  // needs the loaded uPlot instance (uPlot.paths.bars), so it's applied inside
  // build() rather than requiring the caller to import uPlot eagerly.
  bars?: boolean;
}>();

const hostEl = ref<HTMLDivElement | null>(null);
const height = props.height ?? 220;
const chart = shallowRef<UplotType | null>(null);
let ro: ResizeObserver | null = null;

async function build() {
  if (!hostEl.value) return;
  chart.value?.destroy();
  chart.value = null;
  const [{ default: uPlot }] = await Promise.all([
    import('uplot'),
    import('uplot/dist/uPlot.min.css'),
  ]);
  const width = hostEl.value.clientWidth || 320;
  const series = props.bars
    ? props.options.series?.map((s, i) => (i === 0 ? s : { ...s, paths: uPlot.paths.bars!({ size: [0.6, 100] }) }))
    : props.options.series;
  chart.value = new uPlot({ ...props.options, series, width, height }, props.data, hostEl.value);
}

onMounted(async () => {
  await build();
  if (hostEl.value) {
    ro = new ResizeObserver(() => {
      if (chart.value && hostEl.value) chart.value.setSize({ width: hostEl.value.clientWidth || 320, height });
    });
    ro.observe(hostEl.value);
  }
});
onBeforeUnmount(() => { ro?.disconnect(); chart.value?.destroy(); });
watch(() => [props.data, props.options], () => { void build(); }, { deep: true });
</script>

<style>
/* MD3-ish neutral chart chrome — no seed colours here, series colours come from
   the caller's options (this app's charts are single/dual-series, not a big
   categorical palette). */
.ll-chart .u-legend { font-size: 0.75rem; }
</style>
