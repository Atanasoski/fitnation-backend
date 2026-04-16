@php
    use App\Enums\BillingCycle;
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Name <span class="text-red-500">*</span>
        </label>
        <input type="text" name="name" id="name" value="{{ old('name', isset($subscriptionPlan) ? $subscriptionPlan->name : '') }}" required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
        @error('name')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Price <span class="text-red-500">*</span>
        </label>
        <input type="number" name="price" id="price" step="0.01" min="0" required
            value="{{ old('price', isset($subscriptionPlan) ? $subscriptionPlan->price : '') }}"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
        @error('price')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
    <div class="lg:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            Billing cycle <span class="text-red-500">*</span>
        </label>
        <select name="billing_cycle" id="billing_cycle" required
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            @foreach (BillingCycle::cases() as $cycle)
                <option value="{{ $cycle->value }}" @selected(old('billing_cycle', isset($subscriptionPlan) ? $subscriptionPlan->billing_cycle?->value : '') === $cycle->value)>
                    {{ str($cycle->value)->replace('_', ' ')->title() }}
                </option>
            @endforeach
        </select>
        @error('billing_cycle')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
    <div class="lg:col-span-2">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
        <textarea name="description" id="description" rows="3"
            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('description', isset($subscriptionPlan) ? $subscriptionPlan->description : '') }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>
    <div class="lg:col-span-2">
        <input type="hidden" name="is_active" value="0" />
        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-400">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-gray-900 dark:border-gray-600"
                @checked(old('is_active', isset($subscriptionPlan) ? $subscriptionPlan->is_active : true)) />
            Plan is active (visible for new subscriptions)
        </label>
    </div>
</div>
