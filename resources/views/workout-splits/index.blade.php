@extends('layouts.app')

@section('title', 'Workout Splits')

@section('content')
<div x-data="{
    activeFilter: 'all',
    setFilter(focus) {
        this.activeFilter = focus;
    },
    showGroup(focus) {
        return this.activeFilter === 'all' || this.activeFilter === focus;
    }
}" class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb pageTitle="Workout Splits" />

    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage workout split configurations for different training frequencies and focuses
            </p>
        </div>
        <a href="{{ route('workout-splits.create') }}">
            <x-ui.button variant="primary">
                <x-slot:startIcon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </x-slot:startIcon>
                Add Split Day
            </x-ui.button>
        </a>
    </div>

    <!-- Focus Filter -->
    <div class="flex flex-wrap gap-2">
        <button
            @click="setFilter('all')"
            :class="activeFilter === 'all'
                ? 'bg-brand-500 text-white border-brand-500'
                : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300 dark:bg-white/3 dark:text-gray-300 dark:border-gray-800'"
            class="rounded-lg border px-4 py-2 text-sm font-medium transition">
            All
        </button>
        @foreach(\App\Enums\SplitFocus::cases() as $focus)
            <button
                @click="setFilter('{{ $focus->value }}')"
                :class="activeFilter === '{{ $focus->value }}'
                    ? 'bg-brand-500 text-white border-brand-500'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300 dark:bg-white/3 dark:text-gray-300 dark:border-gray-800'"
                class="rounded-lg border px-4 py-2 text-sm font-medium transition">
                {{ ucfirst(str_replace('_', ' ', $focus->value)) }}
            </button>
        @endforeach
    </div>

    <!-- Splits grouped by focus and days per week -->
    @forelse($splits as $focus => $focusGroups)
        @foreach($focusGroups as $daysPerWeek => $splitDays)
            <x-common.component-card x-show="showGroup('{{ $focus }}')" x-cloak>
                <x-slot:title>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span>{{ ucfirst(str_replace('_', ' ', $focus)) }} - {{ $daysPerWeek }} Day{{ $daysPerWeek > 1 ? 's' : '' }}/Week</span>
                            <x-ui.badge variant="light" color="light" size="sm">
                                {{ $splitDays->count() }} day{{ $splitDays->count() > 1 ? 's' : '' }}
                            </x-ui.badge>
                        </div>
                    </div>
                </x-slot:title>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[600px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Day Index
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Target Regions
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-right sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Actions
                                        </p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($splitDays as $split)
                                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/2">
                                        <td class="px-5 py-4 sm:px-6">
                                            <span class="text-sm text-gray-800 dark:text-white/90">{{ $split->day_index }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($split->target_regions as $region)
                                                    <x-ui.badge variant="light" color="light" size="sm">
                                                        {{ $region }}
                                                    </x-ui.badge>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('workout-splits.edit', $split) }}" class="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                                                    Edit
                                                </a>
                                                <form action="{{ route('workout-splits.destroy', $split) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this split day?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-common.component-card>
        @endforeach
    @empty
        <x-common.component-card>
            <div class="text-center py-12">
                <p class="text-gray-500 dark:text-gray-400">No workout splits found. Create your first split day to get started.</p>
                <a href="{{ route('workout-splits.create') }}" class="mt-4 inline-block">
                    <x-ui.button variant="primary">
                        Create First Split
                    </x-ui.button>
                </a>
            </div>
        </x-common.component-card>
    @endforelse
</div>
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
