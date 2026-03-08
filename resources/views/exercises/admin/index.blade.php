@extends('layouts.app')

@section('title', 'Exercise Library')

@section('content')
<div x-data="{
    collapsedEquipmentTypes: {},
    activePartnerFilter: 'all',
    visibleCounts: {},
    toggleEquipmentType(equipmentTypeId) {
        this.collapsedEquipmentTypes[equipmentTypeId] = !this.collapsedEquipmentTypes[equipmentTypeId];
    },
    isEquipmentTypeCollapsed(equipmentTypeId) {
        return this.collapsedEquipmentTypes[equipmentTypeId] || false;
    },
    setPartnerFilter(partnerId) {
        this.activePartnerFilter = partnerId;
        this.applyFilters();
    },
    showExercise(partnerIds) {
        if (this.activePartnerFilter === 'all') {
            return true;
        }
        if (!partnerIds) {
            return false;
        }
        const partnerIdArray = partnerIds.split(',').map(id => id.trim());
        return partnerIdArray.includes(this.activePartnerFilter.toString());
    },
    applyFilters() {
        const searchTerm = document.getElementById('exercise-search')?.value.toLowerCase().trim() || '';
        const exerciseRows = document.querySelectorAll('.exercise-row');
        const equipmentTypeGroups = document.querySelectorAll('.equipment-type-group');

        exerciseRows.forEach(row => {
            const exerciseName = row.getAttribute('data-name');
            const muscleGroups = row.getAttribute('data-muscle-groups') || '';
            const partnerIds = row.getAttribute('data-partner-ids') || '';
            const searchableText = exerciseName + ' ' + muscleGroups;

            const matchesSearch = searchTerm === '' || searchableText.includes(searchTerm);
            const matchesPartner = this.showExercise(partnerIds);

            if (matchesSearch && matchesPartner) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });

        equipmentTypeGroups.forEach(group => {
            const tbody = group.querySelector('tbody');
            if (!tbody) return;

            const visibleRows = tbody.querySelectorAll('.exercise-row:not(.hidden)');
            const equipmentTypeId = group.getAttribute('data-equipment-type-id');

            // Update visible count for this equipment type
            this.visibleCounts[equipmentTypeId] = visibleRows.length;

            if (visibleRows.length === 0) {
                group.classList.add('hidden');
            } else {
                group.classList.remove('hidden');
            }
        });
    },
    getVisibleCount(equipmentTypeId) {
        return this.visibleCounts[equipmentTypeId] ?? 0;
    },
    init() {
        // Initialize counts by calculating from DOM
        this.$nextTick(() => {
            const equipmentTypeGroups = document.querySelectorAll('.equipment-type-group');
            equipmentTypeGroups.forEach(group => {
                const tbody = group.querySelector('tbody');
                if (!tbody) return;
                const equipmentTypeId = group.getAttribute('data-equipment-type-id');
                const totalRows = tbody.querySelectorAll('.exercise-row');
                this.visibleCounts[equipmentTypeId] = totalRows.length;
            });
            // Apply filters to update counts based on initial state
            this.applyFilters();
        });
    }
}" class="space-y-6" x-init="init()">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb pageTitle="Exercise Library" />

    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage your exercises and video tutorials
            </p>
        </div>
        <a href="{{ route('exercises.create') }}">
            <x-ui.button variant="primary">
                <x-slot:startIcon>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </x-slot:startIcon>
                Add Custom Exercise
            </x-ui.button>
        </a>
    </div>

    <!-- Search Bar -->
    <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </span>
        <input type="text"
               id="exercise-search"
               @input="applyFilters()"
               placeholder="Search exercises by name or muscle group..."
               class="w-full rounded-lg border border-gray-200 bg-white py-3 pl-12 pr-4 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
    </div>

    <!-- Partner Filter -->
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Filter by Partner
        </label>
        <div x-data="{ isOptionSelected: false }" class="relative z-20 bg-transparent">
            <select
                x-model="activePartnerFilter"
                @change="isOptionSelected = true; applyFilters()"
                :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                <option value="all" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    All Partners
                </option>
                @foreach($partners as $partner)
                    <option value="{{ $partner->id }}" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                        {{ $partner->name }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>
    </div>

    <!-- Exercises by Equipment Type -->
    @foreach($equipmentTypes as $equipmentType)
        @if($equipmentType->exercises->count() > 0)
        <div class="equipment-type-group" data-equipment-type="{{ $equipmentType->name }}" data-equipment-type-id="{{ $equipmentType->id }}">
            <x-common.component-card>
                <x-slot:title>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span>{{ $equipmentType->name }}</span>
                            <x-ui.badge variant="light" color="light" size="sm">
                                <span x-text="getVisibleCount({{ $equipmentType->id }})">{{ $equipmentType->exercises->count() }}</span>
                            </x-ui.badge>
                        </div>
                        <x-ui.button
                            @click="toggleEquipmentType({{ $equipmentType->id }})"
                            variant="outline"
                            size="sm"
                            className="px-3! py-1.5!">
                            <span x-text="isEquipmentTypeCollapsed({{ $equipmentType->id }}) ? 'Expand' : 'Collapse'"></span>
                        </x-ui.button>
                    </div>
                </x-slot:title>

                <!-- Exercises Table -->
                <div x-show="!isEquipmentTypeCollapsed({{ $equipmentType->id }})"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[800px]">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-5 py-3 text-left sm:px-6 min-w-[200px]">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Image
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 min-w-[200px]">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Exercise Name
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Muscle Groups
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Secondary Muscle Groups
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Movement Pattern
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-center sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Equipment Type
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Target Region
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Training Style
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Angle
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
                                @foreach($equipmentType->exercises as $exercise)

                                    <tr class="exercise-row border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/2"
                                        x-data="{
                                            dropdownOpen: false,
                                            toggleDropdown() {
                                                this.dropdownOpen = !this.dropdownOpen;
                                            },
                                            closeDropdown() {
                                                this.dropdownOpen = false;
                                            }
                                        }"
                                        @click.away="closeDropdown()"
                                        data-name="{{ strtolower($exercise->name) }}"
                                        data-muscle-groups="{{ strtolower($exercise->muscleGroups->pluck('name')->implode(' ')) }}"
                                        data-partner-ids="{{ $exercise->partners->pluck('id')->implode(',') }}">
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            @if($exercise->muscle_group_image)
                                                <img src="{{ Storage::url($exercise->muscle_group_image) }}" class="w-24 rounded" />
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500">No muscle group image set</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('exercises.show', $exercise) }}" class="font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                                                    {{ $exercise->name }}
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($exercise->primaryMuscleGroups as $muscleGroup)
                                                    <x-ui.badge variant="light" color="light" size="sm">
                                                        {{ $muscleGroup->name }}
                                                    </x-ui.badge>
                                                @empty
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($exercise->secondaryMuscleGroups as $muscleGroup)
                                                    <x-ui.badge variant="light" color="light" size="sm">
                                                        {{ $muscleGroup->name }}
                                                    </x-ui.badge>
                                                @empty
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $exercise->movementPattern->name ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $exercise->equipmentType->name ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $exercise->targetRegion->name ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($exercise->trainingStyles as $trainingStyle)
                                                    <x-ui.badge variant="light" color="light" size="sm">
                                                        {{ $trainingStyle->name }}
                                                    </x-ui.badge>
                                                @empty
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $exercise->angle->name ?? '-' }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="relative flex items-center justify-end">
                                                <button type="button"
                                                        @click="toggleDropdown()"
                                                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-white/3 dark:text-gray-300 dark:hover:bg-white/5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                                    </svg>
                                                </button>

                                                <!-- Dropdown Menu -->
                                                <div x-show="dropdownOpen"
                                                     x-transition:enter="transition ease-out duration-100"
                                                     x-transition:enter-start="transform opacity-0 scale-95"
                                                     x-transition:enter-end="transform opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-75"
                                                     x-transition:leave-start="transform opacity-100 scale-100"
                                                     x-transition:leave-end="transform opacity-0 scale-95"
                                                     x-cloak
                                                     class="absolute right-0 z-50 mt-2 w-48 rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark">
                                                    <div class="p-2 space-y-1" role="menu">
                                                        <a href="{{ route('exercises.show', $exercise) }}"
                                                           @click="closeDropdown()"
                                                           class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                            </svg>
                                                            View
                                                </a>
                                                        <a href="{{ route('exercises.edit', $exercise) }}"
                                                           @click="closeDropdown()"
                                                           class="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                            Edit
                                                </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-common.component-card>
        </div>
        @endif
    @endforeach

</div>
@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
