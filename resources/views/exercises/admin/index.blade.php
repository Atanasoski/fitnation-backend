@extends('layouts.app')

@section('title', 'Exercises')

{{--
    The exercise gallery (spec 023, ticket 03). Everything is in the URL: the
    filters, the page, and `edit` / `create` for the slide-over. Facets and
    toggles are links; the search box and the bulk bar are plain forms.
--}}
@php
    $base = $gallery->query() + array_filter(['page' => $page]);
    $back = http_build_query($base);
    $toggle = function (string $facet, int|string $value) use ($gallery) {
        $query = $gallery->query();
        $values = $query[$facet] ?? [];
        $values = in_array((string) $value, $values, true)
            ? array_values(array_diff($values, [(string) $value]))
            : [...$values, (string) $value];
        $query[$facet] = $values;

        return route('exercises.index', array_filter($query, fn ($v) => $v !== []));
    };
    $flag = function (string $key, string $on) use ($gallery) {
        $query = $gallery->query();
        isset($query[$key]) ? $query = array_diff_key($query, [$key => true]) : $query[$key] = $on;

        return route('exercises.index', $query);
    };
    $selected = fn (string $facet, int|string $value) => in_array((string) $value, $gallery->query()[$facet] ?? [], true);
    $heading = 'mb-2 text-theme-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $facetTitles = ['region' => 'Target region', 'equipment' => 'Equipment', 'difficulty' => 'Difficulty'];
@endphp

