@extends('layouts.app')

@section('title', $user->name)

@section('content')
    @php
        $label = fn (?\BackedEnum $value) => $value ? \Illuminate\Support\Str::of($value->value)->replace('_', ' ')->ucfirst() : '—';
        $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
        $heading = 'font-display text-base font-semibold text-gray-800 dark:text-white/90';
        $empty = 'mt-3 text-sm text-gray-400';
    @endphp
    <div class="space-y-6">
        <a href="{{ $back }}" class="inline-block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Users</a>

        <div>
            <h1 class="font-display text-xl font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
        </div>

        {{-- The four-fact strip: the main question, answered before any scrolling. --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="text-theme-xs text-gray-500 dark:text-gray-400">Activity Status</div>
                <x-admin.activity-chip :status="$status" class="mt-1.5" />
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="text-theme-xs text-gray-500 dark:text-gray-400">Access Source</div>
                <x-admin.access-chip :access="$access" class="mt-1.5" />
                <div class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">{{ $access->detail() }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="text-theme-xs text-gray-500 dark:text-gray-400">Partner</div>
                @if ($user->partner)
                    <div class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{{ $user->partner->name }}</div>
                    <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $user->partner->kind()->label() }}</div>
                @else
                    <div class="mt-1 text-sm text-gray-400">No partner</div>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="text-theme-xs text-gray-500 dark:text-gray-400">Active plan</div>
                @if ($plan)
                    <div class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{{ $plan->plan->name }}</div>
                    <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $plan->detail() }}</div>
                @else
                    <div class="mt-1 text-sm text-gray-400">No active plan</div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Profile</h2>
                @if ($profile = $user->profile)
                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Goal</dt><dd class="text-gray-800 dark:text-white/90">{{ $label($profile->fitness_goal) }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Experience</dt><dd class="text-gray-800 dark:text-white/90">{{ $label($profile->training_experience) }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="text-gray-800 dark:text-white/90">{{ $label($profile->gender) }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Age</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->age ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Height</dt><dd class="text-gray-800 dark:text-white/90">{{ $height ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Weight</dt><dd class="text-gray-800 dark:text-white/90">{{ $weight ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Training days</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->training_days_per_week ? $profile->training_days_per_week.' per week' : '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Workout duration</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->workout_duration_minutes ? $profile->workout_duration_minutes.' min' : '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Unit System</dt><dd class="text-gray-800 dark:text-white/90">{{ $label($user->unitSystem()) }}</dd>
                    </dl>
                @else
                    <p class="{{ $empty }}">No profile yet.</p>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">Signed up</dt><dd class="text-gray-800 dark:text-white/90">{{ $user->created_at->format('j M Y') }}</dd>
                    <dt class="text-gray-500 dark:text-gray-400">Sign-in</dt><dd class="text-gray-800 dark:text-white/90">{{ $user->social_provider ? ucfirst($user->social_provider) : 'Password' }}</dd>
                    <dt class="text-gray-500 dark:text-gray-400">Verified</dt><dd class="text-gray-800 dark:text-white/90">{{ $user->email_verified_at?->format('j M Y') ?? 'No' }}</dd>
                    <dt class="text-gray-500 dark:text-gray-400">Onboarded</dt><dd class="text-gray-800 dark:text-white/90">{{ $user->onboarding_completed_at?->format('j M Y') ?? 'No' }}</dd>
                </dl>
            </section>

            <section class="{{ $card }} lg:col-span-2">
                <h2 class="{{ $heading }}">Recent sessions</h2>
                @if ($sessions->isEmpty())
                    <p class="{{ $empty }}">No sessions yet.</p>
                @else
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-theme-xs text-gray-500 dark:text-gray-400">
                                <tr><th class="py-1.5 pr-3 font-medium">Date</th><th class="pr-3 font-medium">Workout</th><th class="pr-3 font-medium">Duration</th><th class="font-medium">Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($sessions as $session)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="whitespace-nowrap py-2 pr-3 text-gray-600 dark:text-gray-400">{{ $session->performed_at?->format('j M Y') ?? '—' }}</td>
                                        <td class="pr-3 text-gray-800 dark:text-white/90">{{ $session->workoutTemplate?->name ?? '—' }}</td>
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
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Personal Records</h2>
                <p class="text-theme-xs text-gray-500 dark:text-gray-400">Best set ever per exercise</p>
                @if ($bests->isEmpty())
                    <p class="{{ $empty }}">None yet.</p>
                @else
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($bests as $best)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="text-gray-800 dark:text-white/90">{{ $best['exercise'] }}</span>
                                <span class="shrink-0 text-right text-gray-500 dark:text-gray-400">{{ $best['weight'] }} × {{ $best['reps'] }} <span class="block text-theme-xs">{{ \Illuminate\Support\Carbon::parse($best['performed_at'])->format('j M Y') }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Devices</h2>
                @forelse ($devices as $device)
                    <div class="mt-3 text-sm">
                        <div class="font-medium text-gray-800 dark:text-white/90">{{ \App\Http\Controllers\Admin\UserController::PLATFORMS[$device->platform] ?? $device->platform }} · {{ $device->device_name ?? 'Unknown device' }}</div>
                        <div class="text-theme-xs text-gray-500 dark:text-gray-400">
                            App {{ $device->app_version ?? '?' }} · {{ $device->timezone ?? 'no timezone' }} · seen {{ $device->last_seen_at?->format('j M Y') ?? 'never' }} · push {{ $user->push_enabled ? 'on' : 'off' }}
                        </div>
                    </div>
                @empty
                    <p class="{{ $empty }}">No Device registered.</p>
                @endforelse
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Sent Records</h2>
                @if ($sent->isEmpty())
                    <p class="{{ $empty }}">Nothing sent.</p>
                @else
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($sent as $record)
                            <li class="flex items-baseline justify-between gap-3">
                                <span>
                                    <span class="text-gray-800 dark:text-white/90">{{ \Illuminate\Support\Str::headline(class_basename($record->type)) }}</span>
                                    @if (! empty($record->data['title']))
                                        <span class="block text-theme-xs text-gray-500 dark:text-gray-400">{{ $record->data['title'] }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400">{{ $record->created_at->format('j M Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Invitation</h2>
                @if ($invitation)
                    <p class="mt-3 text-sm text-gray-800 dark:text-white/90">
                        Invited by {{ $invitation->inviter?->name ?? 'a deleted account' }} ({{ $invitation->partner?->name ?? '—' }}) on {{ $invitation->created_at->format('j M Y') }}
                    </p>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                        {{ $invitation->accepted_at ? 'accepted '.$invitation->accepted_at->format('j M Y') : ($invitation->isExpired() ? 'expired, not accepted' : 'not accepted yet') }}
                    </p>
                @else
                    <p class="{{ $empty }}">Joined without an invitation.</p>
                @endif
            </section>
        </div>
    </div>
@endsection
