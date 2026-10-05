<?php

namespace App\Services\Admin;

use App\Enums\AccessSource;
use App\Enums\ActivityStatus;
use App\Enums\PartnerPlan;
use App\Models\Partner;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\System\FailedJobs;
use App\Webhooks\RevenueCat\FailedWebhookCalls;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The super-admin Overview: "is everything OK?" in one structure.
 *
 * - kpis — users, signups, active users and Completed Sessions, this week so
 *   far against the same point last week. Weeks run Monday–Sunday, as in
 *   Weekly Progress; comparing a whole last week with a part-finished one
 *   would make every number fall on Monday, so the comparison stops at the
 *   same weekday and time.
 * - funnel — users who signed up in the last FUNNEL_DAYS days: signed up →
 *   verified → onboarded → first Completed Session → trained in week two
 *   (a Completed Session 7 to 13 days after signup).
 * - paywall — whether subscriptions are enforced, and how many users have
 *   Access Source None: who the paywall stops.
 * - attention — failed jobs, failed RevenueCat webhooks, Unfinished Accounts,
 *   users with a Stuck Session, and Sponsoring Partners whose sponsorship
 *   runs out within EXPIRING_SPONSORSHIP_DAYS.
 *
 * People counts go through the Activity Status and Access Source query
 * constraints and the model scopes, never a second definition, so each count
 * matches the Users list its link opens. Every count is of app users (User::appUsers()): staff never count. Live
 * queries, cached for CACHE_SECONDS — no snapshot tables or scheduled jobs.
 */
final class Overview
{
    public const CACHE_KEY = 'admin.overview';

    public const CACHE_SECONDS = 600;

    public const FUNNEL_DAYS = 28;

    public const EXPIRING_SPONSORSHIP_DAYS = 30;

    /**
     * @return array{
     *     kpis: array<'users'|'signups'|'active'|'completed_sessions', array{current: int, previous: int, delta: int}>,
     *     funnel: array{signed_up: int, verified: int, onboarded: int, first_completed_session: int, trained_in_week_two: int},
     *     paywall: array{enforced: bool, none: int},
     *     attention: array{failed_jobs: int, failed_webhooks: int, unfinished_accounts: int, stuck_sessions: int, expiring_sponsorships: int},
     * }
     */
    public static function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::compute());
    }

    private static function compute(): array
    {
        $now = CarbonImmutable::now();
        $thisWeek = [$now->startOfWeek(CarbonImmutable::MONDAY), $now];
        $lastWeek = [$thisWeek[0]->subWeek(), $now->subWeek()];

        return [
            'kpis' => [
                'users' => self::compare(fn (array $span) => User::query()->appUsers()->where('users.created_at', '<=', $span[1])->count(), $thisWeek, $lastWeek),
                'signups' => self::compare(fn (array $span) => User::query()->appUsers()->whereBetween('users.created_at', $span)->count(), $thisWeek, $lastWeek),
                'active' => self::compare(fn (array $span) => User::query()->appUsers()->trainedBetween(...$span)->count(), $thisWeek, $lastWeek),
                'completed_sessions' => self::compare(fn (array $span) => WorkoutSession::query()->where(self::completedBetween($span))->whereHas('user', fn (Builder $users) => $users->appUsers())->count(), $thisWeek, $lastWeek),
            ],
            'funnel' => self::funnel($now->subDays(self::FUNNEL_DAYS)),
            'paywall' => [
                'enforced' => (bool) config('subscriptions.enforced'),
                'none' => AccessSources::constrain(User::query()->appUsers(), AccessSource::None)->count(),
            ],
            'attention' => [
                'failed_jobs' => app(FailedJobs::class)->count(),
                'failed_webhooks' => FailedWebhookCalls::query()->count(),
                'unfinished_accounts' => ActivityStatuses::constrain(User::query()->appUsers(), ActivityStatus::Unfinished)->count(),
                'stuck_sessions' => User::query()->appUsers()->whereHas('workoutSessions', fn (Builder $sessions) => $sessions->stuck())->count(),
                'expiring_sponsorships' => Partner::query()
                    ->where('plan', PartnerPlan::Sponsor)
                    ->where('plan_expires_at', '>', $now)
                    ->where('plan_expires_at', '<=', $now->addDays(self::EXPIRING_SPONSORSHIP_DAYS))
                    ->count(),
            ],
        ];
    }

    /**
     * Each stage counted on its own (not as a subset of the one before), so
     * an unverified user who onboarded and trains still shows as onboarded
     * and trained.
     *
     * @return array{signed_up: int, verified: int, onboarded: int, first_completed_session: int, trained_in_week_two: int}
     */
    private static function funnel(CarbonImmutable $since): array
    {
        $cohort = fn () => User::query()->appUsers()->where('users.created_at', '>=', $since);

        // Week two is days 7–13 after signup; its bounds differ per user, so
        // it is read off the cohort's Completed Sessions rather than in SQL.
        $signups = $cohort()->pluck('users.created_at', 'users.id');
        $trainedInWeekTwo = WorkoutSession::query()
            ->completed()
            ->whereIn('user_id', $signups->keys()->all())
            ->get(['user_id', 'completed_at'])
            ->filter(function (WorkoutSession $session) use ($signups) {
                $signedUpAt = CarbonImmutable::parse($signups[$session->user_id]);

                return $session->completed_at >= $signedUpAt->addDays(7)
                    && $session->completed_at < $signedUpAt->addDays(14);
            })
            ->pluck('user_id')
            ->unique()
            ->count();

        return [
            'signed_up' => $signups->count(),
            'verified' => $cohort()->whereNotNull('users.email_verified_at')->count(),
            'onboarded' => $cohort()->whereNotNull('users.onboarding_completed_at')->count(),
            'first_completed_session' => $cohort()->whereHas('workoutSessions', fn (Builder $sessions) => $sessions->completed())->count(),
            'trained_in_week_two' => $trainedInWeekTwo,
        ];
    }

    /**
     * @param  array{CarbonImmutable, CarbonImmutable}  $span
     */
    private static function completedBetween(array $span): \Closure
    {
        return fn (Builder $sessions) => $sessions->completed()->whereBetween('completed_at', $span);
    }

    /**
     * @param  callable(array{CarbonImmutable, CarbonImmutable}): int  $count
     * @return array{current: int, previous: int, delta: int}
     */
    private static function compare(callable $count, array $current, array $previous): array
    {
        $now = $count($current);
        $before = $count($previous);

        return ['current' => $now, 'previous' => $before, 'delta' => $now - $before];
    }
}
