@if ($partner->is_active)
    <span class="inline-block rounded-full bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Active</span>
@else
    <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">Inactive</span>
@endif
