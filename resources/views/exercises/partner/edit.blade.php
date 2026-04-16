@extends('layouts.app')

@section('title', 'Edit ' . $exercise->name)

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb :pageTitle="'Edit ' . $exercise->name" :items="[['label' => 'Exercise Library', 'url' => route('partner.exercises.index')]]" />

    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Customize this exercise for your partner
            </p>
        </div>
    </div>

    <!-- Edit Form -->
    <form action="{{ route('exercises.updatePartner', $exercise) }}"
        method="POST"
        enctype="multipart/form-data"
        x-data="{
            submitting: false,
            removeVideo() {
                const form = $el;
                const videoEntry = form._filepondEntries?.find(e => e.options?.name === 'video');
                if (videoEntry?.pond) videoEntry.pond.removeFiles();
                let input = form.querySelector('input[name=remove_video]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'remove_video';
                    form.appendChild(input);
                }
                input.value = '1';
                form.requestSubmit();
            }
        }"
        @submit.capture="submitting = true">
        @csrf
        @method('PUT')

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Form Fields -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Classification & Muscle Groups (read-only) -->
                <x-common.component-card title="Classification & Muscle Groups">
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Set by the exercise library. Shown for reference only.</p>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <h4 class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Classification</h4>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Movement Pattern</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $exercise->movementPattern?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Target Region</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $exercise->targetRegion?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Equipment Type</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $exercise->equipmentType?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Angle</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $exercise->angle?->name ?? 'None' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h4 class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Muscle Groups</h4>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Primary</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $exercise->primaryMuscleGroups->pluck('name')->join(', ') ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">Secondary</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $exercise->secondaryMuscleGroups->pluck('name')->join(', ') ?: '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </x-common.component-card>

                <!-- Exercise Information Card -->
                <x-common.component-card title="Exercise Information">
                    <div>
                        <!-- Custom Description -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Custom Description
                            </label>
                            <textarea name="description"
                                      rows="6"
                                      placeholder="Enter custom description for this exercise (leave empty to use default)..."
                                      class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">{{ $formDescription }}</textarea>
                            @if($exercise->description && (!$pivot || !$pivot->description))
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Currently using default description</p>
                            @endif
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
                    <div class="grid grid-cols-1 gap-6 {{ ($formImage || $exercise->image) && ($formVideo || $exercise->video) ? 'md:grid-cols-2' : '' }}">
                        <!-- Custom Image -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Custom Image
                                </label>
                                @if($formImage)
                                    <x-ui.badge variant="light" color="success" size="sm">
                                        Custom Set
                                    </x-ui.badge>
                                @elseif($exercise->image)
                                    <x-ui.badge variant="light" color="light" size="sm">
                                        Using Default
                                    </x-ui.badge>
                                @endif
                            </div>
                            @if($formImage)
                                <div class="mb-3 flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <img src="{{ Storage::url($formImage) }}" alt="Exercise image" class="max-h-96 w-full object-contain">
                                </div>
                                <p class="mb-2 text-xs text-green-600 dark:text-green-400">✓ Custom image is currently set</p>
                            @elseif($exercise->image)
                                <div class="mb-3 flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <img src="{{ Storage::url($exercise->image) }}" alt="Exercise image" class="max-h-96 w-full object-contain">
                                </div>
                                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Currently using default image</p>
                            @endif
                            <x-form.filepond
                                name="image"
                                label=""
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                maxFileSize="5MB"
                                :required="false"
                                :allowCrop="false"
                                :currentFileUrl="$formImage ? Storage::url($formImage) : null"
                                hint="{{ $formImage ? 'Upload a new image to replace the current custom one (leave empty to keep current).' : 'Upload a custom image for this exercise (leave empty to use default). Max 5MB.' }}"
                            />
                        </div>

                        <!-- Custom Video -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Custom Video
                                </label>
                                @if($formVideo)
                                    <x-ui.badge variant="light" color="success" size="sm">
                                        Custom Set
                                    </x-ui.badge>
                                @elseif($exercise->video)
                                    <x-ui.badge variant="light" color="light" size="sm">
                                        Using Default
                                    </x-ui.badge>
                                @endif
                            </div>
                            @if($formVideo)
                                <div class="mb-3 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <video src="{{ Storage::url($formVideo) }}" controls muted class="video-no-sound h-auto w-full">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                                <p class="mb-2 text-xs text-green-600 dark:text-green-400">✓ Custom video is currently set</p>
                                <button type="button" @click="if (confirm('Remove custom video? The exercise will use the default video (if any).')) removeVideo()" class="text-sm font-medium text-error-600 hover:text-error-700 dark:text-error-400 dark:hover:text-error-300">
                                    Remove custom video
                                </button>
                            @elseif($exercise->video)
                                <div class="mb-3 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <video src="{{ Storage::url($exercise->video) }}" controls muted class="video-no-sound h-auto w-full">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Currently using default video</p>
                            @endif
                            <x-form.filepond
                                name="video"
                                label=""
                                accept="video/mp4,video/webm,video/ogg"
                                maxFileSize="50MB"
                                :required="false"
                                :allowCrop="false"
                                hint="{{ $formVideo ? 'Upload a new video to replace the current custom one (leave empty to keep current). Max 50MB.' : 'Upload a custom video for this exercise (leave empty to use default). Max 50MB.' }}"
                            />
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

                        <!-- Current Customizations -->
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                            <label class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                Current Customizations
                            </label>
                            <div class="space-y-2">
                                <!-- Custom Description -->
                                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    @if($pivot && $pivot->description)
                                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    @endif
                                    <span>Custom Description</span>
                                </div>

                                <!-- Custom Image -->
                                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    @if($pivot && $pivot->image)
                                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    @endif
                                    <span>Custom Image</span>
                                </div>

                                <!-- Custom Video -->
                                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    @if($pivot && $pivot->video)
                                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    @endif
                                    <span>Custom Video</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-common.component-card>

                <!-- Footer Buttons -->
                <div class="flex gap-2">
                    <a href="{{ route('partner.exercises.index') }}" class="flex-1">
                        <x-ui.button type="button"
                                variant="outline"
                                className="w-full">
                            Cancel
                        </x-ui.button>
                    </a>
                    <button type="submit"
                        :disabled="submitting"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-5 py-3.5 text-sm font-medium transition bg-gray-900 text-white shadow-theme-xs hover:bg-gray-800 disabled:opacity-50 disabled:bg-gray-400 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 dark:disabled:bg-gray-500 dark:disabled:text-gray-300">
                        <span x-show="!submitting">Save Changes</span>
                        <span x-show="submitting" class="inline-flex items-center gap-2" style="display: none;">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
