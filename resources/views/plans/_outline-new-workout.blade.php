{{-- Plan outline: add a workout to the selected plan. --}}
<div class="max-w-xl space-y-4">
    <h2 class="font-display text-lg font-semibold text-gray-800 dark:text-white/90">New workout</h2>
    <form method="POST" action="{{ route('workouts.store', $selected) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="plan_id" value="{{ $selected->id }}">
        <div class="grid grid-cols-[1fr_10rem] gap-3">
            <div>
                <label for="workout-name" class="{{ $label }}">Name</label>
                <input id="workout-name" name="name" required maxlength="255" value="{{ old('name') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="workout-day" class="{{ $label }}">Day</label>
                @include('plans._outline-day-select', ['value' => old('day_of_week') === null || old('day_of_week') === '' ? null : (int) old('day_of_week')])
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="{{ $primary }}">Add workout</button>
            <a href="{{ $outlineUrl(['plan' => $selected->id]) }}" class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</a>
        </div>
    </form>
</div>
