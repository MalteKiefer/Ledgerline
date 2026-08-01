{{-- Renders every category of a receipt as a colour+icon pill.
     $list = a JS expression evaluating to an array of category-name strings
     (e.g. "catList(doc.r)"). Renders nothing when empty. --}}
<span class="inline-flex flex-wrap items-center gap-1">
  <template x-for="cat in ({{ $list }})" :key="cat">
    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
          :style="'background:'+catColor(cat)+'1f; color:'+catColor(cat)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="catIconPath(catIcon(cat))"></path></svg>
      <span x-text="cat"></span>
    </span>
  </template>
</span>
