@extends('layouts.app')

@section('title', $exercise->name)

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb :pageTitle="$exercise->name" :items="[['label' => 'Exercise Library', 'url' => route('partner.exercises.index')]]" />

    <!-- Action Buttons -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                View exercise details and customizations
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($isLinked)
                <a href="{{ route('partner.exercises.edit', $exercise) }}">
                    <x-ui.button variant="primary" size="md">
                        <x-slot:startIcon>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </x-slot:startIcon>
                        Edit
                    </x-ui.button>
                </a>
                <form action="{{ route('exercises.unlink', $exercise) }}"
                      method="POST"
                      class="inline"
                      onsubmit="return confirm('Unlink this exercise? This will remove all customizations.')">
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="md" className="text-orange-600! ring-orange-300! hover:bg-orange-50! dark:text-orange-400! dark:ring-orange-700! dark:hover:bg-orange-500/10!">
                        <x-slot:startIcon>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <x-ui.button type="submit" variant="primary" size="md">
                        <x-slot:startIcon>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </x-slot:startIcon>
                        Add to inventory
                    </x-ui.button>
                </form>
            @endif
            <a href="{{ route('partner.exercises.index') }}">
                <x-ui.button variant="outline" size="md">
                    <x-slot:startIcon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </x-slot:startIcon>
                    Back to Index
                </x-ui.button>
            </a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Left Column: Form Fields -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Exercise Information Card -->
            <x-common.component-card title="Exercise Information">
                <div>
                    <!-- Custom Description -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Custom Description
                        </label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                            @if($partnerView->description)
                                <p class="text-base text-gray-800 dark:text-white/90 whitespace-pre-wrap">{{ $partnerView->description }}</p>
                            @else
                                <p class="text-base text-gray-500 dark:text-gray-400 italic">No custom description (using default)</p>
                            @endif
                        </div>
                        @if($exercise->description && ! $partnerView->hasDescriptionOverride)
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
                        <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <img src="{{ Storage::url($exercise->muscle_group_image) }}" alt="Muscle group image" class="max-h-96 w-full object-contain">
                        </div>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">This image is automatically generated based on the exercise's muscle groups.</p>
                    </div>
                @endif
                <!-- Custom Image -->
                <div class="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="mb-3 flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Custom Image
                        </label>
                        @if($partnerView->hasImageOverride)
                            <x-ui.badge variant="light" color="success" size="sm">
                                Custom Set
                            </x-ui.badge>
                        @elseif($exercise->image)
                            <x-ui.badge variant="light" color="light" size="sm">
                                Using Default
                            </x-ui.badge>
                        @endif
                    </div>
                    @if($partnerView->imageUrl)
                        <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <img src="{{ $partnerView->imageUrl }}" alt="Exercise image" class="h-auto w-full object-contain">
                        </div>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            @if($partnerView->hasImageOverride)
                                Custom image is currently set
                            @else
                                Currently using default image
                            @endif
                        </p>
                    @else
                        <div class="flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <p class="py-8 text-sm text-gray-500 dark:text-gray-400">No image available</p>
                        </div>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Upload an image to customize</p>
                    @endif
                </div>

                <!-- Custom Video -->
                <div class="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="mb-3 flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Custom Video
                        </label>
                        @if($partnerView->hasVideoOverride)
                            <x-ui.badge variant="light" color="success" size="sm">
                                Custom Set
                            </x-ui.badge>
                        @elseif($exercise->video)
                            <x-ui.badge variant="light" color="light" size="sm">
                                Using Default
                            </x-ui.badge>
                        @endif
                    </div>
                    @if($partnerView->videoUrl)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <video src="{{ $partnerView->videoUrl }}" controls preload="metadata" playsinline muted class="video-no-sound max-h-96 w-full rounded-lg">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                            @if($partnerView->hasVideoOverride)
                                Custom video is currently set
                            @else
                                Currently using default video
                            @endif
                        </p>
                    @else
                        <div class="flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <p class="py-8 text-sm text-gray-500 dark:text-gray-400">No video available</p>
                        </div>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Upload a video to customize</p>
                    @endif
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
                                @if($partnerView->hasDescriptionOverride)
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
                                @if($partnerView->hasImageOverride)
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
                                @if($partnerView->hasVideoOverride)
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
        </div>
    </div>
</div>
@endsection
