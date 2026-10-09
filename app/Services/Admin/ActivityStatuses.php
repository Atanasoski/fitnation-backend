<?php

namespace App\Services\Admin;

use App\Enums\ActivityStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Activity Status rule (CONTEXT.md), with two faces that must agree: the
 * status of given users, and a query constraint "users whose status is X" that
 * runs in SQL so the Users list can filter and the Overview can count without
 * loading every user. tests/Feature/Admin/ActivityStatusTest.php holds them to
 * each other.
 *
 * In order, the first that holds:
 * - Deleted — the account is soft-deleted.
 * - Unfinished — email unverified, or verified and onboarding not complete.
 * - Active — last Completed Session under 7 days ago.
 * - Slipping — last Completed Session 7 to 14 days ago.
 * - Inactive — last Completed Session over 14 days ago.
 * - New — no Completed Session, onboarded no more than 14 days ago.
 * - Inactive — no Completed Session, onboarded over 14 days ago.
 *
 * "Days" are whole 24-hour days elapsed on the server clock against now(): 6
 * days 23 hours is still Active, 14 days 23 hours still Slipping. No per-user
 * local time (v1). "Last Completed Session" is the latest completed_at of a
 * session whose status is completed — the column the Inactivity Nudge reads.
 *
 * Loads what it needs itself; callers never pre-aggregate the last Completed
 * Session for it.
 */
final class ActivityStatuses
{
    public const ACTIVE_DAYS = 7;

    public const SLIPPING_DAYS = 14;

    public const NEW_DAYS = 14;

    public static function for(User $user): ActivityStatus
    {
        return self::forUsers(collect([$user]))[$user->id];
    }

    /**
     * One query for the last Completed Session of every user given.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, ActivityStatus> keyed by user id
     */
    public static function forUsers(Collection $users): Collection
    {
        $lastCompleted = $users->isEmpty() ? collect() : WorkoutSession::query()
            ->whereIn('user_id', $users->map(fn (User $user) => $user->getKey())->all())
            ->completed()
            ->whereNotNull('completed_at')
            ->selectRaw('user_id, MAX(completed_at) as last_completed_at')
            ->groupBy('user_id')
            ->pluck('last_completed_at', 'user_id');

        $now = CarbonImmutable::now();

        return $users->mapWithKeys(fn (User $user) => [
            $user->id => self::resolve($user, $lastCompleted[$user->id] ?? null, $now),
        ]);
    }

    /**
     * Narrow a users query to those whose status is $status. Deleted lifts the
     * soft-delete scope; every other status excludes deleted accounts.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function constrain(Builder $query, ActivityStatus $status): Builder
    {
        $now = CarbonImmutable::now();
        $activeSince = $now->subDays(self::ACTIVE_DAYS);
        $slippingSince = $now->subDays(self::SLIPPING_DAYS + 1);
        $newSince = $now->subDays(self::NEW_DAYS + 1);

        if ($status === ActivityStatus::Deleted) {
            return $query->withTrashed()->whereNotNull('users.deleted_at');
        }

        $query->whereNull('users.deleted_at');

        if ($status === ActivityStatus::Unfinished) {
            return $query->where(fn (Builder $q) => $q
                ->whereNull('users.email_verified_at')
                ->orWhereNull('users.onboarding_completed_at'));
        }

        $query->whereNotNull('users.email_verified_at')->whereNotNull('users.onboarding_completed_at');

        $completedSince = fn (?CarbonImmutable $since) => function (Builder $sessions) use ($since) {
            $sessions->completed()->whereNotNull('completed_at');
            if ($since !== null) {
                $sessions->where('completed_at', '>', $since);
            }
        };

        return match ($status) {
            ActivityStatus::Active => $query->whereHas('workoutSessions', $completedSince($activeSince)),
            ActivityStatus::Slipping => $query
                ->whereHas('workoutSessions', $completedSince($slippingSince))
                ->whereDoesntHave('workoutSessions', $completedSince($activeSince)),
            ActivityStatus::Inactive => $query
                ->whereDoesntHave('workoutSessions', $completedSince($slippingSince))
                ->where(fn (Builder $q) => $q
                    ->whereHas('workoutSessions', $completedSince(null))
                    ->orWhere('users.onboarding_completed_at', '<=', $newSince)),
            ActivityStatus::New => $query
                ->whereDoesntHave('workoutSessions', $completedSince(null))
                ->where('users.onboarding_completed_at', '>', $newSince),
        };
    }

    /**
     * Aggregated timestamps come back as strings in the app timezone, which
     * is what Eloquent wrote.
     */
    private static function resolve(User $user, ?string $lastCompletedAt, CarbonImmutable $now): ActivityStatus
    {
        if ($user->deleted_at !== null) {
            return ActivityStatus::Deleted;
        }

        if ($user->email_verified_at === null || $user->onboarding_completed_at === null) {
            return ActivityStatus::Unfinished;
        }

        if ($lastCompletedAt === null) {
            return self::wholeDaysSince(CarbonImmutable::instance($user->onboarding_completed_at), $now) <= self::NEW_DAYS
                ? ActivityStatus::New
                : ActivityStatus::Inactive;
        }

        $days = self::wholeDaysSince(CarbonImmutable::parse($lastCompletedAt, config('app.timezone')), $now);

        return match (true) {
            $days < self::ACTIVE_DAYS => ActivityStatus::Active,
            $days <= self::SLIPPING_DAYS => ActivityStatus::Slipping,
            default => ActivityStatus::Inactive,
        };
    }

    private static function wholeDaysSince(CarbonImmutable $from, CarbonImmutable $now): int
    {
        return (int) floor($from->diffInSeconds($now, false) / 86400);
    }
}
