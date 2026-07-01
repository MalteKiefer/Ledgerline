<x-layouts.app title="Settings">
    <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
    <p class="mt-1 text-sm text-gray-600">Manage your workspace configuration.</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('settings.company.edit') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">Company profile</h2>
            <p class="mt-1 text-sm text-gray-600">Your company details used as the sender on invoices.</p>
        </a>
        <a href="{{ route('settings.tags.index') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">Tags</h2>
            <p class="mt-1 text-sm text-gray-600">Add, rename, colour and delete the tags used on projects and files.</p>
        </a>
        <a href="{{ route('settings.units.index') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">Units</h2>
            <p class="mt-1 text-sm text-gray-600">Multilingual unit types (hour, day, piece…) used on invoice line items.</p>
        </a>
    </div>
</x-layouts.app>
