{{--
    The exercise editor slide-over, over the gallery. Create posts to
    exercises.store, edit puts to exercises.update; both carry `back` so the
    save lands on the same gallery slice. Expects $exercise (new or saved),
    $lookups, $close (the gallery URL without the slide-over) and $back.
--}}
@php
    $isNew = ! $exercise->exists;
    $label = 'mb-1 block text-theme-xs font-medium text-gray-600 dark:text-gray-400';
    $input = 'h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $heading = 'mb-2 text-theme-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $error = 'mt-1 text-theme-xs text-error-600 dark:text-error-400';
    $muscles = $exercise->exists ? $exercise->muscleGroups : collect();
    $primary = array_map('intval', (array) old('primary_muscle_group_ids', $muscles->filter(fn ($m) => $m->pivot->is_primary)->pluck('id')->all()));
    $secondary = array_map('intval', (array) old('secondary_muscle_group_ids', $muscles->reject(fn ($m) => $m->pivot->is_primary)->pluck('id')->all()));
    $styles = array_map('intval', (array) old('training_style_ids', $exercise->exists ? $exercise->trainingStyles->pluck('id')->all() : []));
    $selects = [
        'category_id' => ['Category', $lookups['categories'], true],
        'movement_pattern_id' => ['Movement pattern', $lookups['movementPatterns'], true],
        'target_region_id' => ['Target region', $lookups['targetRegions'], true],
        'equipment_type_id' => ['Equipment', $lookups['equipmentTypes'], true],
        'angle_id' => ['Angle', $lookups['angles'], false],
    ];
@endphp

