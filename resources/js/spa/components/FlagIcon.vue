<template>
  <span class="inline-block overflow-hidden rounded-[2px] align-middle ring-1 ring-black/10" :style="{ width: w + 'px', height: h + 'px' }">
    <svg :viewBox="`0 0 30 20`" :width="w" :height="h" preserveAspectRatio="none" aria-hidden="true">
      <template v-if="spec">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <g v-if="spec.svg" v-html="spec.svg" />
        <template v-else-if="spec.h">
          <rect v-for="(c, i) in spec.h" :key="'h'+i" x="0" :y="i * (20 / spec.h.length)" width="30" :height="20 / spec.h.length" :fill="c" />
        </template>
        <template v-else-if="spec.v">
          <rect v-for="(c, i) in spec.v" :key="'v'+i" :x="i * (30 / spec.v.length)" y="0" :width="30 / spec.v.length" height="20" :fill="c" />
        </template>
      </template>
      <template v-else>
        <rect x="0" y="0" width="30" height="20" fill="#e5e7eb" />
        <text x="15" y="14" text-anchor="middle" font-size="9" font-family="sans-serif" fill="#6b7280">{{ iso }}</text>
      </template>
    </svg>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

// Flat 2-D flags drawn procedurally (no external assets / no dependency — the
// package registry is unreachable in this environment). Simple striped flags
// are generated from a colour list; a handful of iconic flags use inline SVG.
// Anything not in the table falls back to a neutral ISO-code chip (still flat).
const props = defineProps<{ iso: string; size?: number }>();

