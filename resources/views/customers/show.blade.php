<x-layouts.app :title="$customer->name">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $customer->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('customers.edit', $customer) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Edit
            </a>
            <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                onsubmit="return confirm('Delete this customer? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($customer->email)
                        <a href="mailto:{{ $customer->email }}" class="text-gray-900 hover:underline">{{ $customer->email }}</a>
                    @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($customer->phone)
                        <a href="tel:{{ $customer->phone }}" class="text-gray-900 hover:underline">{{ $customer->phone }}</a>
                    @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Website</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($customer->website)
                        <a href="{{ $customer->website }}" target="_blank" rel="noopener noreferrer"
                            class="text-gray-900 hover:underline">{{ $customer->website }}</a>
                    @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">VAT ID</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->vat_id ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Street</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->street ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Postal code</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->postal_code ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">City</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->city ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Country</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($name = \App\Support\Countries::name($customer->country))
                        {{ \App\Support\Countries::flag($customer->country) }} {{ $name }}
                    @else — @endif
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $customer->notes ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-8" x-data="{ tab: 'contacts' }">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6" role="tablist" aria-label="Customer relations">
                <button type="button" role="tab" id="tab-contacts" aria-controls="panel-contacts"
                    :aria-selected="(tab === 'contacts').toString()" @click="tab = 'contacts'"
                    class="border-b-2 px-1 py-3 text-sm font-medium"
                    :class="tab === 'contacts' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    Contacts ({{ $customer->contacts->count() }})
                </button>
                <button type="button" role="tab" id="tab-projects" aria-controls="panel-projects"
                    :aria-selected="(tab === 'projects').toString()" @click="tab = 'projects'"
                    class="border-b-2 px-1 py-3 text-sm font-medium"
                    :class="tab === 'projects' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    Projects ({{ $customer->projects->count() }})
                </button>
                <button type="button" role="tab" id="tab-branches" aria-controls="panel-branches"
                    :aria-selected="(tab === 'branches').toString()" @click="tab = 'branches'"
                    class="border-b-2 px-1 py-3 text-sm font-medium"
                    :class="tab === 'branches' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    Branches ({{ $customer->branches->count() }})
                </button>
            </nav>
        </div>

        {{-- Contacts panel --}}
        <section id="panel-contacts" role="tabpanel" aria-labelledby="tab-contacts" x-show="tab === 'contacts'" class="mt-4">
            <div class="flex items-center justify-end">
                <a href="{{ route('customers.contacts.create', $customer) }}"
                    class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Add contact
                </a>
            </div>
            <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($customer->contacts->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-gray-500">No contacts yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($customer->contacts as $contact)
                            <li class="px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <a href="{{ route('contacts.show', $contact) }}"
                                        class="font-medium text-gray-900 hover:underline">{{ $contact->name }}</a>
                                    <span class="text-gray-500">{{ $contact->function->label() }}</span>
                                </div>
                                @if ($contact->emails->isNotEmpty() || $contact->phones->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                                        @foreach ($contact->emails as $email)
                                            <a href="mailto:{{ $email->email }}" class="hover:underline">
                                                {{ $email->label }}: {{ $email->email }}
                                            </a>
                                        @endforeach
                                        @foreach ($contact->phones as $phone)
                                            <a href="tel:{{ $phone->phone }}" class="hover:underline">
                                                {{ $phone->label }}: {{ $phone->phone }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Projects panel --}}
        <section id="panel-projects" role="tabpanel" aria-labelledby="tab-projects" x-show="tab === 'projects'" x-cloak class="mt-4">
            <div class="flex items-center justify-end">
                <a href="{{ route('customers.projects.create', $customer) }}"
                    class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Add project
                </a>
            </div>
            <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($customer->projects->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-gray-500">No projects yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($customer->projects as $project)
                            <li class="flex items-center justify-between px-4 py-3">
                                <span>
                                    <a href="{{ route('projects.show', $project) }}"
                                        class="font-medium text-gray-900 hover:underline">{{ $project->name }}</a>
                                    <span class="text-gray-500">— {{ $project->status->label() }}</span>
                                </span>
                                <span class="text-gray-500">{{ $project->reference }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- Branches panel --}}
        <section id="panel-branches" role="tabpanel" aria-labelledby="tab-branches" x-show="tab === 'branches'" x-cloak class="mt-4">
            <div class="flex items-center justify-end">
                <a href="{{ route('customers.branches.create', $customer) }}"
                    class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Add branch
                </a>
            </div>
            <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($customer->branches->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-gray-500">No branches yet.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($customer->branches as $branch)
                            <li class="px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <a href="{{ route('branches.show', $branch) }}"
                                        class="font-medium text-gray-900 hover:underline">{{ $branch->name }}</a>
                                    <span class="text-gray-500">
                                        @if ($flag = \App\Support\Countries::flag((string) $branch->country)){{ $flag }} @endif{{ $branch->city }}
                                    </span>
                                </div>
                                @if ($branch->manager)
                                    <div class="mt-1 text-gray-600">
                                        Manager:
                                        <a href="{{ route('contacts.show', $branch->manager) }}" class="hover:underline">
                                            {{ $branch->manager->name }}
                                        </a>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>

    <div class="mt-6">
        <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to customers</a>
    </div>
</x-layouts.app>
