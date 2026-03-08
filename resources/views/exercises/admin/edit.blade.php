@extends('layouts.app')

@section('title', 'Edit ' . $exercise->name)

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb :pageTitle="'Edit ' . $exercise->name" :items="[['label' => 'Exercise Library', 'url' => route('exercises.index')]]" />

    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Edit exercise details
            </p>
        </div>
    </div>

    <!-- Edit Form -->
    <form action="{{ route('exercises.update', $exercise) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Form Fields -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Exercise Information Card -->
                <x-common.component-card title="Exercise Information">
                    <div class="space-y-4">
                        <!-- Exercise Name -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Exercise Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   value="{{ old('name', $exercise->name) }}"
                                   required
                                   class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <textarea name="description"
                                      rows="6"
                                      placeholder="Enter exercise description..."
                                      class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">{{ old('description', $exercise->description) }}</textarea>
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select name="category_id"
                                    required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id', $exercise->category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->icon }} {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Default Rest Time -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default Rest Time (seconds)
                            </label>
                            <input type="number"
                                   name="default_rest_sec"
                                   value="{{ old('default_rest_sec', $exercise->default_rest_sec) }}"
                                   min="0"
                                   class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                        </div>
                    </div>
                </x-common.component-card>

                <!-- Classification -->
                <x-common.component-card title="Classification">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Movement Pattern <span class="text-red-500">*</span>
                            </label>
                            <select name="movement_pattern_id"
                                    required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                <option value="">Select movement pattern...</option>
                                @foreach($movementPatterns as $movementPattern)
                                    <option value="{{ $movementPattern->id }}" {{ old('movement_pattern_id', $exercise->movement_pattern_id) == $movementPattern->id ? 'selected' : '' }}>
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
                            <select name="target_region_id"
                                    required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                <option value="">Select target region...</option>
                                @foreach($targetRegions as $targetRegion)
                                    <option value="{{ $targetRegion->id }}" {{ old('target_region_id', $exercise->target_region_id) == $targetRegion->id ? 'selected' : '' }}>
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
                            <select name="equipment_type_id"
                                    required
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                <option value="">Select equipment type...</option>
                                @foreach($equipmentTypes as $equipmentType)
                                    <option value="{{ $equipmentType->id }}" {{ old('equipment_type_id', $exercise->equipment_type_id) == $equipmentType->id ? 'selected' : '' }}>
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
                            <select name="angle_id"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                <option value="">None</option>
                                @foreach($angles as $angle)
                                    <option value="{{ $angle->id }}" {{ old('angle_id', $exercise->angle_id) == $angle->id ? 'selected' : '' }}>
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
                                    <option value="{{ $trainingStyle->id }}" {{ in_array($trainingStyle->id, old('training_style_ids', $selectedTrainingStyleIds ?? [])) ? 'selected' : '' }}>
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
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Primary Muscle Groups
                            </label>
                            <select name="primary_muscle_group_ids[]"
                                    multiple
                                    size="6"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                                @foreach($muscleGroups as $muscleGroup)
                                    <option value="{{ $muscleGroup->id }}" {{ in_array($muscleGroup->id, old('primary_muscle_group_ids', $primaryMuscleGroupIds ?? [])) ? 'selected' : '' }}>
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
                                    <option value="{{ $muscleGroup->id }}" {{ in_array($muscleGroup->id, old('secondary_muscle_group_ids', $secondaryMuscleGroupIds ?? [])) ? 'selected' : '' }}>
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

                <!-- Media Section -->
                <x-common.component-card title="Media">
                    @if($exercise->muscle_group_image)
                        <div class="mb-6 rounded-lg border border-gray-200 bg-blue-50 p-4 dark:border-gray-800 dark:bg-blue-900/20">
                            <div class="mb-3 flex items-center gap-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Muscle Group Image (Auto-generated)
                                </label>
                                <x-ui.badge variant="light" color="info" size="sm">
                                    Read-only
                                </x-ui.badge>
                            </div>
                            <div class="flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                <img src="{{ Storage::url($exercise->muscle_group_image) }}" alt="Muscle group image" class="max-h-64 w-full object-contain">
                            </div>
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">This image is automatically generated based on the exercise's muscle groups.</p>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 gap-6 {{ $exercise->image && $exercise->video ? 'md:grid-cols-2' : '' }}">
                        <!-- Image -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Image
                                </label>
                                @if($exercise->image)
                                    <x-ui.badge variant="light" color="success" size="sm">
                                        Set
                                    </x-ui.badge>
                                @endif
                            </div>
                            @if($exercise->image)
                                <div class="mb-3 flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <img src="{{ Storage::url($exercise->image) }}" alt="Exercise image" class="max-h-96 w-full object-contain">
                                </div>
                                <p class="mb-2 text-xs text-green-600 dark:text-green-400">✓ Image is currently set</p>
                            @endif
                            <input type="file"
                                   name="image"
                                   accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                   class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if($exercise->image)
                                    Upload a new image to replace the current one (leave empty to keep current)
                                @else
                                    Upload an image for this exercise
                                @endif
                            </p>
                        </div>

                        <!-- Video -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Video
                                </label>
                                @if($exercise->video)
                                    <x-ui.badge variant="light" color="success" size="sm">
                                        Set
                                    </x-ui.badge>
                                @endif
                            </div>
                            @if($exercise->video)
                                <div class="mb-3 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <video src="{{ Storage::url($exercise->video) }}" controls muted class="video-no-sound h-auto w-full">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                                <p class="mb-2 text-xs text-green-600 dark:text-green-400">✓ Video is currently set</p>
                            @endif
                            <input type="file"
                                   name="video"
                                   accept="video/mp4,video/webm,video/ogg"
                                   class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if($exercise->video)
                                    Upload a new video to replace the current one (leave empty to keep current)
                                @else
                                    Upload a video for this exercise
                                @endif
                            </p>
                        </div>
                    </div>
                </x-common.component-card>
            </div>

            <!-- Right Column: Status & Quick Info -->
            <div class="space-y-6">
                <!-- Status Card -->
                <x-common.component-card title="Status">
                    <div class="space-y-6">
                        <!-- Exercise Name -->
                        <div>
                            <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Exercise Name
                            </label>
                            <input type="text"
                                   value="{{ $exercise->name }}"
                                   disabled
                                   class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 outline-none dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Category
                            </label>
                            <div class="flex items-center gap-2" style="color: {{ $exercise->category->color }};">
                                <span class="text-lg">{{ $exercise->category->icon }}</span>
                                <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $exercise->category->name }}</span>
                            </div>
                        </div>

                        <!-- Default Rest Time -->
                        <div>
                            <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Default Rest Time
                            </label>
                            <p class="text-base font-medium text-gray-800 dark:text-white/90">{{ $exercise->default_rest_sec }} seconds</p>
                        </div>
                    </div>
                </x-common.component-card>

                <!-- Footer Buttons -->
                <div class="flex gap-2">
                    <a href="{{ route('exercises.index', $exercise) }}" class="flex-1">
                        <x-ui.button type="button"
                                variant="outline"
                                className="w-full">
                            Cancel
                        </x-ui.button>
                    </a>
                    <x-ui.button type="submit"
                            variant="primary"
                            className="flex-1">
                        Save Changes
                    </x-ui.button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