@section('content')
    <div class="space-y-4" x-data="{ picked: [] }">
        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-900/20">
                <p class="text-sm text-success-800 dark:text-success-200">{{ session('success') }}</p>
            </div>
        @endif
        @if ($errors->any() && ! $editing)
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
                <p class="text-sm text-error-700 dark:text-error-300">{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">{{ $gallery->archived ? 'Archived exercises' : 'Exercises' }}</h1>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($exercises->total()) }} shown</span>
            <template x-if="picked.length">
                <form method="POST" action="{{ route('exercises.bulkDestroy') }}" class="flex items-center gap-3 rounded-lg bg-gray-900 px-3 py-1.5 text-sm text-white dark:bg-gray-800"
                    @submit="if (! confirm('Archive or delete ' + picked.length + ' exercises? Used ones are archived and keep their history; unused ones are deleted.')) $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="back" value="{{ $back }}">
                    <template x-for="id in picked" :key="id"><input type="hidden" name="exercise_ids[]" :value="id"></template>
                    <span x-text="picked.length + ' selected'"></span>
                    <button type="submit" class="text-error-400 hover:underline">Archive or delete</button>
                    <button type="button" class="text-gray-400 hover:underline" @click="picked = []">Clear</button>
                </form>
            </template>
            <a href="{{ route('exercises.index', $base + ['create' => 1]) }}" class="ml-auto rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">+ New exercise</a>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row">
            {{-- Facets --}}
            <aside class="w-full shrink-0 space-y-5 lg:w-56">
                <form method="GET" action="{{ route('exercises.index') }}">
                    @foreach (array_diff_key($gallery->query(), ['q' => true]) as $key => $value)
                        @foreach ((array) $value as $v)
                            <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">
                        @endforeach
                    @endforeach
                    <input type="search" name="q" value="{{ $gallery->q }}" placeholder="Search by name…" aria-label="Search exercises"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                </form>

                <div class="space-y-1">
                    <a href="{{ $flag('missing', 'media') }}" @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                        'bg-orange-500 text-white' => $gallery->missingMedia,
                        'bg-orange-500/10 text-orange-600 hover:bg-orange-500/15 dark:text-orange-400' => ! $gallery->missingMedia,
                    ])>Missing media <span class="ml-auto">{{ $facets['missing'] }}</span></a>
                    <a href="{{ $flag('archived', '1') }}" @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                        'bg-gray-800 text-white dark:bg-gray-700' => $gallery->archived,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' => ! $gallery->archived,
                    ])>Archived <span class="ml-auto">{{ $facets['archived'] }}</span></a>
                </div>

                @foreach ($facetTitles as $facet => $title)
                    <div>
                        <div class="{{ $heading }}">{{ $title }}</div>
                        @foreach ($facets[$facet] as $option)
                            @if ($option['count'] > 0 || $selected($facet, $option['value']))
                                <a href="{{ $toggle($facet, $option['value']) }}" class="flex items-center gap-2 py-0.5 text-sm text-gray-700 hover:text-brand-600 dark:text-gray-300 dark:hover:text-brand-400">
                                    <span @class([
                                        'inline-block h-3.5 w-3.5 rounded border',
                                        'border-brand-500 bg-brand-500' => $selected($facet, $option['value']),
                                        'border-gray-300 dark:border-gray-600' => ! $selected($facet, $option['value']),
                                    ])></span>
                                    {{ $option['label'] }}
                                    <span class="ml-auto text-theme-xs text-gray-400">{{ $option['count'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endforeach

                @if ($gallery->isFiltered())
                    <a href="{{ route('exercises.index') }}" class="text-theme-xs text-brand-600 hover:underline dark:text-brand-400">Clear filters</a>
                @endif
            </aside>

            {{-- Gallery --}}
            <div class="min-w-0 flex-1 space-y-4">
                @if ($exercises->isEmpty())
                    <p class="rounded-xl border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                        {{ $gallery->isFiltered() ? 'No exercises match these filters.' : 'No exercises yet.' }}
                    </p>
                @else
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                        @foreach ($exercises as $exercise)
                            <div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white hover:ring-2 hover:ring-brand-500/40 dark:border-gray-800 dark:bg-white/[0.03]">
                                <input type="checkbox" class="absolute left-2 top-2 z-10 rounded border-gray-300" value="{{ $exercise->id }}" x-model.number="picked" aria-label="Select {{ $exercise->name }}">
                                @if ($exercise->video)
                                    <span class="absolute right-2 top-2 z-10 rounded bg-gray-900/70 px-1.5 text-theme-xs text-white">▶ video</span>
                                @endif
                                <a href="{{ route('exercises.index', $base + ['edit' => $exercise->id]) }}" class="block">
                                    <div class="flex aspect-[4/3] items-center justify-center bg-gray-100 dark:bg-gray-800">
                                        @if ($exercise->image)
                                            <img src="{{ Storage::url($exercise->image) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-theme-xs text-gray-400">no image</span>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <div class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $exercise->name }}</div>
                                        <div class="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                            {{ collect([$exercise->equipmentType?->name, $exercise->primaryMuscleGroups->pluck('name')->join(', ')])->filter()->join(' · ') ?: '—' }}
                                        </div>
                                        <div class="mt-2 flex items-center gap-1 text-theme-xs">
                                            @if ($exercise->difficulty)
                                                <span class="rounded bg-gray-100 px-1.5 capitalize text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ $exercise->difficulty->value }}</span>
                                            @endif
                                            <span class="rounded bg-gray-100 px-1.5 text-gray-600 dark:bg-gray-800 dark:text-gray-400" title="Selection priority">P{{ $exercise->selection_priority }}</span>
                                            @if ($exercise->overrides_count)
                                                <span class="ml-auto text-gray-400">{{ $exercise->overrides_count }} {{ Str::plural('override', $exercise->overrides_count) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                                @if ($exercise->archived_at)
                                    <form method="POST" action="{{ route('exercises.restore', $exercise) }}" class="border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                                        @csrf
                                        <input type="hidden" name="back" value="{{ $back }}">
                                        <button type="submit" class="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Restore</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($exercises->hasPages())
                        <div>{{ $exercises->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @if ($editing)
        @include('exercises.admin._editor', ['exercise' => $editing, 'close' => route('exercises.index', $base), 'back' => $back])
    @endif
@endsection
