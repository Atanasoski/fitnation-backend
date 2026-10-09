{{-- A mock app card in the partner's colours: $mode is Light or Dark, $colors keyed primary/background/... --}}
<div class="flex-1">
    <div class="mb-1 text-theme-xs font-medium text-gray-500 dark:text-gray-400">{{ $mode }}</div>
    <div class="rounded-lg p-3" style="background-color: {{ $colors['background'] }}">
        <div class="rounded-md p-3" style="background-color: {{ $colors['card_background'] }}">
            <div class="flex items-center gap-2">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="h-6 w-6 rounded-full object-cover" />
                @endif
                <span class="text-sm font-semibold" style="color: {{ $colors['text_primary'] }}">{{ $name }}</span>
            </div>
            <p class="mt-1 text-theme-xs" style="color: {{ $colors['text_secondary'] }}">Today's workout</p>
            <span class="mt-2 inline-block rounded-md px-2.5 py-1 text-theme-xs font-medium" style="background-color: {{ $colors['primary'] }}; color: {{ $colors['text_on_primary'] }}">Start</span>
        </div>
    </div>
    <div class="mt-1 flex gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
        <code>{{ $colors['primary'] }}</code>
        <code>{{ $colors['secondary'] }}</code>
    </div>
</div>
