{{-- Scan-scope selector: whole library, or only the N most-recently-added
     photos. Bound to `scanLimit` (0 = all), shared by faces + duplicates. --}}
<select x-model.number="scanLimit"
    class="rounded-lg border-black/[0.06] bg-white py-1.5 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-accent focus:ring-accent dark:border-white/10 dark:bg-[#1c1c1e] dark:text-gray-300"
  <option value="0">{{ __('gallery.scope_all') }}</option>
  <option value="25">{{ __('gallery.scope_last', ['count' => 25]) }}</option>
  <option value="50">{{ __('gallery.scope_last', ['count' => 50]) }}</option>
  <option value="100">{{ __('gallery.scope_last', ['count' => 100]) }}</option>
  <option value="200">{{ __('gallery.scope_last', ['count' => 200]) }}</option>
</select>
