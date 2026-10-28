{{--
    The Station form: name plus the curated, ordered exercise list.
    $exercises is every exercise linked to the partner; $selectedIds is the
    station's current list in display order.
--}}
<div
    class="grid grid-cols-1 gap-6 lg:grid-cols-2"
    x-data="{
        exercises: {{ Js::from($exercises) }},
        selected: {{ Js::from(old('exercise_ids', $selectedIds)) }}.map(Number),
        search: '',

        byId(id) {
            return this.exercises.find(exercise => exercise.id === id);
        },
        get available() {
            const q = this.search.trim().toLowerCase();
            return this.exercises.filter(exercise =>
                !this.selected.includes(exercise.id)
                && (q === '' || exercise.name.toLowerCase().includes(q))
            );
        },
        add(id) {
            if (!this.selected.includes(id)) this.selected.push(id);
        },
        remove(id) {
            this.selected = this.selected.filter(selectedId => selectedId !== id);
        },
        move(index, delta) {
            const target = index + delta;
            if (target < 0 || target >= this.selected.length) return;
            const next = [...this.selected];
            [next[index], next[target]] = [next[target], next[index]];
            this.selected = next;
        },
    }"
>
    <div class="space-y-6">
        <x-common.component-card title="Station">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Name <span class="text-error-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $station->name) }}"
                        required
                        placeholder="e.g. Cable Crossover"
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                    >
                    @error('name')
                        <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                @if ($station->exists)
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Short code</label>
                        <p class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-gray-800 dark:border-gray-800 dark:bg-white/3 dark:text-white/90">{{ $station->short_code }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Printed on stickers — it never changes, even if the station is renamed.</p>
                    </div>
                @endif
            </div>
        </x-common.component-card>

        <x-common.component-card title="Curated exercises" desc="In the order members see them on the public page.">
            <div class="space-y-1">
                <template x-if="selected.length === 0">
                    <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">No exercises yet. Pick from the partner's exercises on the right.</p>
                </template>
                <template x-for="(id, index) in selected" :key="id">
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                        <input type="hidden" name="exercise_ids[]" :value="id">
                        <span class="w-6 text-center text-theme-xs font-semibold text-gray-400 dark:text-gray-500" x-text="index + 1"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="byId(id)?.name"></p>
                            <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400" x-text="byId(id)?.equipment"></p>
                        </div>
                        <button type="button" @click="move(index, -1)" :disabled="index === 0" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-brand-600 disabled:opacity-30 dark:hover:bg-white/5 dark:hover:text-brand-400" title="Move up">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button type="button" @click="move(index, 1)" :disabled="index === selected.length - 1" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-brand-600 disabled:opacity-30 dark:hover:bg-white/5 dark:hover:text-brand-400" title="Move down">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <button type="button" @click="remove(id)" class="rounded p-1 text-gray-400 hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10 dark:hover:text-error-400" title="Remove">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
            </div>
            @error('exercise_ids')
                <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
            @enderror
            @error('exercise_ids.*')
                <p class="mt-2 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
            @enderror
        </x-common.component-card>
    </div>

    <div class="space-y-6">
        <x-common.component-card title="Partner exercises" desc="Only exercises linked to {{ $partner->name }} can go on a station.">
            <div class="space-y-3">
                <input
                    type="text"
                    x-model="search"
                    placeholder="Search exercises..."
                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500"
                >
                <div class="custom-scrollbar max-h-96 space-y-1 overflow-y-auto pr-1">
                    <template x-if="available.length === 0">
                        <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">No exercises to add.</p>
                    </template>
                    <template x-for="exercise in available" :key="exercise.id">
                        <button
                            type="button"
                            @click="add(exercise.id)"
                            class="group flex w-full items-center justify-between rounded-lg border border-transparent px-3 py-2 text-left transition-all hover:border-gray-200 hover:bg-gray-50 dark:hover:border-gray-700 dark:hover:bg-white/5"
                        >
                            <span>
                                <span class="block text-sm font-medium text-gray-700 group-hover:text-brand-600 dark:text-gray-300 dark:group-hover:text-brand-400" x-text="exercise.name"></span>
                                <span class="block text-theme-xs text-gray-500 dark:text-gray-400" x-text="exercise.equipment"></span>
                            </span>
                            <span class="text-theme-xs font-medium text-brand-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-brand-400">Add</span>
                        </button>
                    </template>
                </div>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Actions">
            <div class="flex gap-2">
                <a href="{{ route('admin.partners.show', $partner) }}" class="flex-1">
                    <x-ui.button type="button" variant="outline" className="w-full">Cancel</x-ui.button>
                </a>
                <x-ui.button type="submit" variant="primary" className="flex-1">
                    {{ $station->exists ? 'Save station' : 'Create station' }}
                </x-ui.button>
            </div>
        </x-common.component-card>
    </div>
</div>
