<x-layouts.share :title="$book->name">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $book->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('shares.public_addressbook_hint') }}</p>
            </div>
            <a href="{{ route('public-share.vcf', $share->token) }}"
                class="shrink-0 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('shares.public_download_vcf') }}</a>
        </div>

        <ul class="mt-6 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            @forelse ($contacts as $contact)
                <li class="px-4 py-3">
                    <p class="text-sm font-medium text-gray-900">{{ $contact->fn ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: '—' }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $contact->org }}
                        @foreach (($contact->emails ?? []) as $e)
                            <span class="ml-1">{{ is_array($e) ? ($e['value'] ?? '') : $e }}</span>
                        @endforeach
                        @foreach (($contact->phones ?? []) as $p)
                            <span class="ml-1">{{ is_array($p) ? ($p['value'] ?? '') : $p }}</span>
                        @endforeach
                    </p>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-gray-500">{{ __('shares.public_no_contacts') }}</li>
            @endforelse
        </ul>
    </div>
</x-layouts.share>
