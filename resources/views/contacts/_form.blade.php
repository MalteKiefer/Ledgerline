{{--
    Shared contact-person form fields.

    Expects:
      $contact         the model (empty when creating)
      $functions       ContactFunction options for the combobox
      $emailLabels     suggested email labels (datalist hints)
      $phoneLabels     suggested phone labels (datalist hints)
      $existingEmails  [{label, value}] for repopulation
      $existingPhones  [{label, value}] for repopulation

    The enclosing <form>, CSRF token and submit button come from the including
    view.
--}}
@php
    $emailRows = collect(old('emails', $existingEmails))
        ->map(fn ($row) => ['label' => $row['label'] ?? '', 'value' => $row['email'] ?? ($row['value'] ?? '')])
        ->values()->all();
    $phoneRows = collect(old('phones', $existingPhones))
        ->map(fn ($row) => ['label' => $row['label'] ?? '', 'value' => $row['phone'] ?? ($row['value'] ?? '')])
        ->values()->all();
    $channelErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'emails.') || str_starts_with($key, 'phones.'))
        ->flatten();
@endphp

<div class="space-y-6">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">
            {{ __('contacts.form.name') }}<span class="text-red-600"> *</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $contact->name) }}" required
            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('name')
            <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Function: type-ahead combobox over the fixed ContactFunction enum. --}}
    <div x-data="contactFunctionCombobox(@js($functions), @js(old('function', $contact->function?->value)))">
        <label for="function-input" class="block text-sm font-medium text-gray-700">
            {{ __('contacts.form.function') }}<span class="text-red-600"> *</span>
        </label>
        <input type="hidden" name="function" :value="selected">
        <div class="relative mt-1">
            <input type="text" id="function-input" role="combobox" autocomplete="off"
                aria-controls="function-listbox" :aria-expanded="open.toString()"
                x-model="query" @focus="open = true" @input="syncFromQuery()"
                @keydown.escape="open = false" @click="open = true"
                :placeholder="__('contacts.form.function_placeholder')"
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
                    <li class="px-3 py-2 text-gray-500">{{ __('contacts.form.no_matching_function') }}</li>
                </template>
            </ul>
        </div>
        @error('function')
            <p id="function-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Emails and phones: add as many labelled rows as needed. --}}
    <div x-data="contactChannels(@js($emailRows), @js($phoneRows))" class="space-y-6">
        @if ($channelErrors->isNotEmpty())
            <ul class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                @foreach ($channelErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">{{ __('contacts.form.email_addresses') }}</legend>
            <div class="mt-2 space-y-2">
                <template x-for="(email, index) in emails" :key="index">
                    <div class="flex items-start gap-2">
                        <input type="text" list="email-label-suggestions" placeholder="{{ __('contacts.form.label') }}"
                            :name="`emails[${index}][label]`" x-model="email.label"
                            class="w-32 rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <input type="email" placeholder="{{ __('contacts.form.email_placeholder') }}"
                            :name="`emails[${index}][email]`" x-model="email.value"
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <button type="button" @click="removeEmail(index)"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            {{ __('contacts.form.remove') }}
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addEmail()"
                class="mt-2 text-sm font-medium text-gray-700 hover:text-gray-900">{{ __('contacts.form.add_email') }}</button>
        </fieldset>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">{{ __('contacts.form.phone_numbers') }}</legend>
            <div class="mt-2 space-y-2">
                <template x-for="(phone, index) in phones" :key="index">
                    <div class="flex items-start gap-2">
                        <input type="text" list="phone-label-suggestions" placeholder="{{ __('contacts.form.label') }}"
                            :name="`phones[${index}][label]`" x-model="phone.label"
                            class="w-32 rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <input type="text" placeholder="{{ __('contacts.form.phone_placeholder') }}"
                            :name="`phones[${index}][phone]`" x-model="phone.value"
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <button type="button" @click="removePhone(index)"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            {{ __('contacts.form.remove') }}
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addPhone()"
                class="mt-2 text-sm font-medium text-gray-700 hover:text-gray-900">{{ __('contacts.form.add_phone') }}</button>
        </fieldset>
    </div>
</div>

<datalist id="email-label-suggestions">
    @foreach ($emailLabels as $label)
        <option value="{{ $label }}"></option>
    @endforeach
</datalist>
<datalist id="phone-label-suggestions">
    @foreach ($phoneLabels as $label)
        <option value="{{ $label }}"></option>
    @endforeach
</datalist>
