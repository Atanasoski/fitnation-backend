<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="space-y-6">
        <x-common.component-card title="Split Configuration">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Days Per Week <span class="text-error-500">*</span>
                    </label>
                    <select
                        name="days_per_week"
                        required
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                    >
                        <option value="">Select days per week...</option>
                        @for($i = 1; $i <= 7; $i++)
                            <option value="{{ $i }}" {{ old('days_per_week', isset($workoutSplit) && $workoutSplit->exists ? $workoutSplit->days_per_week : '') == $i ? 'selected' : '' }}>
                                {{ $i }} day{{ $i > 1 ? 's' : '' }}
                            </option>
                        @endfor
                    </select>
                    @error('days_per_week')
                        <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Focus <span class="text-error-500">*</span>
                    </label>
                    <select
                        name="focus"
                        required
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                    >
                        <option value="">Select focus...</option>
                        @foreach($focusOptions as $focus)
                            <option value="{{ $focus->value }}" {{ old('focus', isset($workoutSplit) && $workoutSplit->exists ? $workoutSplit->focus->value : '') == $focus->value ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $focus->value)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('focus')
                        <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Day Index <span class="text-error-500">*</span>
                    </label>
                    <input
                        type="number"
                        name="day_index"
                        value="{{ old('day_index', isset($workoutSplit) && $workoutSplit->exists ? $workoutSplit->day_index : 0) }}"
                        min="0"
                        max="6"
                        required
                        placeholder="0"
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                    >
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">0-based index for the day order within the split</p>
                    @error('day_index')
                        <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-common.component-card>
    </div>

    <div class="space-y-6">
        <x-common.component-card title="Target Regions">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Target Regions <span class="text-error-500">*</span>
                    </label>
                    <select
                        name="target_regions[]"
                        multiple
                        size="8"
                        required
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                    >
                        @foreach($targetRegions as $region)
                            <option
                                value="{{ $region->code }}"
                                {{ in_array($region->code, old('target_regions', isset($workoutSplit) && $workoutSplit->exists ? $workoutSplit->target_regions : [])) ? 'selected' : '' }}
                            >
                                {{ $region->name }} ({{ $region->code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</p>
                    @error('target_regions')
                        <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                    @error('target_regions.*')
                        <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Actions">
            <div class="flex gap-2">
                <a href="{{ route('workout-splits.index') }}" class="flex-1">
                    <x-ui.button type="button" variant="outline" className="w-full">
                        Cancel
                    </x-ui.button>
                </a>
                <x-ui.button type="submit" variant="primary" className="flex-1">
                    {{ (isset($workoutSplit) && $workoutSplit->exists) ? 'Update' : 'Create' }} Split
                </x-ui.button>
            </div>
        </x-common.component-card>
    </div>
</div>
