@extends('layouts.app')

@section('title', 'Exercises')

{{--
    The exercise gallery (spec 023, ticket 03). Everything is in the URL: the
    filters, the page, and `edit` / `create` for the slide-over. Facets and
    toggles are links; the search box and the bulk bar are plain forms.

    The partner lens (#134): with `partner` set, the gallery is that Partner's
    view of the catalogue. Cards show what its customers see ($views, from
    PartnerExerciseView) and link or unlink in the Partner Library.
--}}
@php
    $base = $gallery->query() + array_filter(['page' => $page]);
    $back = http_build_query($base);
    $heading = 'mb-2 text-theme-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $facetTitles = ['region' => 'Target region', 'equipment' => 'Equipment', 'difficulty' => 'Difficulty'];
    $lens = $gallery->partner;
    $overridden = $views ? $views->filter(fn ($v) => $v->hasDescriptionOverride || $v->hasImageOverride || $v->hasVideoOverride)->keys()->all() : [];
@endphp

@section('content')
    <div class="space-y-4" x-data="{ picked: [], overridden: @js($overridden) }">
        @if (session('success') && ! $editing)
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
            <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">
                {{ $gallery->archived ? 'Archived exercises' : 'Exercises' }}
                @if ($lens)
                    <span class="text-gray-500 dark:text-gray-400">· {{ $lens->name }} {{ $gallery->outsideLibrary ? '(not in library)' : 'library' }}</span>
                @endif
            </h1>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($exercises->total()) }} shown</span>
            <template x-if="picked.length">
                <form method="POST" action="{{ route('exercises.bulkDestroy') }}" class="flex items-center gap-3 rounded-lg bg-gray-900 px-3 py-1.5 text-sm text-white dark:bg-gray-800"
                    @submit="if (! confirm('Archive or delete ' + picked.length + ' exercises? Used ones are archived and keep their history; unused ones are deleted.')) $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="back" value="{{ $back }}">
                    <template x-for="id in picked" :key="id"><input type="hidden" name="exercise_ids[]" :value="id"></template>
                    <span x-text="picked.length + ' selected'"></span>
                    @if ($lens)
                        @if ($gallery->outsideLibrary)
                            <button type="submit" form="bulk-library" class="text-brand-300 hover:underline">Link to {{ $lens->name }}</button>
                        @else
                            <button type="submit" form="bulk-library" class="text-orange-400 hover:underline">Unlink from {{ $lens->name }}</button>
                        @endif
                    @endif
                    <button type="submit" class="text-error-400 hover:underline">{{ $lens ? 'Archive or delete (all partners)' : 'Archive or delete' }}</button>
                    <button type="button" class="text-gray-400 hover:underline" @click="picked = []">Clear</button>
                </form>
            </template>
            @if ($lens)
                {{-- The bulk bar's Link / Unlink submits this form, so it carries the picked ids itself. --}}
                <form id="bulk-library" method="POST" class="hidden"
                    action="{{ route($gallery->outsideLibrary ? 'admin.partners.library.link' : 'admin.partners.library.unlink', $lens) }}"
                    @if (! $gallery->outsideLibrary)
                        @submit="const lost = picked.filter(id => overridden.includes(id)).length; if (! confirm('Unlink ' + picked.length + ' exercises from ' + @js($lens->name) + '? Its members will no longer see them' + (lost ? ', and ' + lost + ' Partner Overrides will be deleted' : '') + '. Exercises on a Station are kept.')) $event.preventDefault()"
                    @endif>
                    @csrf
                    @unless ($gallery->outsideLibrary) @method('DELETE') @endunless
                    <input type="hidden" name="back" value="{{ $back }}">
                    <template x-for="id in picked" :key="id"><input type="hidden" name="exercise_ids[]" :value="id"></template>
                </form>
            @endif
            <a href="{{ route('exercises.index', $base + ['create' => 1]) }}" class="ml-auto rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">+ New exercise</a>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row">
            {{-- Facets --}}
            <aside class="w-full shrink-0 space-y-5 lg:w-56">
                <form method="GET" action="{{ route('exercises.index') }}">
                    @foreach (array_diff_key($gallery->query(), ['partner' => true, 'library' => true]) as $key => $value)
                        @foreach ((array) $value as $v)
                            <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">
                        @endforeach
                    @endforeach
                    <select name="partner" onchange="this.form.submit()" aria-label="Partner library"
                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">All partners (catalogue)</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected($lens?->is($partner))>{{ $partner->name }}{{ $partner->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                </form>

                @if ($lens)
                    <div class="space-y-1">
                        @foreach ([false => ['In library', 'in'], true => ['Not in library', 'out']] as $out => [$label, $count])
                            <a href="{{ route('exercises.index', $gallery->outsideLibrary === (bool) $out ? $gallery->query() : $gallery->toggled('library', 'out')) }}" @class([
                                'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                                'bg-brand-500 text-white' => $gallery->outsideLibrary === (bool) $out,
                                'bg-brand-500/10 text-brand-700 hover:bg-brand-500/15 dark:text-brand-300' => $gallery->outsideLibrary !== (bool) $out,
                            ])>{{ $label }} <span class="ml-auto">{{ $facets['library'][$count] }}</span></a>
                        @endforeach
                    </div>
                @endif

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
                    <a href="{{ route('exercises.index', $gallery->toggled('missing', 'media')) }}" @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                        'bg-orange-500 text-white' => $gallery->missingMedia,
                        'bg-orange-500/10 text-orange-600 hover:bg-orange-500/15 dark:text-orange-400' => ! $gallery->missingMedia,
                    ])>Missing media <span class="ml-auto">{{ $facets['missing'] }}</span></a>
                    <a href="{{ route('exercises.index', $gallery->toggled('archived', '1')) }}" @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm',
                        'bg-gray-800 text-white dark:bg-gray-700' => $gallery->archived,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' => ! $gallery->archived,
                    ])>Archived <span class="ml-auto">{{ $facets['archived'] }}</span></a>
                </div>

                @foreach ($facetTitles as $facet => $title)
                    <div>
                        <div class="{{ $heading }}">{{ $title }}</div>
                        @foreach ($facets[$facet] as $option)
                            @if ($option['count'] > 0 || $gallery->has($facet, $option['value']))
                                <a href="{{ route('exercises.index', $gallery->toggled($facet, $option['value'])) }}" class="flex items-center gap-2 py-0.5 text-sm text-gray-700 hover:text-brand-600 dark:text-gray-300 dark:hover:text-brand-400">
                                    <span @class([
                                        'inline-block h-3.5 w-3.5 rounded border',
                                        'border-brand-500 bg-brand-500' => $gallery->has($facet, $option['value']),
                                        'border-gray-300 dark:border-gray-600' => ! $gallery->has($facet, $option['value']),
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
                            @php
                                $view = $views?->get($exercise->id);
                                $imageUrl = $view ? $view->imageUrl : ($exercise->image ? Storage::url($exercise->image) : null);
                                $hasVideo = $view ? $view->videoUrl !== null : (bool) $exercise->video;
                            @endphp
                            <div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white hover:ring-2 hover:ring-brand-500/40 dark:border-gray-800 dark:bg-white/[0.03]">
                                <input type="checkbox" class="absolute left-2 top-2 z-10 rounded border-gray-300" value="{{ $exercise->id }}" x-model.number="picked" aria-label="Select {{ $exercise->name }}">
                                @if ($hasVideo)
                                    <span class="absolute right-2 top-2 z-10 rounded bg-gray-900/70 px-1.5 text-theme-xs text-white">▶ video</span>
                                @endif
                                <a href="{{ route('exercises.index', $base + ['edit' => $exercise->id]) }}" class="block">
                                    <div class="flex aspect-[4/3] items-center justify-center bg-gray-100 dark:bg-gray-800">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="" loading="lazy" class="h-full w-full object-cover">
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
                                            @if ($lens)
                                                <span class="ml-auto flex gap-1">
                                                    @if ($view->hasDescriptionOverride) <span class="rounded bg-brand-500/10 px-1.5 text-brand-700 dark:text-brand-300">own description</span> @endif
                                                    @if ($view->hasImageOverride) <span class="rounded bg-brand-500/10 px-1.5 text-brand-700 dark:text-brand-300">own image</span> @endif
                                                    @if ($view->hasVideoOverride) <span class="rounded bg-brand-500/10 px-1.5 text-brand-700 dark:text-brand-300">own video</span> @endif
                                                </span>
                                            @elseif ($exercise->overrides_count)
                                                <span class="ml-auto text-gray-400">{{ $exercise->overrides_count }} {{ Str::plural('override', $exercise->overrides_count) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                                @if ($lens)
                                    <form method="POST" class="border-t border-gray-100 px-3 py-2 dark:border-gray-800"
                                        action="{{ route($gallery->outsideLibrary ? 'admin.partners.library.link' : 'admin.partners.library.unlink', $lens) }}"
                                        @unless ($gallery->outsideLibrary)
                                            onsubmit="return confirm(@js('Unlink '.$exercise->name.' from '.$lens->name.'? This hides it from its members'.(in_array($exercise->id, $overridden, true) ? ', and deletes its Partner Override' : '').'.'))"
                                        @endunless>
                                        @csrf
                                        @unless ($gallery->outsideLibrary) @method('DELETE') @endunless
                                        <input type="hidden" name="back" value="{{ $back }}">
                                        <input type="hidden" name="exercise_ids[]" value="{{ $exercise->id }}">
                                        @if ($gallery->outsideLibrary)
                                            <button type="submit" class="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Link to {{ $lens->name }}</button>
                                        @else
                                            <button type="submit" class="text-theme-xs text-error-500 hover:underline">Unlink</button>
                                        @endif
                                    </form>
                                @endif
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
        @include('exercises.admin._editor', ['exercise' => $editing, 'overrides' => $overrides, 'close' => route('exercises.index', $base), 'back' => $back])
    @endif
@endsection