interface Spec { h?: string[]; v?: string[]; svg?: string }
const FLAGS: Record<string, Spec> = {
  // Horizontal tricolours / bicolours
  DE: { h: ['#000000', '#DD0000', '#FFCE00'] },
  AT: { h: ['#C8102E', '#FFFFFF', '#C8102E'] },
  NL: { h: ['#AE1C28', '#FFFFFF', '#21468B'] },
  RU: { h: ['#FFFFFF', '#0039A6', '#D52B1E'] },
  HU: { h: ['#CD2A3E', '#FFFFFF', '#436F4D'] },
  BG: { h: ['#FFFFFF', '#00966E', '#D62612'] },
  LT: { h: ['#FDB913', '#006A44', '#C1272D'] },
  EE: { h: ['#0072CE', '#000000', '#FFFFFF'] },
  LV: { h: ['#9E3039', '#FFFFFF', '#9E3039'] },
  LU: { h: ['#ED2939', '#FFFFFF', '#00A1DE'] },
  YE: { h: ['#CE1126', '#FFFFFF', '#000000'] },
  AM: { h: ['#D90012', '#0033A0', '#F2A800'] },
  CO: { h: ['#FCD116', '#FCD116', '#003893', '#CE1126'] },
  VE: { h: ['#FFCC00', '#00247D', '#CF142B'] },
  BO: { h: ['#D52B1E', '#F9E300', '#007A33'] },
  ES: { h: ['#AA151B', '#F1BF00', '#F1BF00', '#AA151B'] },
  AR: { h: ['#74ACDF', '#FFFFFF', '#74ACDF'] },
  IN: { h: ['#FF9933', '#FFFFFF', '#138808'] },
  PL: { h: ['#FFFFFF', '#DC143C'] },
  UA: { h: ['#0057B7', '#FFD700'] },
  ID: { h: ['#CE1126', '#FFFFFF'] },
  MC: { h: ['#CE1126', '#FFFFFF'] },
  // Vertical tricolours
  FR: { v: ['#002395', '#FFFFFF', '#ED2939'] },
  IT: { v: ['#009246', '#FFFFFF', '#CE2B37'] },
  BE: { v: ['#000000', '#FDDA24', '#EF3340'] },
  IE: { v: ['#169B62', '#FFFFFF', '#FF883E'] },
  RO: { v: ['#002B7F', '#FCD116', '#CE1126'] },
  MX: { v: ['#006847', '#FFFFFF', '#CE1126'] },
  NG: { v: ['#008751', '#FFFFFF', '#008751'] },
  PE: { v: ['#D91023', '#FFFFFF', '#D91023'] },
  GN: { v: ['#CE1126', '#FCD116', '#009460'] },
  ML: { v: ['#14B53A', '#FCD116', '#CE1126'] },
  CI: { v: ['#F77F00', '#FFFFFF', '#009E60'] },
  PT: { v: ['#046A38', '#046A38', '#DA020E', '#DA020E', '#DA020E'] },
  // Iconic flags as inline SVG (simplified but recognisable)
  US: { svg: '<rect width="30" height="20" fill="#B22234"/><g fill="#fff"><rect y="1.5" width="30" height="1.5"/><rect y="4.6" width="30" height="1.5"/><rect y="7.7" width="30" height="1.5"/><rect y="10.8" width="30" height="1.5"/><rect y="13.9" width="30" height="1.5"/><rect y="17" width="30" height="1.5"/></g><rect width="13" height="10.7" fill="#3C3B6E"/>' },
  GB: { svg: '<rect width="30" height="20" fill="#012169"/><path d="M0,0 30,20 M30,0 0,20" stroke="#fff" stroke-width="4"/><path d="M0,0 30,20 M30,0 0,20" stroke="#C8102E" stroke-width="2"/><path d="M15,0 V20 M0,10 H30" stroke="#fff" stroke-width="6"/><path d="M15,0 V20 M0,10 H30" stroke="#C8102E" stroke-width="3.5"/>' },
  CH: { svg: '<rect width="30" height="20" fill="#D52B1E"/><rect x="13" y="5" width="4" height="10" fill="#fff"/><rect x="10" y="8" width="10" height="4" fill="#fff"/>' },
  JP: { svg: '<rect width="30" height="20" fill="#fff"/><circle cx="15" cy="10" r="6" fill="#BC002D"/>' },
  KR: { svg: '<rect width="30" height="20" fill="#fff"/><circle cx="15" cy="10" r="4.5" fill="#CD2E3A"/><path d="M15,5.5 a4.5,4.5 0 0,1 0,9 a2.25,2.25 0 0,1 0,-4.5 a2.25,2.25 0 0,0 0,-4.5" fill="#0047A0"/>' },
  CN: { svg: '<rect width="30" height="20" fill="#DE2910"/><text x="7" y="9" font-size="6" fill="#FFDE00">★</text>' },
  DK: { svg: '<rect width="30" height="20" fill="#C60C30"/><rect x="9" y="0" width="4" height="20" fill="#fff"/><rect x="0" y="8" width="30" height="4" fill="#fff"/>' },
  SE: { svg: '<rect width="30" height="20" fill="#006AA7"/><rect x="9" y="0" width="4" height="20" fill="#FECC00"/><rect x="0" y="8" width="30" height="4" fill="#FECC00"/>' },
  NO: { svg: '<rect width="30" height="20" fill="#EF2B2D"/><rect x="8" y="0" width="6" height="20" fill="#fff"/><rect x="0" y="7" width="30" height="6" fill="#fff"/><rect x="9.5" y="0" width="3" height="20" fill="#002868"/><rect x="0" y="8.5" width="30" height="3" fill="#002868"/>' },
  FI: { svg: '<rect width="30" height="20" fill="#fff"/><rect x="8" y="0" width="5" height="20" fill="#003580"/><rect x="0" y="7.5" width="30" height="5" fill="#003580"/>' },
  IS: { svg: '<rect width="30" height="20" fill="#02529C"/><rect x="8" y="0" width="6" height="20" fill="#fff"/><rect x="0" y="7" width="30" height="6" fill="#fff"/><rect x="9.5" y="0" width="3" height="20" fill="#DC1E35"/><rect x="0" y="8.5" width="30" height="3" fill="#DC1E35"/>' },
  GR: { svg: '<rect width="30" height="20" fill="#0D5EAF"/><g fill="#fff"><rect y="2.2" width="30" height="2.2"/><rect y="6.6" width="30" height="2.2"/><rect y="11" width="30" height="2.2"/><rect y="15.4" width="30" height="2.2"/></g><rect width="9" height="11" fill="#0D5EAF"/><rect x="3.5" y="0" width="2" height="11" fill="#fff"/><rect x="0" y="4.5" width="9" height="2" fill="#fff"/>' },
  TR: { svg: '<rect width="30" height="20" fill="#E30A17"/><circle cx="12" cy="10" r="4.5" fill="#fff"/><circle cx="13.5" cy="10" r="3.6" fill="#E30A17"/><text x="17" y="12.5" font-size="6" fill="#fff">★</text>' },
  BR: { svg: '<rect width="30" height="20" fill="#009C3B"/><path d="M15,2 28,10 15,18 2,10Z" fill="#FEDF00"/><circle cx="15" cy="10" r="4" fill="#002776"/>' },
  CA: { svg: '<rect width="30" height="20" fill="#fff"/><rect width="7.5" height="20" fill="#FF0000"/><rect x="22.5" width="7.5" height="20" fill="#FF0000"/><text x="15" y="14" text-anchor="middle" font-size="10" fill="#FF0000">🍁</text>' },
  AU: { svg: '<rect width="30" height="20" fill="#00008B"/><rect width="13" height="10.7" fill="#012169"/><path d="M0,0 13,10.7 M13,0 0,10.7" stroke="#fff" stroke-width="2"/><path d="M6.5,0 V10.7 M0,5.35 H13" stroke="#fff" stroke-width="3"/><path d="M6.5,0 V10.7 M0,5.35 H13" stroke="#C8102E" stroke-width="1.5"/>' },
  NZ: { svg: '<rect width="30" height="20" fill="#00247D"/><rect width="13" height="10.7" fill="#012169"/><path d="M0,0 13,10.7 M13,0 0,10.7" stroke="#fff" stroke-width="2"/><path d="M6.5,0 V10.7 M0,5.35 H13" stroke="#fff" stroke-width="3"/><path d="M6.5,0 V10.7 M0,5.35 H13" stroke="#C8102E" stroke-width="1.5"/>' },
};

const spec = computed<Spec | null>(() => FLAGS[(props.iso || '').toUpperCase()] ?? null);
const iso = computed(() => (props.iso || '').toUpperCase());
const h = computed(() => props.size ?? 14);
const w = computed(() => Math.round((props.size ?? 14) * 1.5));
</script>
