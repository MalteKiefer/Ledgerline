<x-layouts.app :title="__('timeentries.edit.title')">
    <p class="text-sm text-gray-500"><a href="{{ route('finance.time-entries.index') }}" class="hover:underline">{{ __('timeentries.edit.breadcrumb') }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('timeentries.edit.heading') }}</h1>

    <form method="POST" action="{{ route('finance.time-entries.update', $entry) }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('time-entries._form')
        <div class="mt-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('timeentries.edit.submit') }}</button>
                <a href="{{ route('finance.time-entries.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('timeentries.edit.cancel') }}</a>
            </div>
        </div>
    </form>

    <div class="mt-3">
        <x-confirm-action :action="route('finance.time-entries.destroy', $entry)" method="DELETE"
            :trigger="__('timeentries.edit.delete')"
            trigger-class="text-sm text-red-600 hover:text-red-800"
            :message="__('timeentries.edit.confirm_delete')" />
    </div>
</x-layouts.app>
