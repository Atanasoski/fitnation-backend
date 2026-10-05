@extends('layouts.app')

@section('title', $user->name.' · Plans')

{{-- A user's plan outline (023/06, 023/07): every plan → workout → exercise row in a tree on the
     left, the selected node's editor on the right. The selection lives in the URL. --}}
@section('content')
    @php
        $card = 'rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
        $label = 'mb-1 block text-theme-xs font-medium text-gray-500 dark:text-gray-400';
        $input = 'h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $primary = 'rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600';
        $node = 'flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-sm hover:bg-gray-100 dark:hover:bg-white/5';
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dayName = fn (?int $day) => $day === null ? 'Any day' : ($days[$day] ?? '');
        $selected = $creating ? null : $outline->plan;
        $workout = $creating ? null : $outline->workout;
        $row = $creating ? null : $outline->row;
        $outlineUrl = fn (array $query = []) => route('plans.index', ['user' => $user] + $query);
        // The plan form: the selected plan when editing, nothing when creating.
        $form = $creating
            ? ['name' => '', 'type' => $creating->value, 'duration_weeks' => null, 'description' => '']
            : ($selected ? ['name' => $selected->name, 'type' => $selected->type->value, 'duration_weeks' => $selected->duration_weeks, 'description' => $selected->description] : null);
    @endphp

    <div class="space-y-4">
        <div class="space-y-1">
            <a href="{{ $back }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← {{ $user->name }}</a>
            <h1 class="font-display text-xl font-semibold text-gray-800 dark:text-white/90">Plans</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->name }} · {{ $user->email }} · weights in {{ $units->weightUnit() }}</p>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-900/20">
                <p class="text-sm text-success-800 dark:text-success-200">{{ session('success') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
                @foreach ($errors->all() as $error)
                    <p class="text-sm text-error-700 dark:text-error-300">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row">
            {{-- Tree --}}
            <aside class="{{ $card }} w-full shrink-0 p-2 lg:w-80">
                <div class="flex items-center justify-between px-2 py-1">
                    <span class="text-theme-xs font-semibold uppercase tracking-wide text-gray-500">Plans</span>
                    <span class="flex gap-3 text-theme-xs">
                        <a href="{{ $outlineUrl(['create' => 'program']) }}" class="text-brand-600 hover:underline dark:text-brand-400">+ program</a>
                        <a href="{{ $outlineUrl(['create' => 'routine']) }}" class="text-brand-600 hover:underline dark:text-brand-400">+ routine</a>
                    </span>
                </div>

                @forelse ($outline->plans as $plan)
                    <div x-data="{ open: @js($selected?->is($plan) ?? false) }">
                        <div class="{{ $node }} {{ $selected?->is($plan) && ! $workout && ! $adding ? 'bg-brand-500/15' : '' }}">
                            <button type="button" class="w-3 text-gray-400" @click="open = !open" :aria-expanded="open" aria-label="Show workouts">
                                <span x-text="open ? '▾' : '▸'">▸</span>
                            </button>
                            <a href="{{ $outlineUrl(['plan' => $plan->id]) }}" class="flex min-w-0 flex-1 items-center gap-1.5">
                                <span class="flex-1 truncate font-medium text-gray-800 dark:text-white/90">{{ $plan->name }}</span>
                                <span class="text-theme-xs capitalize text-gray-400">{{ $plan->type->value }}</span>
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $plan->is_active ? 'bg-success-500' : 'bg-transparent' }}" title="{{ $plan->is_active ? 'Active' : 'Inactive' }}"></span>
                            </a>
                        </div>
                        <div x-show="open" x-cloak class="ml-4 border-l border-gray-200 pl-1 dark:border-gray-800">
                            @foreach ($plan->workoutTemplates as $planWorkout)
                                <a href="{{ $outlineUrl(['plan' => $plan->id, 'workout' => $planWorkout->id]) }}"
                                    class="{{ $node }} {{ $workout?->is($planWorkout) && ! $row && ! $adding ? 'bg-brand-500/15' : '' }}">
                                    <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ $planWorkout->name }}</span>
                                    <span class="text-theme-xs text-gray-400">
                                        @if ($plan->isProgram() && $planWorkout->week_number) W{{ $planWorkout->week_number }} @endif
                                        {{ $dayName($planWorkout->day_of_week) }}
                                    </span>
                                </a>
                                <div class="ml-3 border-l border-gray-200 pl-1 dark:border-gray-800">
                                    @foreach ($planWorkout->workoutTemplateExercises as $planRow)
                                        <a href="{{ $outlineUrl(['plan' => $plan->id, 'workout' => $planWorkout->id, 'row' => $planRow->id]) }}"
                                            class="{{ $node }} py-0.5 text-theme-xs {{ $row?->is($planRow) ? 'bg-brand-500/15' : '' }}">
                                            <span class="flex-1 truncate text-gray-600 dark:text-gray-400">{{ $planRow->exercise?->name ?? 'Unknown exercise' }}</span>
                                            <span class="text-gray-400">{{ $planRow->target_sets }}×{{ $planRow->max_target_reps }}</span>
                                        </a>
                                    @endforeach
                                    <a href="{{ $outlineUrl(['plan' => $plan->id, 'workout' => $planWorkout->id, 'add' => 'exercise']) }}"
                                        class="{{ $node }} py-0.5 text-theme-xs text-gray-400 {{ $adding === 'exercise' && $workout?->is($planWorkout) ? 'bg-brand-500/15' : '' }}">+ exercise</a>
                                </div>
                            @endforeach
                            <a href="{{ $outlineUrl(['plan' => $plan->id, 'add' => 'workout']) }}"
                                class="{{ $node }} text-theme-xs text-gray-400 {{ $adding === 'workout' && $selected?->is($plan) ? 'bg-brand-500/15' : '' }}">+ workout</a>
                        </div>
                    </div>
                @empty
                    <p class="px-2 py-3 text-sm text-gray-400">No plans yet. Create a Program or a Routine.</p>
                @endforelse
            </aside>

            {{-- Node editor --}}
            <section class="{{ $card }} min-w-0 flex-1 p-6">
                @if ($selected)
                    <div class="mb-4 text-theme-xs text-gray-400">
                        <a href="{{ $outlineUrl(['plan' => $selected->id]) }}" class="hover:underline">{{ $selected->name }}</a>
                        @if ($workout)
                            / <a href="{{ $outlineUrl(['plan' => $selected->id, 'workout' => $workout->id]) }}" class="hover:underline">{{ $workout->name }}</a>
                        @endif
                        @if ($row)
                            / {{ $row->exercise?->name }}
                        @endif
                    </div>
                @endif

                @if ($adding === 'workout')
                    @include('plans._outline-new-workout')
                @elseif ($row)
                    @include('plans._outline-row')
                @elseif ($adding === 'exercise')
                    @include('plans._outline-picker')
                @elseif ($workout)
                    @include('plans._outline-workout')
                @elseif ($form)
                    <div class="max-w-xl space-y-4">
                        <div class="flex items-center gap-3">
                            <h2 class="font-display text-lg font-semibold text-gray-800 dark:text-white/90">
                                {{ $creating ? 'New '.$creating->value : 'Plan' }}
                            </h2>
                            @if ($selected?->is_active)
                                <span class="rounded-full bg-success-500/15 px-2 py-0.5 text-theme-xs font-medium text-success-600 dark:text-success-400">Active</span>
                            @endif
                            @if ($selected?->is_auto_generated)
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-500 dark:bg-gray-800">generated</span>
                            @endif
                        </div>

                        <form method="POST" action="{{ $creating ? route('plans.store', $user) : route('plans.update', $selected) }}" class="space-y-4"
                            x-data="{ type: @js(old('type', $form['type'])) }">
                            @csrf
                            @unless ($creating)
                                @method('PUT')
                            @endunless
                            <div>
                                <label for="plan-name" class="{{ $label }}">Name</label>
                                <input id="plan-name" name="name" required maxlength="255" value="{{ old('name', $form['name']) }}" class="{{ $input }}">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="plan-type" class="{{ $label }}">Type</label>
                                    <select id="plan-type" name="type" x-model="type" class="{{ $input }}">
                                        <option value="program" @selected(old('type', $form['type']) === 'program')>Program</option>
                                        <option value="routine" @selected(old('type', $form['type']) === 'routine')>Routine</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="plan-weeks" class="{{ $label }}">Duration (weeks)</label>
                                    <input id="plan-weeks" type="number" name="duration_weeks" min="1" max="52" value="{{ old('duration_weeks', $form['duration_weeks']) }}"
                                        :disabled="type === 'routine'" :required="type === 'program'" class="{{ $input }} disabled:opacity-40">
                                </div>
                            </div>
                            <div>
                                <label for="plan-description" class="{{ $label }}">Description</label>
                                <textarea id="plan-description" name="description" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent p-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('description', $form['description']) }}</textarea>
                            </div>
                            @if ($creating)
                                <p class="text-theme-xs text-gray-500 dark:text-gray-400">A new plan starts inactive. Activate it once it is ready.</p>
                            @elseif ($selected->is_active)
                                <p class="text-theme-xs text-gray-500 dark:text-gray-400" x-show="type !== @js($selected->type->value)" x-cloak>
                                    Changing the type makes this the active <span x-text="type"></span>, replacing any other.
                                </p>
                            @endif
                            <div class="flex gap-2">
                                <button type="submit" class="{{ $primary }}">{{ $creating ? 'Create '.$creating->value : 'Save' }}</button>
                                @if ($creating)
                                    <a href="{{ $outlineUrl() }}" class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</a>
                                @endif
                            </div>
                        </form>

                        @if ($selected && ! $selected->is_active)
                            <form method="POST" action="{{ route('plans.activate', $selected) }}" class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800"
                                onsubmit="return confirm(@js($replaces ? "Activate {$selected->name}? It replaces {$replaces->name} as the active {$selected->type->value}." : "Activate {$selected->name}?"))">
                                @csrf
                                @if ($replaces)
                                    <p class="mb-2 text-gray-600 dark:text-gray-400">Activating replaces <b>{{ $replaces->name }}</b> as the active {{ $selected->type->value }}.</p>
                                @else
                                    <p class="mb-2 text-gray-600 dark:text-gray-400">No other {{ $selected->type->value }} is active.</p>
                                @endif
                                <button type="submit" class="rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-600">Activate</button>
                            </form>
                        @endif

                        @if ($selected)
                            <form method="POST" action="{{ route('plans.destroy', $selected) }}" class="border-t border-gray-100 pt-4 dark:border-gray-800"
                                onsubmit="return confirm(@js("Delete {$selected->name}? Its workouts go with it. {$user->name}'s logged sessions are kept."))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg px-3 py-2 text-sm text-error-500 hover:bg-error-500/10">Delete plan</button>
                                <p class="mt-1 text-theme-xs text-gray-400">Logged sessions are kept.</p>
                            </form>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-400">Select a plan, or create a Program or a Routine.</p>
                @endif
            </section>
        </div>
    </div>
@endsection
