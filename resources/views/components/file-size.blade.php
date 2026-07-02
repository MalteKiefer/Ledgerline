@props(['bytes'])
@php
    // Step up a unit only once the value reaches a full 1024 of the previous one.
    // Bytes render as a whole number; larger units show up to two decimals with
    // trailing zeros trimmed.
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    $label = ($unit === 0
        ? number_format($value, 0)
        : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')).' '.$units[$unit];
@endphp
{{ $label }}
