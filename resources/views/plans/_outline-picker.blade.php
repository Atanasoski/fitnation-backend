{{-- Plan outline: add an exercise to the selected workout, from the plan owner's partner
     catalogue ($offered: available exercises only). --}}
<div class="max-w-xl space-y-3" x-data="{ q: '' }">
    <h2 class="font-display text-lg font-semibold text-gray-800 dark:text-white/90">Add exercise to {{ $workout->name }}</h2>
    <input x-model="q" type="search" placeholder="Search catalogue…" aria-label="Search catalogue" class="{{ $input }}">
    <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
        @forelse ($offered as $exercise)
            <form method="POST" action="{{ route('workout-exercises.store', $workout) }}"
                x-show="q === '' || @js(mb_strtolower($exercise->name)).includes(q.toLowerCase())">
                @csrf
                <input type="hidden" name="exercise_id" value="{{ $exercise->id }}">
                <button type="submit" class="flex w-full justify-between gap-3 py-2 text-left text-sm text-gray-700 hover:text-brand-600 dark:text-gray-300">
                    <span>{{ $exercise->name }}</span>
                    <span class="text-theme-xs text-gray-400">{{ $exercise->equipmentType?->name }}</span>
                </button>
            </form>
        @empty
            <p class="py-2 text-sm text-gray-400">This partner's catalogue has no exercises.</p>
        @endforelse
    </div>
    <a href="{{ $outlineUrl(['plan' => $selected->id, 'workout' => $workout->id]) }}" class="inline-block rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Cancel</a>
</div>
