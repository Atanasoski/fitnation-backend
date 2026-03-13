@props([
    'plan' => null,
    'action' => '',
    'method' => 'POST',
    'context' => 'library', // 'library' or 'user'
    'user' => null,
    'cancelUrl' => null,
    'cancelAlpineHandler' => null, // when set, Cancel is a button that calls this (e.g. 'closeCreatePlanModal')
])

<form action="{{ $action }}"
    method="POST"
    enctype="multipart/form-data"
    x-data="{ submitting: false }"
    @submit.capture="submitting = true">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <!-- Plan Name -->
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                {{ $context === 'library' ? 'Program' : 'Plan' }} Name <span class="text-red-500">*</span>
            </label>
            <input type="text"
                name="name"
                id="name"
                value="{{ old('name', $plan?->name) }}"
                required
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('name') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
            @error('name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Description -->
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Description
            </label>
            <textarea name="description"
                id="description"
                rows="4"
                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('description') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror">{{ old('description', $plan?->description) }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <x-form.filepond
            name="cover_image"
            label="Cover image"
            accept="image/jpeg,image/png,image/webp"
            maxFileSize="5MB"
            :required="false"
            :allowCrop="false"
            resizeTargetWidth="2400"
            resizeTargetHeight="2400"
            resizeMode="contain"
            :currentFileUrl="$plan?->cover_image ? Storage::url($plan->cover_image) : null"
            hint="Optional. Whole image kept (no crop). Saved as JPEG; PNG/WebP are converted and compressed. Max 2400 px, max 5MB."
        />

        @if($context === 'user')
            <!-- User context: Always a program, duration always shown -->
            <input type="hidden" name="type" value="program">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Duration (weeks)
                </label>
                <input type="number"
                    name="duration_weeks"
                    id="duration_weeks"
                    value="{{ old('duration_weeks', $plan?->duration_weeks) }}"
                    min="1"
                    max="52"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('duration_weeks') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                @error('duration_weeks')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Number of weeks for this program (1-52)</p>
            </div>

            <!-- Active Checkbox (only for user plans) -->
            <div x-data="{ checkboxToggle: {{ old('is_active', $plan?->is_active ?? false) ? 'true' : 'false' }} }">
                <input type="hidden" name="is_active" value="0" />
                <label for="is_active"
                    class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                    <div class="relative">
                        <input type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            class="sr-only"
                            @change="checkboxToggle = !checkboxToggle"
                            {{ old('is_active', $plan?->is_active ?? false) ? 'checked' : '' }} />
                        <div :class="checkboxToggle ? 'border-brand-500 bg-brand-500' :
                            'bg-transparent border-gray-300 dark:border-gray-700'"
                            class="hover:border-brand-500 dark:hover:border-brand-500 mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px]">
                            <span :class="checkboxToggle ? '' : 'opacity-0'">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white"
                                        stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>
                    Set as Active Plan
                </label>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Only one plan can be active at a time. Activating this plan will deactivate all other plans for this user.</p>
            </div>
        @else
            <!-- Library context: preserve existing type or default to program -->
            <input type="hidden" name="type" value="{{ old('type', $plan?->type?->value ?? 'program') }}">

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Duration (weeks)
                </label>
                <input type="number"
                    name="duration_weeks"
                    id="duration_weeks"
                    value="{{ old('duration_weeks', $plan?->duration_weeks) }}"
                    min="1"
                    max="52"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('duration_weeks') border-red-300 focus:border-red-300 focus:ring-red-500/10 dark:border-red-700 dark:focus:border-red-800 @enderror" />
                @error('duration_weeks')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recommended duration for this program (1-52 weeks)</p>
            </div>

            <!-- Available in library (library programs) -->
            <div x-data="{ checkboxToggle: {{ old('is_active', $plan?->is_active ?? true) ? 'true' : 'false' }} }">
                <input type="hidden" name="is_active" value="0" />
                <label for="is_active_library"
                    class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                    <div class="relative">
                        <input type="checkbox"
                            id="is_active_library"
                            name="is_active"
                            value="1"
                            class="sr-only"
                            @change="checkboxToggle = !checkboxToggle"
                            {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }} />
                        <div :class="checkboxToggle ? 'border-brand-500 bg-brand-500' :
                            'bg-transparent border-gray-300 dark:border-gray-700'"
                            class="hover:border-brand-500 dark:hover:border-brand-500 mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px]">
                            <span :class="checkboxToggle ? '' : 'opacity-0'">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white"
                                        stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>
                    Available in library
                </label>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">When off, this program is hidden from the program library in the app.</p>
            </div>
        @endif
    </div>

    <!-- Form Actions -->
    <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
        @if($cancelAlpineHandler)
            <x-ui.button variant="outline" size="md" type="button" @click="{{ $cancelAlpineHandler }}()">
                Cancel
            </x-ui.button>
        @else
            <a href="{{ $context === 'library' ? ($cancelUrl ?? route('partner.programs.index')) : route('plans.index', $user) }}">
                <x-ui.button variant="outline" size="md">
                    Cancel
                </x-ui.button>
            </a>
        @endif
        <button type="submit"
            :disabled="submitting"
            class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3.5 text-sm font-medium transition bg-gray-900 text-white shadow-theme-xs hover:bg-gray-800 disabled:opacity-50 disabled:bg-gray-400 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 dark:disabled:bg-gray-500 dark:disabled:text-gray-300">
            <span x-show="!submitting">{{ $plan ? 'Update' : 'Create' }} {{ $context === 'library' ? 'Program' : 'Plan' }}</span>
            <span x-show="submitting" class="inline-flex items-center gap-2" style="display: none;">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ $plan ? 'Updating' : 'Creating' }}...
            </span>
        </button>
    </div>
</form>
