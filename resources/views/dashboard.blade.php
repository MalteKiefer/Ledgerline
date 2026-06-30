<x-layouts.app title="Dashboard">
    <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
    <p class="mt-1 text-sm text-gray-600">Overview of your ERP records.</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <dt class="text-sm font-medium text-gray-500">Customers</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['customers'] }}</dd>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <dt class="text-sm font-medium text-gray-500">Projects</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['projects'] }}</dd>
        </div>
    </div>
</x-layouts.app>
