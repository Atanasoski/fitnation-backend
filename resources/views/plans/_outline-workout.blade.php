{{-- Plan outline: the selected workout — name, day, row order, remove. --}}
@php($rows = $workout->workoutTemplateExercises)
<div class="max-w-xl space-y-4">
    <h2 class="font-display text-lg font-semibold text-gray-800 dark:text-white/90">Workout</h2>

    <form method="POST" action="{{ route('workouts.update', $workout) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-[1fr_10rem] gap-3">
            <div>
                <label for="workout-name" class="{{ $label }}">Name</label>
                <input id="workout-name" name="name" required maxlength="255" value="{{ old('name', $workout->name) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="workout-day" class="{{ $label }}">Day</label>
                @include('plans._outline-day-select', ['value' => $workout->day_of_week])
            </div>
        </div>
        <button type="submit" class="{{ $primary }}">Save</button>
    </form>

    <div>
        <div class="{{ $label }}">Exercises (order)</div>
        @forelse ($rows as $index => $workoutRow)
            <div class="flex items-center gap-2 border-b border-gray-100 py-1.5 text-sm dark:border-gray-800">
                <span class="w-5 text-gray-400">{{ $index + 1 }}</span>
                <a href="{{ $outlineUrl(['plan' => $selected->id, 'workout' => $workout->id, 'row' => $workoutRow->id]) }}"
                    class="flex-1 truncate text-gray-800 hover:text-brand-600 dark:text-white/90">{{ $workoutRow->exercise?->name ?? 'Unknown exercise' }}</a>
                <span class="text-theme-xs text-gray-400">{{ $workoutRow->target_sets }}×{{ $workoutRow->min_target_reps }}–{{ $workoutRow->max_target_reps }}</span>
                @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                    <form method="POST" action="{{ route('workout-exercises.move', [$workout, $workoutRow]) }}">
                        @csrf
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <button type="submit" class="px-1 text-gray-400 hover:text-gray-700 disabled:opacity-30 dark:hover:text-gray-200"
                            aria-label="Move {{ $direction }}" @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))>{{ $arrow }}</button>
                    </form>
                @endforeach
            </div>
        @empty
            <p class="py-1.5 text-sm text-gray-400">No exercises yet.</p>
        @endforelse
        <a href="{{ $outlineUrl(['plan' => $selected->id, 'workout' => $workout->id, 'add' => 'exercise']) }}"
            class="mt-2 inline-block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">+ exercise</a>
    </div>

    <form method="POST" action="{{ route('workouts.destroy', $workout) }}" class="border-t border-gray-100 pt-4 dark:border-gray-800"
        onsubmit="return confirm(@js("Remove {$workout->name} and its exercises? {$user->name}'s logged sessions are kept."))">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg px-3 py-2 text-sm text-error-500 hover:bg-error-500/10">Remove workout</button>
    </form>
</div>
