<x-layouts.app :title="$file->displayTitle">
    @php
        $formatBytes = static function (int $bytes): string {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = 0;
            $value = (float) $bytes;
            while ($value >= 1024 && $i < count($units) - 1) {
                $value /= 1024;
                $i++;
            }
            return number_format($value, $i > 0 ? 1 : 0).' '.$units[$i];
        };
        $attached = $file->attachable;
    @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('files.index') }}" class="hover:underline">{{ __('files.breadcrumb_files') }}</a>
        @if ($attached instanceof \App\Models\Customer)
            <span aria-hidden="true">/</span>
            <a href="{{ route('customers.show', $attached) }}" class="hover:underline">{{ $attached->name }}</a>
        @elseif ($attached instanceof \App\Models\Project)
            <span aria-hidden="true">/</span>
            <a href="{{ route('projects.show', $attached) }}" class="hover:underline">{{ $attached->name }}</a>
        @endif
    </p>

    <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $file->displayTitle }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('files.download', $file) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.download') }}</a>
            @php $linkedInvoice = $file->attachable instanceof \App\Models\Invoice ? $file->attachable : null; @endphp
            @if ($linkedInvoice)
                <div x-data="{ open: false }" class="inline">
                    <button type="button" @click="open = true"
                        class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('files.delete') }}</button>
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                        <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>
                        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                            <h3 class="text-base font-semibold text-gray-900">{{ __('files.delete_invoice_title') }}</h3>
                            <p class="mt-2 text-sm text-gray-600">{{ __('files.delete_invoice_warning', ['number' => $linkedInvoice->number ?? ('#'.$linkedInvoice->id)]) }}</p>
                            <div class="mt-5 flex justify-end gap-3">
                                <button type="button" @click="open = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.cancel') }}</button>
                                <form method="POST" action="{{ route('files.destroy', $file) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('files.delete_invoice_confirm') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <x-confirm-action :action="route('files.destroy', $file)" method="DELETE"
                    :trigger="__('files.delete')" :message="__('files.delete_file_confirm')" />
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Preview + metadata --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                @if ($file->isImage())
                    <img src="{{ route('files.download', $file) }}" alt="{{ $file->displayTitle }}"
                        class="mx-auto max-h-[520px] rounded object-contain">
                @elseif ($file->mime_type === 'application/pdf')
                    <div class="mb-3 flex justify-end">
                        <a href="{{ route('files.download', $file) }}" target="_blank" rel="noopener"
                            class="text-sm text-gray-600 hover:text-gray-900">{{ __('files.open_in_new_tab') }}</a>
                    </div>
                    {{-- <object> renders the PDF inline; if framing is blocked (e.g. a
                         proxy forcing X-Frame-Options), the fallback link is shown. --}}
                    <object data="{{ route('files.download', $file) }}" type="application/pdf"
                        class="h-[600px] w-full rounded">
                        <p class="py-10 text-center text-sm text-gray-500">
                            {{ __('files.inline_preview_unavailable') }}
                            <a href="{{ route('files.download', $file) }}" target="_blank" rel="noopener"
                                class="text-gray-900 underline">{{ __('files.open_pdf_new_tab') }}</a>.
                        </p>
                    </object>
                @else
                    <p class="py-10 text-center text-sm text-gray-500">
                        {{ __('files.no_inline_preview') }}
                        <a href="{{ route('files.download', $file) }}" class="text-gray-900 underline">{{ __('files.download') }}</a> {{ __('files.to_view') }}
                    </p>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.original_name') }}</dt>
                        <dd class="mt-1 break-all text-sm text-gray-900">{{ $file->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.col_type') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $file->type->label() }} · {{ $file->mime_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.size') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $formatBytes($file->size) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.encrypted') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $file->is_encrypted ? __('files.yes') : __('files.no') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.uploaded_by') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $file->uploader?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.uploaded') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $file->created_at?->format('Y-m-d H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.checksum') }}</dt>
                        <dd class="mt-1 break-all font-mono text-xs text-gray-600">{{ $file->checksum ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.tags') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @forelse ($file->tags as $tag)
                                <x-tag-chip :tag="$tag" :href="route('files.index', ['tag' => $tag->slug])" class="mr-1" />
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </div>

            @if (! empty($exif) && ! empty($exif['fields']))
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('files.exif') }}</h2>
                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                        @foreach ($exif['fields'] as $label => $value)
                            <div class="flex justify-between gap-4 text-sm">
                                <dt class="text-gray-500">{{ $label }}</dt>
                                <dd class="text-right text-gray-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if (! empty($location))
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-3">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('files.location') }}</h2>
                        @if ($location['address'])
                            <p class="mt-1 text-sm text-gray-600">{{ $location['address'] }}</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">{{ number_format($location['lat'], 5) }}, {{ number_format($location['lon'], 5) }}</p>
                    </div>
                    <iframe title="{{ __('files.location') }}" loading="lazy" class="h-72 w-full border-0"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $location['lon'] - 0.01 }}%2C{{ $location['lat'] - 0.01 }}%2C{{ $location['lon'] + 0.01 }}%2C{{ $location['lat'] + 0.01 }}&amp;layer=mapnik&amp;marker={{ $location['lat'] }}%2C{{ $location['lon'] }}"></iframe>
                    <div class="px-6 py-2 text-right">
                        <a href="https://www.openstreetmap.org/?mlat={{ $location['lat'] }}&amp;mlon={{ $location['lon'] }}#map=16/{{ $location['lat'] }}/{{ $location['lon'] }}"
                            target="_blank" rel="noopener" class="text-xs text-gray-500 hover:text-gray-700">{{ __('files.view_larger_map') }} ↗</a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Editable metadata --}}
        <div>
            <form method="POST" action="{{ route('files.update', $file) }}"
                class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')
                <h2 class="text-sm font-semibold text-gray-900">{{ __('files.details') }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">{{ __('files.title_label') }}</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $file->title) }}"
                            placeholder="{{ $file->name }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('files.description') }}</label>
                        <textarea id="description" name="description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">{{ old('description', $file->description) }}</textarea>
                        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="note" class="block text-sm font-medium text-gray-700">{{ __('files.note') }}</label>
                        <textarea id="note" name="note" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">{{ old('note', $file->note) }}</textarea>
                        @error('note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="folder_id" class="block text-sm font-medium text-gray-700">{{ __('files.folder') }}</label>
                        <select id="folder_id" name="folder_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                            <option value="">{{ __('files.folder_none') }}</option>
                            @foreach ($folders as $f)
                                <option value="{{ $f->id }}" @selected((int) old('folder_id', $file->folder_id) === $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                        class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
