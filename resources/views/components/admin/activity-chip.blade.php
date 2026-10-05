@props(['status'])
@php
    $classes = match ($status) {
        \App\Enums\ActivityStatus::Unfinished => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        \App\Enums\ActivityStatus::New => 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        \App\Enums\ActivityStatus::Active => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
        \App\Enums\ActivityStatus::Slipping => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        \App\Enums\ActivityStatus::Inactive => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        \App\Enums\ActivityStatus::Deleted => 'bg-gray-100 text-gray-400 line-through dark:bg-gray-800',
    };
@endphp
<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-theme-xs font-medium', $classes]) }}>{{ $status->label() }}</span>
