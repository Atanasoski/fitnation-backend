{{-- Plan outline: the selected exercise row — targets, swap, remove. The form always sends every
     field. The target weight is in the user's Unit System ($rowWeight, converted for display). --}}
<div class="max-w-xl space-y-4">
    <div>
        <div class="flex items-center gap-3">
            <h2 class="font-display text-lg font-semibold text-gray-800 dark:text-white/90">{{ $row->exercise?->name ?? 'Unknown exercise' }}</h2>
            @if ($row->exercise?->archived_at)
                <span class="rounded bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-500 dark:bg-gray-800">archived</span>
            @endif
        </div>
        @if ($row->exercise?->equipmentType)
            <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $row->exercise->equipmentType->name }}</p>
        @endif
    </div>

    <form method="POST" action="{{ route('workout-exercises.update', [$workout, $row]) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-3 gap-3">
            @foreach ([
                ['target_sets', 'Sets', $row->target_sets, '0', '1'],
                ['min_target_reps', 'Min reps', $row->min_target_reps, '1', '1'],
                ['max_target_reps', 'Max reps', $row->max_target_reps, '1', '1'],
                ['target_weight', 'Target weight ('.$units->weightUnit().')', $rowWeight, '0', 'any'],
                ['rest_seconds', 'Rest (s)', $row->rest_seconds, '0', '1'],
            ] as [$field, $text, $value, $min, $step])
                <div>
                    <label for="row-{{ $field }}" class="{{ $label }}">{{ $text }}</label>
                    <input id="row-{{ $field }}" type="number" name="{{ $field }}" min="{{ $min }}" step="{{ $step }}"
                        value="{{ old($field, $value) }}" @if ($field !== 'target_weight') required @endif
                        @if ($field === 'target_weight') placeholder="—" @endif class="{{ $input }}">
                </div>
            @endforeach
        </div>
        <button type="submit" class="{{ $primary }}">Save</button>
    </form>

    <form method="POST" action="{{ route('workout-exercises.swap', [$workout, $row]) }}" class="flex gap-2">
        @csrf
        @method('PUT')
        <label for="row-swap" class="sr-only">Swap exercise</label>
        <select id="row-swap" name="exercise_id" required class="{{ $input }} w-auto min-w-0 flex-1">
            <option value="" selected disabled>Swap for…</option>
            @foreach ($offered as $exercise)
                @continue($exercise->id === $row->exercise_id)
                <option value="{{ $exercise->id }}">{{ $exercise->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Swap</button>
    </form>

    <form method="POST" action="{{ route('workout-exercises.destroy', [$workout, $row]) }}" class="border-t border-gray-100 pt-4 dark:border-gray-800"
        onsubmit="return confirm(@js('Remove '.($row->exercise?->name ?? 'this exercise').' from '.$workout->name.'?'))">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg px-3 py-2 text-sm text-error-500 hover:bg-error-500/10">Remove</button>
    </form>
</div>
