@extends('layouts.app')

@section('title', ($session->workoutTemplate?->name ?? 'Session').' · '.$user->name)

@section('content')
    @php
        $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
        $heading = 'font-display text-base font-semibold text-gray-800 dark:text-white/90';
    @endphp
    <div class="space-y-6">
        <a href="{{ route('admin.users.sessions.index', $user) }}" class="inline-block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Sessions</a>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-900/20">
                <p class="text-sm text-success-800 dark:text-success-200">{{ session('success') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
                @foreach ($errors->all() as $error)
                    <p class="text-sm text-error-700 dark:text-error-300">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-xl font-semibold text-gray-800 dark:text-white/90">{{ $session->workoutTemplate?->name ?? 'Session #'.$session->id }}</h1>
            @if ($session->isStuck())
                <span class="inline-block rounded-full bg-error-50 px-2 py-0.5 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400" title="Active since {{ $session->performed_at->format('j M Y H:i') }}">Stuck</span>
            @else
                <span class="inline-block rounded-full px-2 py-0.5 text-theme-xs font-medium {{ $session->status_badge_classes }}">{{ $session->status_label }}</span>
            @endif
            @if ($session->is_auto_generated)
                <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">Auto-generated</span>
            @endif
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $user->name }}</a>
        </p>

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Session</h2>
            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm sm:grid-cols-4">
                <dt class="text-gray-500 dark:text-gray-400">Started</dt><dd class="text-gray-800 dark:text-white/90">{{ $session->performed_at?->format('j M Y H:i') ?? '—' }}</dd>
                <dt class="text-gray-500 dark:text-gray-400">Completed</dt><dd class="text-gray-800 dark:text-white/90">{{ $session->completed_at?->format('j M Y H:i') ?? '—' }}</dd>
                <dt class="text-gray-500 dark:text-gray-400">Duration</dt><dd class="text-gray-800 dark:text-white/90">{{ $session->performed_at && $session->completed_at ? (int) $session->performed_at->diffInMinutes($session->completed_at).' min' : '—' }}</dd>
                <dt class="text-gray-500 dark:text-gray-400">Logged sets</dt><dd class="text-gray-800 dark:text-white/90">{{ $exercises->sum(fn ($exercise) => $exercise['sets']->count()) }}</dd>
            </dl>
            @if ($session->notes)
                <p class="mt-3 border-t border-gray-100 pt-3 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-400">{{ $session->notes }}</p>
            @endif
            @if ($session->replacedSession)
                <p class="mt-3 border-t border-gray-100 pt-3 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Replaces <a href="{{ route('admin.users.sessions.show', [$user, $session->replacedSession]) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $session->replacedSession->workoutTemplate?->name ?? 'Session #'.$session->replacedSession->id }}</a>
                </p>
            @endif
            @if ($session->replacedBy)
                <p class="mt-3 border-t border-gray-100 pt-3 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    Replaced by <a href="{{ route('admin.users.sessions.show', [$user, $session->replacedBy]) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $session->replacedBy->workoutTemplate?->name ?? 'Session #'.$session->replacedBy->id }}</a>
                </p>
            @endif
        </section>

        @if ($session->isStuck())
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Actions</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This session has been active for over {{ \App\Models\WorkoutSession::STUCK_AFTER_HOURS }} hours — the app most likely lost track of it. Cancelling keeps every logged set.</p>
                <form method="POST" action="{{ route('admin.users.sessions.cancel', [$user, $session]) }}" class="mt-3 flex flex-wrap items-center gap-2"
                    onsubmit="return confirm('Cancel this stuck session? Logged sets are kept.')">
                    @csrf
                    <input type="text" name="reason" maxlength="1000" placeholder="Reason (optional)" class="w-64 rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" aria-label="Reason for cancelling">
                    <button type="submit" class="rounded-lg border border-error-300 px-3 py-1.5 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-700 dark:text-error-400 dark:hover:bg-error-500/10">Cancel this session</button>
                </form>
            </section>
        @endif

        <section class="{{ $card }}">
            <h2 class="{{ $heading }}">Exercises</h2>
            @if ($exercises->isEmpty())
                <p class="mt-3 text-sm text-gray-400">No exercises on this session.</p>
            @else
                <div class="mt-3 space-y-5">
                    @foreach ($exercises as $exercise)
                        <div class="border-t border-gray-100 pt-4 first:border-t-0 first:pt-0 dark:border-gray-800">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $exercise['name'] }}</span>
                                <span class="text-theme-xs text-gray-500 dark:text-gray-400">
                                    {{ $exercise['target_sets'] }} sets × {{ $exercise['min_target_reps'] }}–{{ $exercise['max_target_reps'] }}
                                    @if ($exercise['target_weight'])
                                        · {{ $exercise['target_weight'] }}
                                    @endif
                                    @if ($exercise['rest_seconds'])
                                        · {{ $exercise['rest_seconds'] }}s rest
                                    @endif
                                </span>
                            </div>
                            @if ($exercise['sets']->isEmpty())
                                <p class="mt-1 text-theme-xs text-gray-400">No sets logged.</p>
                            @else
                                <table class="mt-2 text-sm">
                                    <thead class="text-left text-theme-xs text-gray-500 dark:text-gray-400">
                                        <tr><th class="py-1 pr-6 font-medium">Set</th><th class="pr-6 font-medium">Weight</th><th class="font-medium">Reps</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($exercise['sets'] as $set)
                                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                                <td class="py-1.5 pr-6 text-gray-600 dark:text-gray-400">{{ $set['set_number'] }}</td>
                                                <td class="pr-6 text-gray-800 dark:text-white/90">{{ $set['weight'] ?? '—' }}</td>
                                                <td class="text-gray-800 dark:text-white/90">{{ $set['reps'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
