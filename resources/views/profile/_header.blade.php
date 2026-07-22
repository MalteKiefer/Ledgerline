{{-- iOS-style sub-page chrome: a back link to the profile hub + page title. --}}
<a href="{{ route('profile') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-accent transition hover:opacity-80">
    <x-icon name="chevron-left" class="h-4 w-4" />{{ __('account.back') }}
</a>
<h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
@isset($subtitle)
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
@endisset
