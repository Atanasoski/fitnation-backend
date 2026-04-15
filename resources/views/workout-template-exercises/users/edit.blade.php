@extends('layouts.app')

@section('title', 'Edit Exercise - ' . $workoutTemplateExercise->exercise->name)

@section('content')
    <x-common.page-breadcrumb :pageTitle="'Edit Exercise'" :items="[['label' => 'Users', 'url' => route('users.index')], ['label' => $user->name, 'url' => route('users.show', $user)], ['label' => $workoutTemplate->plan->name, 'url' => route('plans.show', $workoutTemplate->plan)], ['label' => $workoutTemplate->name, 'url' => route('workouts.show', $workoutTemplate)]]" />

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <div class="mb-2 text-sm font-semibold text-red-800 dark:text-red-400">
                There were some errors with your submission:
            </div>
            <ul class="list-inside list-disc space-y-1 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-common.component-card title="Edit Exercise in Workout Template" :desc="'Update exercise details for ' . $workoutTemplateExercise->exercise->name">
        <form action="{{ route('workout-exercises.update', [$workoutTemplate, $workoutTemplateExercise]) }}" method="POST" @keydown.escape.window="window.location.href='{{ route('workouts.show', $workoutTemplate) }}'">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <!-- Exercise (read-only) -->
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Exercise
                    </label>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $workoutTemplateExercise->exercise->name }}</p>
                    @if($workoutTemplateExercise->exercise->category)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $workoutTemplateExercise->exercise->category->name }}</p>
                    @endif
                </div>

                <!-- Order -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">
                        Order <span class="text-xs font-normal text-gray-400 dark:text-gray-500">(optional)</span>
                    </label>
                    <input type="number"
                        name="order"
                        id="order"
                        value="{{ old('order', $workoutTemplateExercise->order) }}"
                        min="0"
                        placeholder="{{ ($workoutTemplate->workoutTemplateExercises()->max('order') ?? -1) + 1 }}"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('order') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                    @error('order')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Automatically set if left empty. The order in which this exercise appears in the workout (lower numbers appear first).</p>
                </div>

                <!-- Targets Section -->
                <div class="space-y-3">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Targets <span class="text-xs font-normal">(optional)</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <!-- Target Sets -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Sets
                            </label>
                            <div class="relative">
                                <input type="number"
                                    name="target_sets"
                                    id="target_sets"
                                    value="{{ old('target_sets', $workoutTemplateExercise->target_sets) }}"
                                    min="0"
                                    step="1"
                                    placeholder="sets"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('target_sets') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500">sets</span>
                            </div>
                            @error('target_sets')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Min Target Reps -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Min Reps
                            </label>
                            <div class="relative">
                                <input type="number"
                                    name="min_target_reps"
                                    id="min_target_reps"
                                    value="{{ old('min_target_reps', $workoutTemplateExercise->min_target_reps) }}"
                                    min="1"
                                    step="1"
                                    placeholder="min reps"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('min_target_reps') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500">reps</span>
                            </div>
                            @error('min_target_reps')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Max Target Reps -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Max Reps
                            </label>
                            <div class="relative">
                                <input type="number"
                                    name="max_target_reps"
                                    id="max_target_reps"
                                    value="{{ old('max_target_reps', $workoutTemplateExercise->max_target_reps) }}"
                                    min="1"
                                    step="1"
                                    placeholder="max reps"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('max_target_reps') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500">reps</span>
                            </div>
                            @error('max_target_reps')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Target Weight -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Weight
                            </label>
                            <div class="relative">
                                <input type="number"
                                    name="target_weight"
                                    id="target_weight"
                                    value="{{ old('target_weight', $workoutTemplateExercise->target_weight) }}"
                                    min="0"
                                    step="0.1"
                                    placeholder="kg"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('target_weight') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                                <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500">kg</span>
                            </div>
                            @error('target_weight')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Rest Seconds -->
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                            Rest
                        </label>
                        <div class="relative">
                            <input type="number"
                                name="rest_seconds"
                                id="rest_seconds"
                                value="{{ old('rest_seconds', $workoutTemplateExercise->rest_seconds) }}"
                                min="0"
                                step="1"
                                placeholder="sec"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-9 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('rest_seconds') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                            <span class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-gray-400 dark:text-gray-500">sec</span>
                        </div>
                        @error('rest_seconds')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                <a href="{{ route('workouts.show', $workoutTemplate) }}">
                    <x-ui.button variant="outline" size="md" type="button">
                        Cancel
                    </x-ui.button>
                </a>
                <x-ui.button variant="primary" size="md" type="submit">
                    Update Exercise
                </x-ui.button>
            </div>
        </form>
    </x-common.component-card>
@endsection
