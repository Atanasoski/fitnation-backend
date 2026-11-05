@extends('layouts.app')

@section('title', $user->name.' · Sessions')

@section('content')
    @php
        $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
        $select = 'rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';
        $options = [
            \App\Enums\WorkoutSessionStatus::Active->value => 'In Progress',
            \App\Http\Controllers\Admin\UserSessionController::STUCK_FILTER => 'Stuck',
            \App\Enums\WorkoutSessionStatus::Completed->value => 'Completed',
            \App\Enums\WorkoutSessionStatus::Cancelled->value => 'Cancelled',
            \App\Enums\WorkoutSessionStatus::Draft->value => 'Draft',
        ];
        $current = $filter instanceof \App\Enums\WorkoutSessionStatus ? $filter->value : $filter;
    @endphp
    <div class="space-y-6">
        <a href="{{ route('admin.users.show', $user) }}" class="inline-block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← {{ $user->name }}</a>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-xl font-semibold text-gray-800 dark:text-white/90">Sessions</h1>
            <form method="GET" action="{{ route('admin.users.sessions.index', $user) }}">
                <select name="status" class="{{ $select }}" onchange="this.form.submit()" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach ($options as $value => $label)
                        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <section class="{{ $card }}">
            @if ($sessions->isEmpty())
                <p class="text-sm text-gray-400">No sessions.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-theme-xs text-gray-500 dark:text-gray-400">
                            <tr><th class="py-1.5 pr-3 font-medium">Date</th><th class="pr-3 font-medium">Workout</th><th class="pr-3 font-medium">Duration</th><th class="font-medium">Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="whitespace-nowrap py-2 pr-3 text-gray-600 dark:text-gray-400">{{ $session->performed_at?->format('j M Y H:i') ?? '—' }}</td>
                                    <td class="pr-3">
                                        <a href="{{ route('admin.users.sessions.show', [$user, $session]) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $session->workoutTemplate?->name ?? 'Session #'.$session->id }}</a>
                                    </td>
                                    <td class="whitespace-nowrap pr-3 text-gray-600 dark:text-gray-400">{{ $session->performed_at && $session->completed_at ? (int) $session->performed_at->diffInMinutes($session->completed_at).' min' : '—' }}</td>
                                    <td class="whitespace-nowrap">
                                        @if ($session->isStuck())
                                            <span class="inline-block rounded-full bg-error-50 px-2 py-0.5 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400" title="Active since {{ $session->performed_at->format('j M Y H:i') }}">Stuck</span>
                                        @else
                                            <span class="inline-block rounded-full px-2 py-0.5 text-theme-xs font-medium {{ $session->status_badge_classes }}">{{ $session->status_label }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $sessions->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
