{{-- Type icon that resolves at runtime: renders all six server-side, x-show
     toggles the one matching the Alpine expression `$expr` (e.g. 'x.type'). --}}
@php $map = ['login' => 'user', 'password' => 'key', 'card' => 'credit-card', 'wifi' => 'wifi', 'license' => 'document', 'server' => 'server']; @endphp
@foreach ($map as $t => $ic)
    <span x-show="{{ $expr }} === '{{ $t }}'"><x-icon :name="$ic" :class="$cls ?? 'h-4 w-4'" /></span>
@endforeach
