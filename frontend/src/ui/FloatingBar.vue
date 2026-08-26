<template>
  <!-- Teleported to the body: inside the list it would inherit an overflow or a
       transform from some ancestor and stop being fixed, which is the failure
       this component exists to avoid. -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out" enter-from-class="translate-y-3 opacity-0"
      leave-active-class="transition duration-100 ease-in" leave-to-class="translate-y-3 opacity-0"
    >
      <!-- Centred by a full-width wrapper rather than by translating the bar:
           a transform makes the bar the containing block for any `fixed`
           descendant, so a menu or a click-catcher inside it would be measured
           against the bar instead of the viewport. The wrapper ignores clicks
           so the page underneath stays usable either side of the bar. -->
      <div v-if="show" class="pointer-events-none fixed inset-x-0 bottom-5 z-[1400] flex justify-center px-3">
        <div
          class="pointer-events-auto flex max-w-full flex-wrap items-center justify-center gap-2 rounded-2xl border border-[var(--ll-border)] bg-[var(--ll-elevated)] px-3 py-2 shadow-2xl"
          role="toolbar" :aria-label="label"
        >
          <!-- Wraps rather than scrolls: a scroll container clips anything a
               button opens upward, which is most of what these buttons do. -->
          <span v-if="label" class="whitespace-nowrap px-1 text-sm font-medium">{{ label }}</span>
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
/**
 * Actions for a selection, floating above the page.
 *
 * A selection bar in the normal flow scrolls out of sight, and a selection is
 * made by scrolling — so by the time you have picked what you wanted, the
 * buttons are gone. This sits at the bottom of the viewport instead, where it
 * stays reachable however far down the list goes.
 *
 * Bottom rather than top: it is nearer the pointer after a drag or a range
 * click, and it does not cover the row that was just clicked.
 */
defineProps<{
  show: boolean;
  /** Usually the count, e.g. "12 selected" — rendered before the buttons. */
  label?: string;
}>();
</script>
