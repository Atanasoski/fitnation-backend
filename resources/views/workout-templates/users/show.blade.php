@extends('layouts.app')

@section('title', $workoutTemplate->name . ' - Workout Template Details')

@section('content')
    <div>
    <x-common.page-breadcrumb :pageTitle="$workoutTemplate->name" :items="[['label' => 'Users', 'url' => route('users.index')], ['label' => $user->name, 'url' => route('users.show', $user)], ['label' => $workoutTemplate->plan->name, 'url' => route('plans.show', $workoutTemplate->plan)]]" />

    <!-- Workout Template Header Card -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900/30 mb-6">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h2 class="truncate text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ $workoutTemplate->name }}
                </h2>
                @if($workoutTemplate->description)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $workoutTemplate->description }}</p>
                @endif
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Part of <a href="{{ route('plans.show', $workoutTemplate->plan) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $workoutTemplate->plan->name }}</a>
                    @if($dayName)
                        — {{ $dayName }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('plans.show', $workoutTemplate->plan) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    Back to Plan
                </a>
            </div>
        </div>
    </div>

    <!-- Exercises Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Exercises</h3>
            <button @click="$dispatch('open-add-exercise-modal')" type="button" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Exercise
            </button>
        </div>

        @if($exercises->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Exercise</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sets</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reps</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Weight</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rest</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($exercises as $exercise)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $exercise->order }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $exercise->exercise->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $exercise->exercise->category->name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $exercise->target_sets ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $exercise->target_reps ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $exercise->target_weight ? number_format($exercise->target_weight, 1) . ' kg' : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $exercise->rest_seconds ? $exercise->rest_seconds . 's' : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('workout-exercises.edit', [$workoutTemplate, $exercise]) }}" class="text-blue-600 dark:text-blue-400 hover:underline mr-3">Edit</a>
                                    <form action="{{ route('workout-exercises.destroy', [$workoutTemplate, $exercise]) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this exercise?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No exercises yet</p>
                <button @click="$dispatch('open-add-exercise-modal')" type="button" class="mt-4 inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                    Add First Exercise
                </button>
            </div>
        @endif
    </div>

    <x-modals.add-exercise
        :storeUrl="route('workout-exercises.store', $workoutTemplate)"
        :workoutName="$workoutTemplate->name"
        :exercises="$availableExercises"
        :equipmentTypes="$equipmentTypes"
        :muscleGroups="$muscleGroups"
    />
    </div>
@endsection
