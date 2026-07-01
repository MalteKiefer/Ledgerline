@php
    $selected = $selected ?? null;
    $palette = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#6B7280'];
@endphp

<div class="flex flex-wrap items-center gap-1.5">
    <label class="cursor-pointer" title="No colour">
        <input type="radio" name="color" value="" class="peer sr-only" @checked(empty($selected))>
        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-gray-400 ring-1 ring-gray-300 peer-checked:ring-2 peer-checked:ring-gray-800">∅</span>
    </label>
    @foreach ($palette as $hex)
        <label class="cursor-pointer" title="{{ $hex }}">
            <input type="radio" name="color" value="{{ $hex }}" class="peer sr-only"
                @checked(strtoupper((string) $selected) === $hex)>
            <span class="block h-6 w-6 rounded-full ring-1 ring-black/10 peer-checked:ring-2 peer-checked:ring-gray-800"
                style="background-color: {{ $hex }}"></span>
        </label>
    @endforeach
</div>