<div class="fixed inset-0 z-99999 flex justify-end" x-data="{
        primary: @js($primary),
        secondary: @js($secondary),
        cycle(id) {
            if (this.primary.includes(id)) {
                this.primary = this.primary.filter(x => x !== id);
                this.secondary.push(id);
            } else if (this.secondary.includes(id)) {
                this.secondary = this.secondary.filter(x => x !== id);
            } else {
                this.primary.push(id);
            }
        },
        muscleImage: @js($exercise->muscle_group_image ? Storage::url($exercise->muscle_group_image) : null),
        regenerating: false,
        regenerateError: null,
        async regenerate() {
            this.regenerating = true;
            this.regenerateError = null;
            try {
                const response = await fetch(@js($isNew ? '' : route('exercises.updateMuscleGroupImage', $exercise)), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
                const data = await response.json();
                data.success ? this.muscleImage = data.image_url : this.regenerateError = data.error || 'Could not regenerate the image.';
            } catch (e) {
                this.regenerateError = 'Could not regenerate the image.';
            } finally {
                this.regenerating = false;
            }
        },
    }" @keydown.escape.window="window.location = @js($close)" role="dialog" aria-modal="true" aria-labelledby="exercise-editor-title">
    <a href="{{ $close }}" class="absolute inset-0 bg-gray-900/50" aria-label="Close"></a>

    <div class="relative flex h-full w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-gray-950">
        <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h2 id="exercise-editor-title" class="font-display text-lg font-semibold text-gray-800 dark:text-white/90">{{ $isNew ? 'New exercise' : $exercise->name }}</h2>
            @if ($exercise->archived_at)
                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">Archived {{ $exercise->archived_at->format('j M Y') }}</span>
            @endif
            <a href="{{ $close }}" class="ml-auto text-2xl leading-none text-gray-400 hover:text-gray-600" aria-label="Close">×</a>
        </div>

        <form id="exercise-form" method="POST" enctype="multipart/form-data"
            action="{{ $isNew ? route('exercises.store') : route('exercises.update', $exercise) }}"
            class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
            @csrf
            @unless ($isNew) @method('PUT') @endunless
            <input type="hidden" name="back" value="{{ $back }}">

            @if ($errors->any())
                <div class="rounded-lg border border-error-200 bg-error-50 p-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-900/20 dark:text-error-300">
                    Some fields need attention.
                </div>
            @endif

            {{-- Media --}}
            <section>
                <div class="{{ $heading }}">Media</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Image</div>
                        @if ($exercise->image)
                            <img src="{{ Storage::url($exercise->image) }}" alt="" class="mb-2 aspect-video w-full rounded-md object-cover">
                        @endif
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full text-theme-xs text-gray-600 dark:text-gray-400" aria-label="Image">
                        <p class="mt-1 text-theme-xs text-gray-400">JPG, PNG, GIF or WebP, up to 5 MB.</p>
                        @error('image') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Video</div>
                        @if ($exercise->video)
                            <video src="{{ Storage::url($exercise->video) }}" controls preload="none" class="mb-2 aspect-video w-full rounded-md bg-gray-900"></video>
                        @endif
                        <input type="file" name="video" accept="video/mp4,video/webm,video/ogg" class="w-full text-theme-xs text-gray-600 dark:text-gray-400" aria-label="Video">
                        <p class="mt-1 text-theme-xs text-gray-400">MP4, WebM or Ogg, up to 50 MB.</p>
                        @error('video') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Muscle-group image</div>
                        <template x-if="muscleImage"><img :src="muscleImage" alt="" class="mb-2 w-full rounded-md"></template>
                        <p x-show="! muscleImage" class="mb-2 text-theme-xs text-gray-400">Generated from the muscle groups.</p>
                        @if ($isNew)
                            <p class="text-theme-xs text-gray-400">Save first, then regenerate.</p>
                        @else
                            <button type="button" @click="regenerate()" :disabled="regenerating" data-url="{{ route('exercises.updateMuscleGroupImage', $exercise) }}"
                                class="text-theme-xs font-medium text-brand-600 hover:underline disabled:opacity-40 dark:text-brand-400"
                                x-text="regenerating ? 'Regenerating…' : 'Regenerate'">Regenerate</button>
                            <p class="mt-1 text-theme-xs text-gray-400">Uses the saved muscle groups.</p>
                            <p x-show="regenerateError" x-text="regenerateError" class="{{ $error }}"></p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Basics --}}
            <section class="space-y-3">
                <div class="{{ $heading }}">Basics</div>
                <div>
                    <label for="exercise-name" class="{{ $label }}">Name</label>
                    <input id="exercise-name" name="name" value="{{ old('name', $exercise->name) }}" required maxlength="255" class="{{ $input }}" placeholder="e.g. Barbell Bench Press">
                    @error('name') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="exercise-description" class="{{ $label }}">Description / cues</label>
                    <textarea id="exercise-description" name="description" rows="3" maxlength="5000" class="w-full rounded-lg border border-gray-300 bg-transparent p-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('description', $exercise->description) }}</textarea>
                    @error('description') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label for="exercise-difficulty" class="{{ $label }}">Difficulty</label>
                        <select id="exercise-difficulty" name="difficulty" class="{{ $input }}">
                            <option value="">Not set</option>
                            @foreach (\App\Enums\ExerciseDifficulty::cases() as $difficulty)
                                <option value="{{ $difficulty->value }}" @selected(old('difficulty', $exercise->difficulty?->value) === $difficulty->value)>{{ ucfirst($difficulty->value) }}</option>
                            @endforeach
                        </select>
                        @error('difficulty') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exercise-priority" class="{{ $label }}">Selection priority</label>
                        <input id="exercise-priority" type="number" name="selection_priority" min="0" max="1000" step="1" value="{{ old('selection_priority', $exercise->selection_priority) }}" class="{{ $input }}">
                        @error('selection_priority') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="exercise-rest" class="{{ $label }}">Default rest (s)</label>
                        <input id="exercise-rest" type="number" name="default_rest_sec" min="0" step="1" value="{{ old('default_rest_sec', $exercise->default_rest_sec) }}" class="{{ $input }}">
                        @error('default_rest_sec') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-theme-xs text-gray-400">Priority 0–1000 (default 100): the workout generator picks higher first.</p>
            </section>

            {{-- Classification --}}
            <section class="space-y-4">
                <div class="{{ $heading }}">Classification</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($selects as $field => [$title, $options, $required])
                        <div>
                            <label for="exercise-{{ $field }}" class="{{ $label }}">{{ $title }}</label>
                            <select id="exercise-{{ $field }}" name="{{ $field }}" class="{{ $input }}" @required($required)>
                                <option value="">{{ $required ? 'Choose…' : 'None' }}</option>
                                @foreach ($options as $option)
                                    <option value="{{ $option->id }}" @selected((int) old($field, $exercise->{$field}) === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                            @error($field) <p class="{{ $error }}">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                <div>
                    <span class="{{ $label }}">Muscle groups — click once for primary, twice for secondary, again to clear</span>
                    <template x-for="id in primary" :key="'p' + id"><input type="hidden" name="primary_muscle_group_ids[]" :value="id"></template>
                    <template x-for="id in secondary" :key="'s' + id"><input type="hidden" name="secondary_muscle_group_ids[]" :value="id"></template>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($lookups['muscleGroups'] as $muscle)
                            <button type="button" @click="cycle({{ $muscle->id }})"
                                class="rounded-full border px-2.5 py-0.5 text-theme-xs"
                                :class="primary.includes({{ $muscle->id }}) ? 'border-brand-500 bg-brand-500 text-white' : secondary.includes({{ $muscle->id }}) ? 'border-brand-400 bg-brand-500/15 text-brand-700 dark:text-brand-300' : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-400'">{{ $muscle->name }}</button>
                        @endforeach
                    </div>
                    <div class="mt-1 flex gap-3 text-theme-xs text-gray-500">
                        <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-brand-500"></span>primary</span>
                        <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-brand-500/30"></span>secondary</span>
                    </div>
                    @error('primary_muscle_group_ids.*') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="{{ $label }}">Training styles</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($lookups['trainingStyles'] as $style)
                            <label class="cursor-pointer rounded-full border border-gray-300 px-2.5 py-0.5 text-theme-xs text-gray-600 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500/15 has-[:checked]:text-orange-600 dark:border-gray-700 dark:text-gray-400 dark:has-[:checked]:text-orange-400">
                                <input type="checkbox" name="training_style_ids[]" value="{{ $style->id }}" class="sr-only" @checked(in_array($style->id, $styles, true))>
                                {{ $style->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>
        </form>

        <div class="flex items-center gap-2 border-t border-gray-200 px-6 py-3 dark:border-gray-800">
            @unless ($isNew)
                @if ($exercise->archived_at)
                    <form method="POST" action="{{ route('exercises.restore', $exercise) }}">
                        @csrf
                        <input type="hidden" name="back" value="{{ $back }}">
                        <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 hover:bg-brand-500/10 dark:text-brand-400">Restore</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('exercises.destroy', $exercise) }}"
                        onsubmit="return confirm('Delete this exercise? If anyone used it in a plan or a logged session it is archived instead, and their history keeps it.')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="back" value="{{ $back }}">
                        <button type="submit" class="rounded-lg px-3 py-2 text-sm text-error-500 hover:bg-error-500/10">Delete</button>
                    </form>
                @endif
            @endunless
            <a href="{{ $close }}" class="ml-auto rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Cancel</a>
            <button type="submit" form="exercise-form" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">{{ $isNew ? 'Create' : 'Save' }}</button>
        </div>
    </div>
</div>
