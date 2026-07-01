<x-layouts.app title="Profile">
    <h1 class="text-2xl font-semibold text-gray-900">Profile</h1>
    <p class="mt-1 text-sm text-gray-600">
        Your identity is managed by Pocket-ID. These details are read-only and
        refresh each time you sign in.
    </p>

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-4">
            <x-user-avatar :user="$user" size="h-16 w-16" />
            <div>
                <p class="text-lg font-semibold text-gray-900">{{ $user->name }}</p>
                <p class="text-sm text-gray-600">{{ $user->email }}</p>
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($user->email)
                        <a href="mailto:{{ $user->email }}" class="text-gray-900 hover:underline">{{ $user->email }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Email verified</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $user->email_verified_at ? 'Yes, on '.$user->email_verified_at->format('Y-m-d') : 'No' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Pocket-ID subject</dt>
                <dd class="mt-1 break-all font-mono text-sm text-gray-900">{{ $user->oidc_sub ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Avatar</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $user->avatar ? 'Provided by Pocket-ID, stored locally' : 'None' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Account created</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at?->format('Y-m-d H:i') ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Teams</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @forelse ($user->teams->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE) as $team)
                        <span class="mr-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $team->displayName }}</span>
                    @empty
                        —
                    @endforelse
                </dd>
            </div>
        </dl>
    </div>
</x-layouts.app>
