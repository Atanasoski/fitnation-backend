@props([
    'plan' => null,
    'workoutTemplate' => null,
    'action' => '',
    'method' => 'POST',
    'context' => 'library', // 'library' or 'user'
    // day_of_week (commented out): form UI and passing from controller/views are disabled
    'dayOfWeekOptions' => [],
    'dayOfWeekValue' => null,
])

<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($plan && $method === 'POST')
        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
    @endif

    <div class="space-y-5">
        <!-- Workout Name -->
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Workout Name <span class="text-error-500">*</span>
            </label>
            <input type="text"
                name="name"
                id="name"
                value="{{ old('name', $workoutTemplate?->name) }}"
                required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @enderror" />
            @error('name')
                <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Description -->
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Description
            </label>
            <textarea name="description"
                id="description"
                rows="4"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('description') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @enderror">{{ old('description', $workoutTemplate?->description) }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Day of Week (commented out)
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Day of Week
            </label>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Day of week">
                @foreach ($dayOfWeekOptions as $option)
                    <label class="relative inline-flex h-10 min-w-10 cursor-pointer select-none">
                        <input type="radio"
                            name="day_of_week"
                            value="{{ $option['value'] }}"
                            {{ (string) $dayOfWeekValue === (string) $option['value'] ? 'checked' : '' }}
                            class="peer sr-only"
                            @if ($option['value'] === '') aria-label="{{ $option['title'] }}" @endif />
                        <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm font-semibold text-gray-700 transition-colors hover:border-gray-400 hover:bg-gray-100 peer-checked:border-brand-500 peer-checked:bg-brand-500 peer-checked:text-white dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-500 dark:hover:bg-gray-700 dark:peer-checked:border-brand-500 dark:peer-checked:bg-brand-500 @error('day_of_week') border-error-300 dark:border-error-700 @enderror peer-checked:hidden"
                            title="{{ $option['title'] }}">{{ $option['letter'] }}</span>
                        <span class="absolute inset-0 hidden h-10 min-w-10 items-center justify-center rounded-lg border border-brand-500 bg-brand-500 text-white peer-checked:inline-flex dark:border-brand-500 dark:bg-brand-500"
                            title="{{ $option['title'] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('day_of_week')
                <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
            @enderror
        </div>
        --}}
    </div>

    <!-- Form Actions -->
    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
        @if ($context === 'library' && $plan)
            <a href="{{ route('partner.programs.show', $plan) }}">
                <x-ui.button variant="outline" size="md">
                    Cancel
                </x-ui.button>
            </a>
        @elseif ($context === 'user' && $plan)
            <a href="{{ route('plans.show', $plan) }}">
                <x-ui.button variant="outline" size="md">
                    Cancel
                </x-ui.button>
            </a>
        @elseif ($workoutTemplate)
            <a href="{{ route('workouts.show', $workoutTemplate) }}">
                <x-ui.button variant="outline" size="md">
                    Cancel
                </x-ui.button>
            </a>
        @endif
        <x-ui.button variant="primary" size="md" type="submit">
            {{ $workoutTemplate ? 'Update' : 'Create' }} Workout Template
        </x-ui.button>
    </div>
</form>
