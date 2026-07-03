<x-layouts.share :title="__('share.title')">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $share->title ?: __('share.untitled') }}</h1>
        <article class="prose prose-sm mt-6 max-w-none text-gray-800">{!! $html !!}</article>
        <p class="mt-10 border-t border-gray-100 pt-4 text-xs text-gray-400">{{ __('share.read_only_notice') }}</p>
    </div>
</x-layouts.share>
