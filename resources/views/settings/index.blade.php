<x-layouts.app title="Settings">
    <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
    <p class="mt-1 text-sm text-gray-600">Manage your team's configuration.</p>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('settings.tags.index') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">Tags</h2>
            <p class="mt-1 text-sm text-gray-600">Add, rename, colour and delete the tags used on projects and files.</p>
        </a>
        <a href="{{ route('settings.teams.index') }}"
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <h2 class="text-base font-semibold text-gray-900">Teams</h2>
            <p class="mt-1 text-sm text-gray-600">View your teams and members, set the default, and move data between teams.</p>
        </a>
    </div>
</x-layouts.app>
