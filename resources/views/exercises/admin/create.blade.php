@extends('layouts.app')

@section('title', 'Create Exercise')

@section('content')
<div class="space-y-6">
    <x-common.page-breadcrumb
        pageTitle="Create Exercise"
        :items="[['label' => 'Exercise Library', 'url' => route('exercises.index')]]"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Add a new exercise to the library
            </p>
        </div>
    </div>

    <form action="{{ route('exercises.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="space-y-6">
                <x-common.component-card title="Exercise Information">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Exercise Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                placeholder="e.g., Dumbbell Curl"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                            @error('name')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <textarea
                                name="description"
                                rows="6"
                                placeholder="Exercise instructions or notes..."
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="category_id"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                                <option value="">Select category...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->icon }} {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default Rest Time (seconds)
                            </label>
                            <input
                                type="number"
                                name="default_rest_sec"
                                value="{{ old('default_rest_sec', 120) }}"
                                min="0"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                            @error('default_rest_sec')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-common.component-card>
            </div>

            <div class="space-y-6">
                <x-common.component-card title="Classification">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Movement Pattern <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="movement_pattern_id"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                                <option value="">Select movement pattern...</option>
                                @foreach($movementPatterns as $movementPattern)
                                    <option value="{{ $movementPattern->id }}" {{ old('movement_pattern_id') == $movementPattern->id ? 'selected' : '' }}>
                                        {{ $movementPattern->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('movement_pattern_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Target Region <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="target_region_id"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                                <option value="">Select target region...</option>
                                @foreach($targetRegions as $targetRegion)
                                    <option value="{{ $targetRegion->id }}" {{ old('target_region_id') == $targetRegion->id ? 'selected' : '' }}>
                                        {{ $targetRegion->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_region_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Equipment Type <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="equipment_type_id"
                                required
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                                <option value="">Select equipment type...</option>
                                @foreach($equipmentTypes as $equipmentType)
                                    <option value="{{ $equipmentType->id }}" {{ old('equipment_type_id') == $equipmentType->id ? 'selected' : '' }}>
                                        {{ $equipmentType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('equipment_type_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Angle
                            </label>
                            <select
                                name="angle_id"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                            >
                                <option value="">None</option>
                                @foreach($angles as $angle)
                                    <option value="{{ $angle->id }}" {{ old('angle_id') == $angle->id ? 'selected' : '' }}>
                                        {{ $angle->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('angle_id')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Training Styles
                            </label>
                            <select name="training_style_ids[]"
                                    multiple
                                    size="6"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                @foreach($trainingStyles as $trainingStyle)
                                    <option value="{{ $trainingStyle->id }}" {{ in_array($trainingStyle->id, old('training_style_ids', [])) ? 'selected' : '' }}>
                                        {{ $trainingStyle->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</p>
                            @error('training_style_ids')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @error('training_style_ids.*')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-common.component-card>

                <x-common.component-card title="Muscle Groups">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Primary Muscle Groups
                            </label>
                            <select name="primary_muscle_group_ids[]"
                                    multiple
                                    size="6"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                @foreach($muscleGroups as $muscleGroup)
                                    <option value="{{ $muscleGroup->id }}" {{ in_array($muscleGroup->id, old('primary_muscle_group_ids', [])) ? 'selected' : '' }}>
                                        {{ $muscleGroup->name }} ({{ ucfirst($muscleGroup->body_region) }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</p>
                            @error('primary_muscle_group_ids')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @error('primary_muscle_group_ids.*')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Secondary Muscle Groups
                            </label>
                            <select name="secondary_muscle_group_ids[]"
                                    multiple
                                    size="6"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                @foreach($muscleGroups as $muscleGroup)
                                    <option value="{{ $muscleGroup->id }}" {{ in_array($muscleGroup->id, old('secondary_muscle_group_ids', [])) ? 'selected' : '' }}>
                                        {{ $muscleGroup->name }} ({{ ucfirst($muscleGroup->body_region) }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</p>
                            @error('secondary_muscle_group_ids')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @error('secondary_muscle_group_ids.*')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-common.component-card>

                <x-common.component-card title="Actions">
                    <div class="flex gap-2">
                        <a href="{{ route('exercises.index') }}" class="flex-1">
                            <x-ui.button type="button" variant="outline" className="w-full">
                                Cancel
                            </x-ui.button>
                        </a>
                        <x-ui.button type="submit" variant="primary" className="flex-1">
                            Create Exercise
                        </x-ui.button>
                    </div>
                </x-common.component-card>
            </div>
        </div>
    </form>
</div>
@endsection
