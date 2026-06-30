{{--
    Shared contact-person form fields.

    Expects $contact (an empty instance when creating) and $functions, the
    ContactFunction options. The enclosing <form>, CSRF token and submit button
    are provided by the including view.
--}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium text-gray-700">
            Name<span class="text-red-600"> *</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $contact->name) }}" required
            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('name')
            <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $contact->email) }}"
            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('email')
            <p id="email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $contact->phone) }}"
            @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('phone')
            <p id="phone-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Function: type-ahead combobox over the fixed ContactFunction enum. --}}
    <div class="sm:col-span-2"
        x-data="contactFunctionCombobox(@js($functions), @js(old('function', $contact->function?->value)))">
        <label for="function-input" class="block text-sm font-medium text-gray-700">
            Function<span class="text-red-600"> *</span>
        </label>

        {{-- The actual submitted value is always an enum backing value. --}}
        <input type="hidden" name="function" :value="selected">

        <div class="relative mt-1">
            <input type="text" id="function-input" role="combobox" autocomplete="off"
                aria-controls="function-listbox" :aria-expanded="open.toString()"
                x-model="query" @focus="open = true" @input="syncFromQuery()"
                @keydown.escape="open = false" @click="open = true"
                placeholder="Type to search a function…"
                @error('function') aria-invalid="true" aria-describedby="function-error" @enderror
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">

            <ul x-show="open" x-cloak @click.outside="open = false" id="function-listbox" role="listbox"
                class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                <template x-for="option in filtered" :key="option.value">
                    <li role="option" :aria-selected="(selected === option.value).toString()"
                        @click="choose(option)"
                        class="cursor-pointer px-3 py-2 hover:bg-gray-100"
                        :class="{ 'bg-gray-100 font-medium': selected === option.value }"
                        x-text="option.label"></li>
                </template>
                <template x-if="filtered.length === 0">
                    <li class="px-3 py-2 text-gray-500">No matching function.</li>
                </template>
            </ul>
        </div>

        @error('function')
            <p id="function-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
