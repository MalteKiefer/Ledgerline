<x-layouts.app :title="__('downloads.heading')">
    @php $dlLabels = [
        'dataUrl' => route('downloads.data'),
        'destroyUrl' => route('downloads.destroy'),
        'confirmDelete' => __('downloads.confirm_delete'),
        'status' => [
            'queued' => __('downloads.status.queued'),
            'processing' => __('downloads.status.processing'),
            'ready' => __('downloads.status.ready'),
            'failed' => __('downloads.status.failed'),
        ],
        'source' => ['gallery' => __('downloads.source.gallery'), 'files' => __('downloads.source.files')],
        'variant' => ['original' => __('downloads.variant.original'), 'edited' => __('downloads.variant.edited')],
        'expires' => __('downloads.expires', ['when' => '__W__']),
    ]; @endphp
    <div x-data="downloadsPage(@js($dlLabels))" x-init="init()">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('downloads.heading') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('downloads.subheading') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('settings.downloads.edit') }}"
                    class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    {{ __('downloads.settings') }}
                </a>
                <button type="button" @click="load()"
                    class="rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    {{ __('downloads.refresh') }}
                </button>
            </div>
        </div>

        {{-- Bulk bar --}}
        <div x-show="selected.length" x-cloak class="mt-4 flex items-center justify-between rounded-md bg-gray-900 px-4 py-2 text-sm text-white">
            <span x-text="'{{ __('downloads.selected', ['count' => '__C__']) }}'.replace('__C__', selected.length)"></span>
            <button type="button" @click="destroySelected()" class="rounded bg-red-600 px-3 py-1 font-medium hover:bg-red-500">
                {{ __('downloads.delete_selected') }}
            </button>
        </div>

        <template x-if="!loading && exports.length === 0">
            <div class="mt-8 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-10 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('downloads.empty') }}
            </div>
        </template>

        <div class="mt-4 space-y-3">
            <template x-for="e in exports" :key="e.id">
                <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" :value="e.id" x-model.number="selected"
                            class="mt-1 rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100" x-text="e.title"></span>
                                <span class="rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-400" x-text="sourceLabel(e)"></span>
                                <span class="rounded px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400': e.status==='queued',
                                        'bg-blue-100 text-blue-700': e.status==='processing',
                                        'bg-green-100 text-green-700 dark:text-green-300': e.status==='ready',
                                        'bg-red-100 text-red-700 dark:text-red-300': e.status==='failed',
                                    }" x-text="statusLabel(e.status)"></span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="metaLine(e)"></span>
                            </div>

                            {{-- Ready: download each part --}}
                            <template x-if="e.status==='ready'">
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <template x-for="p in e.parts" :key="p.index">
                                        <a :href="'{{ url('downloads') }}/'+e.id+'/parts/'+p.index"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-xs font-medium text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-white">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                            <span x-text="e.parts.length > 1 ? '{{ __('downloads.part', ['n' => '__N__']) }}'.replace('__N__', p.index+1) : '{{ __('downloads.download') }}'"></span>
                                            <span class="opacity-70" x-text="'('+humanSize(p.size)+')'"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            <template x-if="e.status==='processing' || e.status==='queued'">
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ __('downloads.building_hint') }}</p>
                            </template>
                            <template x-if="e.status==='failed'">
                                <p class="mt-2 text-xs text-red-600 dark:text-red-400" x-text="e.error"></p>
                            </template>
                        </div>
                        <button type="button" @click="destroy(e.id)" :title="'{{ __('downloads.delete') }}'"
                            class="shrink-0 rounded p-1.5 text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-red-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-layouts.app>
