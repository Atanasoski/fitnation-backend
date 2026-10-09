@props(['access'])
{{-- An Access Source chip; the detail line rides along as a tooltip. Takes an App\Services\Access\Access. --}}
@php
    $classes = match ($access->source) {
        \App\Enums\AccessSource::Subscribed => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
        \App\Enums\AccessSource::Trial => 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        \App\Enums\AccessSource::Cancelled => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        \App\Enums\AccessSource::BillingIssue => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
        \App\Enums\AccessSource::Paused => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        \App\Enums\AccessSource::Sponsored => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
        \App\Enums\AccessSource::SignupTrial => 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
        \App\Enums\AccessSource::Complimentary => 'bg-theme-purple-500/10 text-theme-purple-500',
        \App\Enums\AccessSource::None => 'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-400',
    };
@endphp
<span title="{{ $access->detail() }}" {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-theme-xs font-medium', $classes]) }}>{{ $access->source->label() }}</span>
