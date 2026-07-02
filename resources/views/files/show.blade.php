<x-layouts.app :title="$file->displayTitle">
    @php
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
        <h1 class="flex items-center gap-2 text-2xl font-semibold text-gray-900 break-all">
            <x-lock-badge :encrypted="$file->is_encrypted" class="h-5 w-5" />
            @if ($file->is_encrypted)
                <span x-data="encName(@js($file->enc_metadata), @js(__('files.encrypted')))" x-text="label"></span>
            @else
                {{ $file->displayTitle }}
            @endif
        </h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('files.edit', $file) }}"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.edit') }}</a>
            @if ($file->is_encrypted)
                <button type="button" @click="window.vaultDownload(@js(route('files.download', $file)), @js($file->enc_metadata), @js($file->enc_file_key))"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.download') }}</button>
            @else
                <a href="{{ route('files.download', $file) }}"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.download') }}</a>
            @endif
            <x-confirm-action :action="route('files.destroy', $file)" method="DELETE"
                :trigger="__('files.delete')" :message="__('files.delete_file_confirm')" />
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Preview + metadata --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                @if ($file->is_encrypted)
                    <div x-data="encPreview(@js(route('files.download', $file)), @js($file->enc_metadata), @js($file->enc_file_key))">
                        <p x-show="state === 'loading'" class="py-10 text-center text-sm text-gray-500">{{ __('files.decrypting') }}</p>
                        <p x-show="state === 'locked'" x-cloak class="py-10 text-center text-sm text-gray-500">{{ __('files.encrypted_locked') }}</p>
                        <p x-show="state === 'error'" x-cloak class="py-10 text-center text-sm text-red-600">{{ __('files.encrypted_locked') }}</p>
                        <img x-show="state === 'image'" x-cloak :src="src" :alt="name" class="mx-auto max-h-[520px] rounded object-contain">
                        <template x-if="state === 'pdf'">
                            <object :data="src" type="application/pdf" class="h-[600px] w-full rounded"></object>
                        </template>
                        <div x-show="state === 'none'" x-cloak class="py-10 text-center text-sm text-gray-500">
                            {{ __('files.encrypted_no_preview') }}
                            <button type="button" @click="download()" class="text-gray-900 underline">{{ __('files.download') }}</button>
                        </div>
                    </div>
                @elseif ($file->isImage())
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
                        @if ($file->is_encrypted)
                            <dd class="mt-1 break-all text-sm text-gray-900" x-data="encName(@js($file->enc_metadata), @js(__('files.encrypted')))" x-text="label"></dd>
                        @else
                            <dd class="mt-1 break-all text-sm text-gray-900">{{ $file->name }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.col_type') }}</dt>
                        @if ($file->is_encrypted)
                            @php $fileTypeLabels = collect(\App\Enums\FileType::cases())->mapWithKeys(fn (\App\Enums\FileType $c): array => [$c->value => $c->label()]); @endphp
                            <dd class="mt-1 text-sm text-gray-900" x-data="encMime(@js($file->enc_metadata), @js($fileTypeLabels))" x-text="label"></dd>
                        @else
                            <dd class="mt-1 text-sm text-gray-900">{{ $file->type->label() }} · {{ $file->mime_type }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('files.size') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900"><x-file-size :bytes="$file->size" /></dd>
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
                        <select id="folder_id" name="folder_id" x-data="encOptions()"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                            <option value="">{{ __('files.folder_none') }}</option>
                            @foreach ($folders as $f)
                                <option value="{{ $f->id }}" @selected((int) old('folder_id', $file->folder_id) === $f->id)
                                    @if ($f->enc_name) data-enc="{{ $f->enc_name }}" @endif>{{ $f->enc_name ? '…' : $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-tag-input name="tags" :value="old('tags', $file->tags->pluck('name')->all())" :suggestions="$tagSuggestions" :label="__('files.tags')" />
                        @error('tags')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit"
                        class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
