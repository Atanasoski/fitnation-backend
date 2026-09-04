@extends('layouts.app')

@section('title', 'Workout Session - ' . $user->name)

@section('content')
<div class="space-y-6">

    <x-common.page-breadcrumb
        :pageTitle="'Workout Session'"
        :items="[
            ['label' => 'Users', 'url' => route('users.index')],
            ['label' => $user->name, 'url' => route('users.show', $user)],
            ['label' => 'Workout History', 'url' => route('users.workout-sessions.index', $user)],
        ]"
    />

    {{-- 12-column grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- MAIN SESSION CARD (8 cols) --}}
        <div class="lg:col-span-8">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900/30">

                <div class="border-l-4 border-brand-500 bg-gray-50/50 px-6 py-5 dark:bg-gray-800/30">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
                                {{ $workoutSession->workoutTemplate?->name ?? 'Custom Workout' }}
                            </h1>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $workoutSession->performed_at?->format('M d, Y') ?? '—' }}
                            </p>
                        </div>

                        @if($workoutSession->status_label === 'Completed')
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-success-100 px-3 py-1.5 text-xs font-semibold text-success-800 dark:bg-success-900/40 dark:text-success-300">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ $workoutSession->status_label }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold {{ $workoutSession->status_badge_classes }}">
                                {{ $workoutSession->status_label }}
                            </span>
                        @endif
                    </div>

                    {{-- METRICS GRID --}}
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-xl border border-gray-100 bg-white px-4 py-3 text-center dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="text-xs uppercase tracking-wider text-gray-500">Duration</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $durationMinutes ? $durationMinutes . ' min' : 'N/A' }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-white px-4 py-3 text-center dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="text-xs uppercase tracking-wider text-gray-500">Exercises</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $totalExercises }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-white px-4 py-3 text-center dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="text-xs uppercase tracking-wider text-gray-500">Total Volume</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ number_format($totalSessionVolume) }} kg
                            </div>
                        </div>
                    </div>

                    {{-- PROGRESS --}}
                    @if($totalExercises > 0)
                        <div class="mt-4">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Progress</span>
                                <span class="text-gray-500">{{ $exercisesWithSets }}/{{ $totalExercises }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div class="h-full bg-success-500 dark:bg-success-600" style="width: {{ min($progressPercent, 100) }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end border-t border-gray-100 px-6 py-3 dark:border-gray-800">
                    <a href="{{ route('users.workout-sessions.index', $user) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700">
                        ← Back to history
                    </a>
                </div>

            </div>
        </div>

        {{-- NOTES CARD (4 cols) --}}
        <div class="lg:col-span-4">
            <div class="sticky top-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900/30">
                <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-800 dark:bg-gray-800/30">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notes</h2>
                </div>
                <div class="p-5">
                    @if($workoutSession->notes)
                        <p class="whitespace-normal text-left text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                            {{ trim($workoutSession->notes) }}
                        </p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No notes for this session.</p>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- EXERCISES SECTION (8 cols) --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Exercises</h2>

            <div class="space-y-4">
                @forelse($exerciseRows as $index => $row)
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900/30">

                        {{-- HEADER --}}
                        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-200 text-sm font-bold">
                                {{ $index + 1 }}
                            </span>

                            <h3 class="min-w-0 flex-1 truncate font-semibold text-gray-900 dark:text-white">
                                {{ $row->sessionExercise->exercise?->name ?? 'Exercise' }}
                            </h3>

                            @if($row->hasSets)
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-success-100 dark:bg-success-900/40">
                                    ✓
                                </span>
                            @endif
                        </div>

                        {{-- SETS --}}
                        <div class="p-4">
                            @if($row->hasSets)
                                <div class="space-y-2">
                                    @foreach($row->setsForExercise as $set)
                                        <div class="flex flex-col sm:flex-row sm:justify-between gap-2 rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800/50">
                                            <span class="text-sm text-gray-500">Set {{ $set->set_number }}</span>

                                            <span class="text-sm font-semibold">
                                                {{ number_format($set->weight, 1) }} kg × {{ $set->reps }}
                                            </span>

                                            <span class="text-xs text-gray-500">
                                                {{ number_format($set->volume, 1) }} kg volume
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3 flex justify-between border-t border-gray-100 pt-3">
                                    <span class="text-sm text-gray-500">Volume</span>
                                    <span class="font-bold">{{ number_format($row->exerciseVolume, 1) }} kg</span>
                                </div>

                            @else
                                <p class="rounded-lg bg-gray-50 py-4 text-center text-sm text-gray-500">
                                    No sets logged
                                </p>
                            @endif
                        </div>

                    </div>

                @empty
                    <p class="text-sm text-gray-500 text-center py-10">
                        No exercises logged
                    </p>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
