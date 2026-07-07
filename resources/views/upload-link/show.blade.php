<x-layouts.minimal :title="__('upload_links.title')">
    <div x-data="uploadLink({
            url: '{{ route('upload-link.upload', $token) }}',
            token: '{{ csrf_token() }}',
            maxBytes: {{ (int) $maxMb * 1024 * 1024 }},
            extensions: @js($extensions),
         }, {
            typeNotAllowed: @js(__('upload_links.type_not_allowed')),
            tooLarge: @js(__('upload_links.too_large')),
            failed: @js(__('upload_links.upload_failed')),
         })"
         @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
         @drop.prevent="dragging = false; add($event.dataTransfer.files)"
         class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm">

        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="@js($label ?: __('upload_links.title'))"></h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('upload_links.intro') }}</p>
        @if (! empty($extensions))
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('upload_links.allowed_types') }}: {{ implode(', ', array_map(fn ($e) => '.'.$e, $extensions)) }}</p>
        @endif

        {{-- Drop zone --}}
        <label class="mt-4 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-10 text-center transition"
            :class="dragging ? 'border-gray-500 bg-gray-50 dark:bg-gray-800' : 'border-gray-300 dark:border-gray-700'">
            <x-icon name="arrow-up-tray" class="h-8 w-8 text-gray-400" />
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('upload_links.drop_or_choose') }}</span>
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('upload_links.max_size', ['mb' => (int) $maxMb]) }}</span>
            <input type="file" multiple class="hidden" @change="add($event.target.files); $event.target.value = ''">
        </label>

        {{-- Per-file results --}}
        <div x-show="items.length" x-cloak class="mt-4 space-y-2">
            <template x-for="(u, i) in items" :key="i">
                <div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="truncate text-gray-700 dark:text-gray-300" x-text="u.name"></span>
                        <span class="shrink-0" :class="{'text-green-600 dark:text-green-400': u.state==='done', 'text-red-600 dark:text-red-400': u.state==='error', 'text-gray-500 dark:text-gray-400': u.state==='uploading'||u.state==='pending'}">
                            <template x-if="u.state==='done'"><x-icon name="check" class="h-4 w-4" /></template>
                            <template x-if="u.state==='error'"><span x-text="u.error || '✕'"></span></template>
                            <span x-show="u.state==='uploading'" x-text="u.progress + '%'"></span>
                            <span x-show="u.state==='pending'">…</span>
                        </span>
                    </div>
                    <div class="mt-1 h-1.5 w-full rounded bg-gray-100 dark:bg-gray-800">
                        <div class="h-1.5 rounded transition-all" :class="{'bg-green-500': u.state==='done', 'bg-red-500': u.state==='error', 'bg-gray-800': u.state==='uploading'||u.state==='pending'}"
                            :style="`width: ${u.state==='pending' ? 4 : (u.state==='uploading' ? u.progress : 100)}%`"></div>
                    </div>
                </div>
            </template>
            <p x-show="! busy" x-cloak class="pt-1 text-sm font-medium text-gray-700 dark:text-gray-300"
               x-text="'{{ __('upload_links.summary', ['ok' => '__OK__', 'fail' => '__FAIL__']) }}'.replace('__OK__', doneCount).replace('__FAIL__', errorCount)"></p>
        </div>
    </div>
</x-layouts.minimal>
