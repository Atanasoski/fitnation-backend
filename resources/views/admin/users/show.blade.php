@extends('layouts.app')

@section('title', $user->name)

@section('content')
    @php
        $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
        $heading = 'font-display text-base font-semibold text-gray-800 dark:text-white/90';
        $empty = 'mt-3 text-sm text-gray-400';
    @endphp
    <div class="space-y-6">
        <a href="{{ $back }}" class="inline-block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Users</a>

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
                    <a href="{{ \App\Services\Plan\PlanOutline::url($plan->plan) }}" class="mt-1 block text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $plan->plan->name }}</a>
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
                        <dt class="text-gray-500 dark:text-gray-400">Goal</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->fitness_goal?->label() ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Experience</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->training_experience?->label() ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->gender?->label() ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Age</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->age ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Height</dt><dd class="text-gray-800 dark:text-white/90">{{ $height ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Weight</dt><dd class="text-gray-800 dark:text-white/90">{{ $weight ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Training days</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->training_days_per_week ? $profile->training_days_per_week.' per week' : '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Workout duration</dt><dd class="text-gray-800 dark:text-white/90">{{ $profile->workout_duration_minutes ? $profile->workout_duration_minutes.' min' : '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Unit System</dt><dd class="text-gray-800 dark:text-white/90">{{ $user->unitSystem()->label() }}</dd>
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

            <section class="{{ $card }} lg:col-span-2">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="{{ $heading }}">Plans</h2>
                    <a href="{{ route('plans.index', $user) }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Open plan outline →</a>
                </div>
                @if ($plans->isEmpty())
                    <p class="{{ $empty }}">No plans yet.</p>
                @else
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-theme-xs text-gray-500 dark:text-gray-400">
                                <tr><th class="py-1.5 pr-3 font-medium">Name</th><th class="pr-3 font-medium">Type</th><th class="pr-3 font-medium">Status</th><th class="pr-3 font-medium">Workouts</th><th class="font-medium">Updated</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($plans as $userPlan)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2 pr-3">
                                            <a href="{{ \App\Services\Plan\PlanOutline::url($userPlan) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $userPlan->name }}</a>
                                        </td>
                                        <td class="whitespace-nowrap pr-3 text-gray-600 dark:text-gray-400">{{ $userPlan->type?->label() ?? '—' }}</td>
                                        <td class="whitespace-nowrap pr-3">
                                            @if ($userPlan->is_active)
                                                <span class="inline-block rounded-full bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400">Active</span>
                                            @else
                                                <span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap pr-3 text-gray-600 dark:text-gray-400">{{ $userPlan->workout_templates_count }}</td>
                                        <td class="whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $userPlan->updated_at?->format('j M Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Best sets</h2>
                <p class="text-theme-xs text-gray-500 dark:text-gray-400">Best set ever per exercise, by estimated 1RM</p>
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
            <section class="{{ $card }} lg:col-span-2">
                <h2 class="{{ $heading }}">Grants &amp; partner changes</h2>
                @forelse ($history as $change)
                    <div class="mt-3 text-sm">
                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $change->kind->label() }}</span>
                        <span class="text-gray-500 dark:text-gray-400">
                            · by {{ $change->admin?->name ?? 'a deleted account' }} · {{ $change->created_at->format('j M Y') }}
                            @if ($change->kind === \App\Enums\AdminChangeKind::ComplimentaryAccess)
                                · {{ $change->until ? 'until '.$change->until->format('j M Y') : 'ended' }}
                            @else
                                · {{ $change->fromPartner?->name ?? 'no partner' }} → {{ $change->toPartner?->name ?? 'a deleted partner' }}
                            @endif
                        </span>
                        @if ($change->reason)
                            <div class="text-theme-xs text-gray-500 dark:text-gray-400">Reason: {{ $change->reason }}</div>
                        @endif
                    </div>
                @empty
                    <p class="{{ $empty }}">None.</p>
                @endforelse
            </section>

            @include('admin.users._actions')
        </div>
    </div>
@endsection
