{{--
    A horizontal share bar: a grey track with a fill sized to the percentage.
    tone: brand | orange | gray | blue-light. height: 2 (rounded) or 3
    (rounded-sm). With rest set, the bar is a split instead: the fill, then
    the remainder in the rest tone, no grey track.
--}}
@props(['percent', 'tone' => 'brand', 'height' => 2, 'rest' => null])

@php
    $tones = [
        'brand' => 'bg-brand-500 dark:bg-brand-600',
        'orange' => 'bg-orange-500 dark:bg-orange-600',
        'gray' => 'bg-gray-300 dark:bg-gray-700',
        'blue-light' => 'bg-blue-light-700 dark:bg-blue-light-600',
    ];
    $size = (int) $height === 3 ? 'h-3' : 'h-2';
    $radius = (int) $height === 3 ? 'rounded-sm' : 'rounded';
@endphp

@if ($rest)
    <div {{ $attributes->class("flex {$size} overflow-hidden rounded") }}>
        <div class="{{ $tones[$tone] }}" style="width: {{ $percent }}%"></div>
        <div class="flex-1 {{ $tones[$rest] }}"></div>
    </div>
@else
    <div {{ $attributes->class("{$size} {$radius} bg-gray-100 dark:bg-gray-800") }}>
        <div class="{{ $size }} {{ $radius }} {{ $tones[$tone] }}" style="width: {{ $percent }}%"></div>
    </div>
@endif
