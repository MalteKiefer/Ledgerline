<x-layouts.app :title="__('branches.edit.title')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $branch->customer_id) }}" class="hover:underline">
            {{ $branch->customer->name }}
        </a>
        <span aria-hidden="true">/</span> {{ __('branches.edit.breadcrumb') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('branches.edit.heading') }}</h1>

    <form method="POST" action="{{ route('branches.update', $branch) }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @include('branches._form')

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                {{ __('branches.edit.submit') }}
            </button>
            <a href="{{ route('branches.show', $branch) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('branches.edit.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
