@props([
    'subtitle' => 'Update workout template details',
])

{{-- Rendered inside a parent with x-data containing editingWorkout, dayOfWeekOptions, closeEditModal. --}}
<form :action="editingWorkout?.updateUrl" method="POST">
    @csrf
    @method('PUT')

    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">
                    Edit Workout Template
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            </div>
            <button type="button"
                @click="closeEditModal"
                class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
        <div class="space-y-5">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Workout Name <span class="text-error-500">*</span>
                </label>
                <input type="text"
                    name="name"
                    x-model="editingWorkout.name"
                    required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-white dark:focus:ring-white/10" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea name="description"
                    x-model="editingWorkout.description"
                    rows="4"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-white dark:focus:ring-white/10"></textarea>
            </div>

            {{-- Day of Week (commented out)
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Day of Week</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="option in dayOfWeekOptions" :key="option.value">
                        <label class="relative inline-flex h-10 min-w-10 cursor-pointer select-none">
                            <input type="radio"
                                name="day_of_week"
                                :value="option.value"
                                x-model="editingWorkout.day_of_week"
                                class="peer sr-only" />
                            <span class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm font-semibold text-gray-700 transition-colors hover:border-gray-400 hover:bg-gray-100 peer-checked:border-gray-900 peer-checked:bg-gray-900 peer-checked:text-white dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-500 dark:hover:bg-gray-700 dark:peer-checked:border-white dark:peer-checked:bg-white dark:peer-checked:text-gray-900 peer-checked:hidden"
                                :title="option.title"
                                x-text="option.letter"></span>
                            <span class="absolute inset-0 hidden h-10 min-w-10 items-center justify-center rounded-lg border border-gray-900 bg-gray-900 text-white peer-checked:inline-flex dark:border-white dark:bg-white dark:text-gray-900"
                                :title="option.title">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </span>
                        </label>
                    </template>
                </div>
            </div>
            --}}
        </div>
    </div>

    <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
        <div class="flex justify-end gap-3">
            <button type="button"
                @click="closeEditModal"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                Cancel
            </button>
            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                Update Workout Template
            </button>
        </div>
    </div>
</form>
