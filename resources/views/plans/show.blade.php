@extends('layouts.app')

@section('title', $plan->name . ' - Program Details')

@section('content')
    <div x-data="{
        editModalOpen: false,
        editingWorkout: null,
        addWorkoutModalOpen: false,
        addWorkoutWeekNumber: 1,
        editProgramModalOpen: false,
        openEditModal(workout) {
            this.editingWorkout = workout;
            this.editModalOpen = true;
        },
        closeEditModal() {
            this.editModalOpen = false;
            this.editingWorkout = null;
        },
        openAddWorkoutModal(weekNumber) {
            this.addWorkoutWeekNumber = weekNumber;
            this.addWorkoutModalOpen = true;
        },
        closeAddWorkoutModal() {
            this.addWorkoutModalOpen = false;
        },
        openEditProgramModal() {
            this.editProgramModalOpen = true;
        },
        closeEditProgramModal() {
            this.editProgramModalOpen = false;
        }
    }" x-init="
        if ({{ Js::encode($errors->hasAny(['name','description']) && old('plan_id') == $plan->id) }}) { addWorkoutWeekNumber = {{ (int) old('week_number', 1) }}; addWorkoutModalOpen = true; }
        if ({{ Js::encode($errors->hasAny(['name','description','duration_weeks']) && session('edit_plan_id') == $plan->id) }}) { editProgramModalOpen = true; }
    ">
    <x-common.page-breadcrumb :pageTitle="$plan->name" :items="[['label' => 'Programs', 'url' => route('partner.programs.index')]]" />

    <!-- Program Header Card -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900/30 mb-6">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h2 class="truncate text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ $plan->name }}
                </h2>
                @if($plan->description)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $plan->description }}</p>
                @endif
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                        Library Program
                    </span>
                    @if($plan->duration_weeks)
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            • {{ $plan->duration_weeks }} {{ Str::plural('week', $plan->duration_weeks) }}
                        </span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Available to all gym members via mobile app
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="openEditProgramModal()" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Program
                </button>
                <a href="{{ route('partner.programs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    Back to Programs
                </a>
            </div>
        </div>
    </div>

    <!-- Week cards: one card per week with per-week add workout -->
    <div class="mb-6">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Weeks</h3>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @for($week = 1; $week <= $weeks; $week++)
                <div class="relative rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Week {{ $week }}</h4>
                        <button type="button"
                            @click="openAddWorkoutModal({{ $week }})"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            title="Add workout to this week">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                    <div class="space-y-3">
                        @forelse($workoutsByWeek[$week] ?? collect() as $template)
                            <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-700 dark:bg-gray-900/50">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('workouts.show', $template) }}" class="min-w-0 flex-1 hover:opacity-90">
                                        <h5 class="truncate font-medium text-gray-900 dark:text-white">{{ $template->name }}</h5>
                                        @if($template->description)
                                            <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">{{ $template->description }}</p>
                                        @endif
                                        <span class="mt-1 inline-block text-xs text-gray-500 dark:text-gray-400">
                                            {{ $template->workout_template_exercises_count }} {{ Str::plural('exercise', $template->workout_template_exercises_count) }}
                                        </span>
                                    </a>
                                    <div class="flex shrink-0 gap-1">
                                        <button type="button"
                                            @click="openEditModal({{ Js::from(['id' => $template->id, 'name' => $template->name, 'description' => $template->description ?? '', 'updateUrl' => route('workouts.update', $template)]) }})"
                                            class="rounded p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                            title="Edit">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form action="{{ route('workouts.destroy', $template) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this workout template? This will also delete all associated exercises.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300" title="Delete">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No workouts yet. Click + to add one.</p>
                        @endforelse
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <!-- Delete Program Section -->
    <div class="rounded-lg border border-red-200 bg-red-50 p-6 dark:border-red-900/40 dark:bg-red-900/10">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-base font-medium text-red-900 dark:text-red-300">Delete Program</h3>
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                    Once you delete this program, it will be removed from the library and users won't be able to clone it anymore.
                </p>
            </div>
            <form action="{{ route('partner.programs.destroy', $plan) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this program? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800">
                    Delete Program
                </button>
            </form>
        </div>
    </div>

    <x-modals.edit-program :plan="$plan" />
    <x-modals.edit-workout />
    <x-modals.add-workout :plan="$plan" subtitle="Create a new workout template for this program." />
    </div>
@endsection
