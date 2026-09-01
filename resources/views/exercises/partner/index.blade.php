@extends('layouts.app')

@section('title', 'Exercise Library')

@section('content')
<div x-data="{
    selectedExercises: [],
    selectAll: false,
    collapsedCategories: {},
    showOnlyLinked: false,
    get selectedCount() { return this.selectedExercises.length; },
    toggleCategory(categoryId) {
        this.collapsedCategories[categoryId] = !this.collapsedCategories[categoryId];
    },
    isCategoryCollapsed(categoryId) {
        return this.collapsedCategories[categoryId] || false;
    },
    toggleSelectAll() {
        this.selectAll = !this.selectAll;
        const visibleUnlinkedRows = document.querySelectorAll('.exercise-row:not(.hidden) .exercise-checkbox:not([disabled])');
        visibleUnlinkedRows.forEach(checkbox => {
            const exerciseId = parseInt(checkbox.value);
            if (this.selectAll) {
                if (!this.selectedExercises.includes(exerciseId)) {
                    this.selectedExercises.push(exerciseId);
                }
            } else {
                this.selectedExercises = this.selectedExercises.filter(id => id !== exerciseId);
            }
            checkbox.checked = this.selectAll;
        });
    },
    toggleExercise(id) {
        const index = this.selectedExercises.indexOf(id);
        if (index > -1) {
            this.selectedExercises.splice(index, 1);
        } else {
            this.selectedExercises.push(id);
        }
        this.updateSelectAllState();
    },
    isSelected(id) {
        return this.selectedExercises.includes(id);
    },
    clearSelection() {
        this.selectedExercises = [];
        this.selectAll = false;
        document.querySelectorAll('.exercise-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
    },
    updateSelectAllState() {
        const visibleUnlinkedRows = document.querySelectorAll('.exercise-row:not(.hidden) .exercise-checkbox:not([disabled])');
        if (visibleUnlinkedRows.length === 0) {
            this.selectAll = false;
            return;
        }
        const allChecked = Array.from(visibleUnlinkedRows).every(checkbox => checkbox.checked);
        this.selectAll = allChecked;
    },
    submitBulkLink() {
        const form = document.getElementById('bulk-link-form');
        const inputsContainer = document.getElementById('bulk-link-inputs');
        inputsContainer.innerHTML = '';
        this.selectedExercises.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'exercise_ids[]';
            input.value = id;
            inputsContainer.appendChild(input);
        });
        form.submit();
    },
    toggleLinkedFilter() {
        this.showOnlyLinked = !this.showOnlyLinked;
        this.applyFilters();
    },
    applyFilters() {
        const searchTerm = document.getElementById('exercise-search')?.value.toLowerCase().trim() || '';
        const exerciseRows = document.querySelectorAll('.exercise-row');
        const categories = document.querySelectorAll('.exercise-category');

        exerciseRows.forEach(row => {
            const exerciseName = row.getAttribute('data-name');
            const muscleGroups = row.getAttribute('data-muscle-groups') || '';
            const isLinked = row.getAttribute('data-is-linked') === 'true';
            const searchableText = exerciseName + ' ' + muscleGroups;

            const matchesSearch = searchTerm === '' || searchableText.includes(searchTerm);
            const matchesLinkedFilter = !this.showOnlyLinked || isLinked;

            if (matchesSearch && matchesLinkedFilter) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });

        categories.forEach(category => {
            const tbody = category.querySelector('tbody');
            if (!tbody) return;

            const visibleRows = tbody.querySelectorAll('.exercise-row:not(.hidden)');
            if (visibleRows.length === 0) {
                category.classList.add('hidden');
            } else {
                category.classList.remove('hidden');
            }
        });

        this.updateSelectAllState();
    }
}" class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb pageTitle="Exercise Library" />

    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Select exercises from our exercise library and customize them for your gym's needs
            </p>
        </div>
    </div>

    <!-- Search Bar and Filters -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <span class="absolute left-4 top-1/2 -translate-y-1/2">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </span>
            <input type="text"
                   id="exercise-search"
                   placeholder="Search exercises by name or muscle group..."
                   @input="applyFilters()"
                   class="w-full rounded-lg border border-gray-200 bg-white py-3 pl-12 pr-4 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/3">
            <label class="flex cursor-pointer items-center gap-2">
                <input type="checkbox"
                       @change="toggleLinkedFilter()"
                       :checked="showOnlyLinked"
                       class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Show only linked</span>
            </label>
        </div>
    </div>

    <!-- Bulk Action Bar - Fixed at Bottom -->
    <div x-show="selectedCount > 0"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-0 z-50 border-t border-brand-500 bg-white shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] dark:border-brand-400 dark:bg-gray-900 transition-all duration-300 ease-in-out m-0"
         :class="{
             'xl:left-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
             'xl:left-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
             'left-0': $store.sidebar.isMobileOpen
         }"
         style="right: 0; margin-bottom: 0; padding-bottom: 0;">
        <div class="mx-auto w-full max-w-7xl px-4 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium text-brand-700 dark:text-brand-300">
                    <span x-text="selectedCount"></span> exercise(s) selected
                </p>
                <div class="flex items-center gap-2">
                    <form id="bulk-link-form" action="{{ route('partner.exercises.bulkLink') }}" method="POST" @submit.prevent="submitBulkLink()">
                        @csrf
                        <div id="bulk-link-inputs"></div>
                        <x-ui.button type="submit" variant="primary" size="md">
                            <x-slot:startIcon>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </x-slot:startIcon>
                            Link Selected
                        </x-ui.button>
                    </form>
                    <x-ui.button @click="clearSelection()" variant="outline" size="md">
                        Clear Selection
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>

    <!-- Exercises by Category -->
    @foreach($categories as $category)
        @if($category->exercises->count() > 0)
        <div class="exercise-category" data-category="{{ $category->name }}">
            <x-common.component-card>
                <x-slot:title>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2" style="color: {{ $category->color }};">
                            <span>{{ $category->icon }} {{ $category->name }}</span>
                            <x-ui.badge variant="light" color="light" size="sm">
                                {{ $category->exercises->count() }}
                            </x-ui.badge>
                        </div>
                        <x-ui.button
                            @click="toggleCategory({{ $category->id }})"
                            variant="outline"
                            size="sm"
                            className="px-3! py-1.5!">
                            <span x-text="isCategoryCollapsed({{ $category->id }}) ? 'Expand' : 'Collapse'"></span>
                        </x-ui.button>
                    </div>
                </x-slot:title>

                <!-- Exercises Table -->
                <div x-show="!isCategoryCollapsed({{ $category->id }})"
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
                                    <th class="px-5 py-3 text-center sm:px-6 w-12">
                                        <input type="checkbox"
                                               @change="toggleSelectAll()"
                                               :checked="selectAll"
                                               class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Image
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Exercise Name
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6 hidden lg:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Muscle Groups
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-center sm:px-6 hidden md:table-cell">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Rest Time
                                        </p>
                                    </th>
                                    <th class="px-5 py-3 text-center sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                            Type
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
                                @foreach($category->exercises as $exercise)
                                    <tr class="exercise-row border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-white/2"
                                        data-name="{{ strtolower($exercise->name) }}"
                                        data-muscle-groups="{{ strtolower($exercise->muscleGroups->pluck('name')->implode(' ')) }}"
                                        data-is-linked="{{ isset($exercise->is_linked) && $exercise->is_linked ? 'true' : 'false' }}">
                                        <td class="px-5 py-4 text-center sm:px-6">
                                            @if(!isset($exercise->is_linked) || !$exercise->is_linked)
                                                <input type="checkbox"
                                                       class="exercise-checkbox h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                                       value="{{ $exercise->id }}"
                                                       @change="toggleExercise({{ $exercise->id }})"
                                                       :checked="isSelected({{ $exercise->id }})">
                                            @else
                                                <input type="checkbox"
                                                       class="exercise-checkbox h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                                       disabled>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center gap-2">
                                                @if($exercise->partnerView->imageUrl)
                                                    <img src="{{ $exercise->partnerView->imageUrl }}" class="w-24 rounded">
                                                @else
                                            <span class="text-gray-400">No image set</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('partner.exercises.show', $exercise) }}" class="font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400">
                                                    {{ $exercise->name }}
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6 hidden lg:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($exercise->muscleGroups as $muscleGroup)
                                                    <x-ui.badge variant="light" color="light" size="sm">
                                                        {{ $muscleGroup->name }}
                                                    </x-ui.badge>
                                                @empty
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-center sm:px-6 hidden md:table-cell">
                                            <x-ui.badge variant="light" color="light" size="sm">
                                                {{ $exercise->default_rest_sec }}s
                                            </x-ui.badge>
                                        </td>
                                        <td class="px-5 py-4 text-center sm:px-6">
                                            @if(isset($exercise->is_linked) && $exercise->is_linked)
                                                <x-ui.badge variant="light" color="success" size="sm">
                                                    Linked
                                                </x-ui.badge>
                                            @else
                                                <x-ui.badge variant="light" color="light" size="sm">
                                                    Not Linked
                                                </x-ui.badge>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                @if(isset($exercise->is_linked) && $exercise->is_linked)
                                                    <a href="{{ route('partner.exercises.show', $exercise) }}">
                                                        <x-ui.button variant="outline" size="sm" className="px-3! py-1.5!">
                                                            <x-slot:startIcon>
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                </svg>
                                                            </x-slot:startIcon>
                                                        </x-ui.button>
                                                    </a>
                                                    <a href="{{ route('partner.exercises.edit', $exercise) }}">
                                                        <x-ui.button variant="outline" size="sm" className="px-3! py-1.5!">
                                                            <x-slot:startIcon>
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                </svg>
                                                            </x-slot:startIcon>
                                                        </x-ui.button>
                                                    </a>
                                                    <form action="{{ route('exercises.unlink', $exercise) }}"
                                                          method="POST"
                                                          class="inline"
                                                          onsubmit="return confirm('Unlink this exercise? This will remove all customizations.')">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="outline" size="sm" className="px-3! py-1.5! text-orange-600! ring-orange-300! hover:bg-orange-50! dark:text-orange-400! dark:ring-orange-700! dark:hover:bg-orange-500/10!">
                                                            <x-slot:startIcon>
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                                </svg>
                                                            </x-slot:startIcon>
                                                            Unlink
                                                        </x-ui.button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('exercises.link', $exercise) }}"
                                                          method="POST"
                                                          class="inline">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="primary" size="sm" className="px-3! py-1.5!">
                                                            <x-slot:startIcon>
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                                </svg>
                                                            </x-slot:startIcon>
                                                            Link
                                                        </x-ui.button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-common.component-card>
        </div>
        @endif
    @endforeach

    <!-- Spacer to prevent content from being hidden behind fixed bar -->
    <div x-show="selectedCount > 0"
         x-cloak
         class="h-24">
    </div>
</div>
@endsection

@push('scripts')
<script>
// Filter functionality is now handled by Alpine.js applyFilters() method
</script>

<style>
    [x-cloak] { display: none !important; }
</style>
@endpush
