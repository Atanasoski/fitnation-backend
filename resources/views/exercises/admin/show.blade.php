@extends('layouts.app')

@section('title', $exercise->name)

@section('content')
<div x-data="{
    exerciseId: {{ $exercise->id }},
    imageUrl: @js($exercise->muscle_group_image ? Storage::url($exercise->muscle_group_image) : ''),
    isLoading: false,
    async updateMuscleImage() {
        this.isLoading = true;
        try {
            const response = await fetch(`/admin/exercises/${this.exerciseId}/update-muscle-group-image`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content'),
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (data.success) {
                this.imageUrl = data.image_url;
                alert('Muscle group image updated successfully!');
            } else {
                alert(data.error || 'Failed to update muscle group image.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while updating the muscle group image.');
        } finally {
            this.isLoading = false;
        }
    }
}" class="space-y-6">
    <!-- Breadcrumb -->
    <x-common.page-breadcrumb :pageTitle="$exercise->name" :items="[['label' => 'Exercise Library', 'url' => route('exercises.index')]]" />

    <!-- Action Buttons -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                View exercise details
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('exercises.edit', $exercise) }}">
                <x-ui.button variant="primary" size="md">
                    <x-slot:startIcon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </x-slot:startIcon>
                    Edit
                </x-ui.button>
            </a>
            <form action="{{ route('exercises.destroy', $exercise) }}"
                  method="POST"
                  class="inline"
                  onsubmit="return confirm('Delete this exercise? This action cannot be undone.')">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="outline" size="md" className="text-error-600! ring-error-300! hover:bg-error-50! dark:text-error-400! dark:ring-error-700! dark:hover:bg-error-500/10!">
                    <x-slot:startIcon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </x-slot:startIcon>
                    Delete
                </x-ui.button>
            </form>
            <a href="{{ route('exercises.index') }}">
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
        <!-- Left Column: Basic Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Exercise Details Card -->
            <x-common.component-card title="Exercise Information">
                <div>
                    <!-- Description -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        @if($exercise->description)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                                <p class="text-base text-gray-800 dark:text-white/90 whitespace-pre-wrap">{{ $exercise->description }}</p>
                            </div>
                        @else
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                                <p class="text-base text-gray-500 dark:text-gray-400 italic">No description available</p>
                            </div>
                        @endif
                    </div>
                </div>
            </x-common.component-card>

            <!-- Media Section -->
            @if($exercise->muscle_group_image || $exercise->image || $exercise->video)
                <x-common.component-card title="Media">
                    <div class="grid grid-cols-1 gap-6 {{ ($exercise->image && $exercise->video) || ($exercise->muscle_group_image && ($exercise->image || $exercise->video)) ? 'md:grid-cols-2' : '' }}">
                        <!-- Muscle Group Image -->
                            <div>
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Muscle Group Image
                                    <x-ui.badge variant="light" color="info" size="sm" className="ml-2">
                                        Auto-generated
                                    </x-ui.badge>
                                </label>
                                <button type="button"
                                        @click="updateMuscleImage()"
                                        :disabled="isLoading"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-white/3 dark:text-gray-300 dark:hover:bg-white/5">
                                    <template x-if="!isLoading">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </template>
                                    <template x-if="isLoading">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </template>
                                    <span x-text="isLoading ? 'Updating...' : 'Update Image'"></span>
                                </button>
                            </div>
                                <div class="flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                <img x-show="imageUrl" :src="imageUrl" alt="Muscle group image" class="max-h-96 w-full object-contain" />
                                <div x-show="!imageUrl" class="flex items-center justify-center py-8">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No muscle group image available</p>
                                </div>
                            </div>
                        </div>

                        <!-- Image -->
                        @if($exercise->image)
                            <div>
                                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Image
                                </label>
                                <div class="flex items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <img src="{{ Storage::url($exercise->image) }}" alt="Exercise image" class="max-h-96 w-full object-contain">
                                </div>
                            </div>
                        @endif

                        <!-- Video -->
                        @if($exercise->video)
                            <div>
                                <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Video
                                </label>
                                <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                                    <video src="{{ Storage::url($exercise->video) }}" controls muted class="video-no-sound h-auto w-full">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-common.component-card>
            @endif
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
                        <p class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $exercise->name }}</p>
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
        </div>
    </div>
</div>
@endsection
