{{-- Payment-method type icon resolved at runtime: renders every type server-side,
     x-show toggles the one matching the Alpine expression `$expr` (e.g. 'pm.type').
     A reactive <x-icon ::name> does NOT work (the SVG is server-rendered). --}}
@php $map = ['bank' => 'building-library', 'card' => 'credit-card', 'paypal' => 'globe-alt', 'cash' => 'banknotes', 'other' => 'wallet']; @endphp
@foreach ($map as $t => $ic)
    <span x-show="{{ $expr }} === '{{ $t }}'"><x-icon :name="$ic" :class="$cls ?? 'h-4 w-4 text-white'" /></span>
@endforeach
