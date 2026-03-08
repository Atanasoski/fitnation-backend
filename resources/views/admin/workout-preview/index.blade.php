@extends('layouts.app')

@section('title', 'Workout Generator Preview')

@section('content')
<div x-data="{
    collapsedWeeks: {},
    toggleWeek(weekNum) {
        this.collapsedWeeks[weekNum] = !this.collapsedWeeks[weekNum];
    },
    isWeekCollapsed(weekNum) {
        return this.collapsedWeeks[weekNum] || false;
    }
}" class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb pageTitle="Workout Generator Preview" />

    <!-- Page Header -->
    <div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Preview what the workout generator would produce for different user profiles and time periods
        </p>
    </div>

    @if(isset($error))
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 dark:bg-red-900/20 dark:border-red-800">
            <p class="text-sm text-red-800 dark:text-red-200">{{ $error }}</p>
        </div>
    @endif

    <!-- Form -->
    <x-common.component-card>
        <x-slot:title>Generator Parameters</x-slot:title>

        <form method="POST" action="{{ route('workout-preview.preview') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Partner -->
                <div>
                    <label for="partner_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Partner <span class="text-gray-400 text-xs">(optional)</span>
                    </label>
                    <select name="partner_id" id="partner_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        <option value="">All Exercises (No Partner)</option>
                        @if(isset($partners))
                            @foreach($partners as $partner)
                                <option value="{{ $partner->id }}" {{ (old('partner_id', $params['partner_id'] ?? '') == $partner->id) ? 'selected' : '' }}>
                                    {{ $partner->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Select a partner to preview workouts using only their linked exercises
                    </p>
                </div>

                <!-- Fitness Goal -->
                <div>
                    <label for="fitness_goal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Fitness Goal
                    </label>
                    <select name="fitness_goal" id="fitness_goal" required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        <option value="fat_loss" {{ (old('fitness_goal', $params['fitness_goal'] ?? '') === 'fat_loss') ? 'selected' : '' }}>Fat Loss</option>
                        <option value="muscle_gain" {{ (old('fitness_goal', $params['fitness_goal'] ?? '') === 'muscle_gain') ? 'selected' : '' }}>Muscle Gain</option>
                        <option value="strength" {{ (old('fitness_goal', $params['fitness_goal'] ?? '') === 'strength') ? 'selected' : '' }}>Strength</option>
                        <option value="general_fitness" {{ (old('fitness_goal', $params['fitness_goal'] ?? 'general_fitness') === 'general_fitness') ? 'selected' : '' }}>General Fitness</option>
                    </select>
                </div>

                <!-- Training Experience -->
                <div>
                    <label for="training_experience" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Training Experience
                    </label>
                    <select name="training_experience" id="training_experience" required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        <option value="beginner" {{ (old('training_experience', $params['training_experience'] ?? 'beginner') === 'beginner') ? 'selected' : '' }}>Beginner</option>
                        <option value="intermediate" {{ (old('training_experience', $params['training_experience'] ?? '') === 'intermediate') ? 'selected' : '' }}>Intermediate</option>
                        <option value="advanced" {{ (old('training_experience', $params['training_experience'] ?? '') === 'advanced') ? 'selected' : '' }}>Advanced</option>
                    </select>
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Gender
                    </label>
                    <select name="gender" id="gender" required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        <option value="male" {{ (old('gender', $params['gender'] ?? 'male') === 'male') ? 'selected' : '' }}>Male</option>
                        <option value="female" {{ (old('gender', $params['gender'] ?? '') === 'female') ? 'selected' : '' }}>Female</option>
                        <option value="other" {{ (old('gender', $params['gender'] ?? '') === 'other') ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <!-- Training Days Per Week -->
                <div>
                    <label for="training_days_per_week" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Training Days / Week
                    </label>
                    <select name="training_days_per_week" id="training_days_per_week" required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        @for($i = 1; $i <= 7; $i++)
                            <option value="{{ $i }}" {{ (old('training_days_per_week', $params['training_days_per_week'] ?? 3) == $i) ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Session Duration -->
                <div>
                    <label for="duration_minutes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Session Duration
                    </label>
                    <select name="duration_minutes" id="duration_minutes" required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        <option value="30" {{ (old('duration_minutes', $params['duration_minutes'] ?? 60) == 30) ? 'selected' : '' }}>30 min</option>
                        <option value="45" {{ (old('duration_minutes', $params['duration_minutes'] ?? 60) == 45) ? 'selected' : '' }}>45 min</option>
                        <option value="60" {{ (old('duration_minutes', $params['duration_minutes'] ?? 60) == 60) ? 'selected' : '' }}>60 min</option>
                        <option value="75" {{ (old('duration_minutes', $params['duration_minutes'] ?? 60) == 75) ? 'selected' : '' }}>75 min</option>
                        <option value="90" {{ (old('duration_minutes', $params['duration_minutes'] ?? 60) == 90) ? 'selected' : '' }}>90 min</option>
                    </select>
                </div>

                <!-- Weeks to Preview -->
                <div>
                    <label for="weeks" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Weeks to Preview
                    </label>
                    <select name="weeks" id="weeks" required
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
                        <option value="1" {{ (old('weeks', $params['weeks'] ?? 1) == 1) ? 'selected' : '' }}>1 week</option>
                        <option value="2" {{ (old('weeks', $params['weeks'] ?? 1) == 2) ? 'selected' : '' }}>2 weeks</option>
                        <option value="4" {{ (old('weeks', $params['weeks'] ?? 1) == 4) ? 'selected' : '' }}>4 weeks</option>
                        <option value="8" {{ (old('weeks', $params['weeks'] ?? 1) == 8) ? 'selected' : '' }}>8 weeks</option>
                        <option value="12" {{ (old('weeks', $params['weeks'] ?? 1) == 12) ? 'selected' : '' }}>12 weeks</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary">
                    Generate Preview
                </x-ui.button>
            </div>
        </form>
    </x-common.component-card>

    <!-- Results -->
    @if(isset($weeks) && !empty($weeks))
        <!-- Summary -->
        <x-common.component-card>
            <x-slot:title>Summary</x-slot:title>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Workouts</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['total_workouts'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Unique Exercises</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['unique_exercises'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Avg Exercises/Session</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['avg_exercises_per_session'] }}</p>
                </div>
            </div>
        </x-common.component-card>

        <!-- Weeks -->
        @foreach($weeks as $weekNum => $days)
            <x-common.component-card>
                <x-slot:title>
                    <button @click="toggleWeek({{ $weekNum }})" class="flex items-center justify-between w-full text-left">
                        <span>Week {{ $weekNum }}</span>
                        <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-180': !isWeekCollapsed({{ $weekNum }}) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </x-slot:title>

                <div x-show="!isWeekCollapsed({{ $weekNum }})" x-cloak class="space-y-4">
                    @foreach($days as $dayIndex => $dayData)
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
                            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/5">
                                <h4 class="font-semibold text-gray-900 dark:text-white">Day {{ $dayIndex + 1 }}: {{ $dayData['name'] }}</h4>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Exercise</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Muscle Groups</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sets × Reps</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Weight</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Rest</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-white/3">
                                        @foreach($dayData['exercises'] as $exerciseData)
                                            @php
                                                $exercise = $exerciseData['exercise'] ?? null;
                                                $muscleGroups = $exercise?->primaryMuscleGroups->pluck('name')->join(', ') ?? 'N/A';
                                            @endphp
                                            <tr>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $exercise?->name ?? 'Exercise #' . $exerciseData['exercise_id'] }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $muscleGroups }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $exerciseData['target_sets'] }} × {{ $exerciseData['target_reps'] }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $exerciseData['target_weight'] ? number_format($exerciseData['target_weight'], 1) . ' kg' : 'N/A' }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $exerciseData['rest_seconds'] ? round($exerciseData['rest_seconds'] / 60, 1) . ' min' : 'N/A' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-common.component-card>
        @endforeach
    @endif
</div>
@endsection
