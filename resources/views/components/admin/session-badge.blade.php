{{-- One session's status pill: the Stuck badge while the session is stuck (CONTEXT.md), the status label otherwise. --}}
@props(['session'])
@if ($session->isStuck())
    <span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full bg-error-50 px-2 py-0.5 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400']) }} title="Active since {{ $session->performed_at->format('j M Y H:i') }}">Stuck</span>
@else
    <span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-theme-xs font-medium', $session->status_badge_classes]) }}>{{ $session->status_label }}</span>
@endif
