<x-layouts.app title="Units">
    @php $field = 'rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500'; @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">Settings</a> <span aria-hidden="true">/</span> Units
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Units</h1>
    <p class="mt-1 text-sm text-gray-600">Unit types for invoice line items. Labels are shown in the invoice's language; the code is stored on lines and the UN/ECE code is used for e-invoices.</p>

    {{-- Add --}}
    <form method="POST" action="{{ route('settings.units.store') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div>
                <label for="code" class="block text-xs font-medium text-gray-500">Code</label>
                <input type="text" id="code" name="code" value="{{ old('code') }}" placeholder="h" required class="mt-1 w-full {{ $field }}">
                @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">German</label>
                <input type="text" name="name_de" value="{{ old('name_de') }}" placeholder="Stunde" required class="mt-1 w-full {{ $field }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">English</label>
                <input type="text" name="name_en" value="{{ old('name_en') }}" placeholder="Hour" required class="mt-1 w-full {{ $field }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">UN/ECE</label>
                <input type="text" name="zugferd_code" value="{{ old('zugferd_code', 'C62') }}" required class="mt-1 w-full {{ $field }}">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Add</button>
            </div>
        </div>
    </form>

    {{-- Existing --}}
    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($units->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No units.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($units as $unit)
                    <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                        <form method="POST" action="{{ route('settings.units.update', $unit) }}" class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
                            @csrf @method('PUT')
                            <input type="text" name="code" value="{{ $unit->code }}" class="{{ $field }}">
                            <input type="text" name="name_de" value="{{ $unit->name_de }}" class="{{ $field }}">
                            <input type="text" name="name_en" value="{{ $unit->name_en }}" class="{{ $field }}">
                            <div class="flex gap-2">
                                <input type="text" name="zugferd_code" value="{{ $unit->zugferd_code }}" class="w-20 {{ $field }}">
                                <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">Save</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('settings.units.destroy', $unit) }}" onsubmit="return confirm('Delete this unit?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
