@props([
    'action' => '',
    'method' => 'POST',
    'plan' => null,
    'cancelUrl' => null,
    'alpine' => false,
])

@if($alpine)
    {{-- Alpine-driven form for edit modal (opened from table). Parent must have editingPlan and closeEditModal. --}}
    <form :action="editingPlan?.updateUrl" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="type" :value="editingPlan.type ?? 'program'">

        <div class="space-y-5">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Program Name <span class="text-red-500">*</span>
                </label>
                <input type="text"
                    name="name"
                    required
                    x-model="editingPlan.name"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea name="description"
                    rows="4"
                    x-model="editingPlan.description"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Duration (weeks)
                </label>
                <input type="number"
                    name="duration_weeks"
                    min="1"
                    max="52"
                    x-model="editingPlan.duration_weeks"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recommended duration for this program (1-52 weeks)</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-6 dark:border-gray-800">
            <button type="button"
                @click="closeEditModal()"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                Cancel
            </button>
            <x-ui.button variant="primary" size="md" type="submit">
                Update Program
            </x-ui.button>
        </div>
    </form>
@else
    @include('plans._form', [
        'action' => $action,
        'method' => $method,
        'plan' => $plan,
        'context' => 'library',
        'cancelUrl' => $cancelUrl,
    ])
@endif
