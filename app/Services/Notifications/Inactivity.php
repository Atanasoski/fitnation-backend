<?php

namespace App\Services\Notifications;

use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Notifications\InactivityNudge;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The Inactivity Nudge rule, as a read: who is due a nudge at this instant.
 *
 * A user is due when, in the timezone of their most recently seen Device, it is
 * the nudge hour; they have gone a whole number of days without a Completed
 * Session — counted in local calendar days from their last one, or from the day
 * they finished onboarding if they have none — that reaches a step of the
 * ladder (3, 7, 14); and that step has not already been sent since they last
 * trained. Training resets the ladder: a Completed Session makes every earlier
 * nudge irrelevant, and the count starts again from it.
 *
 * Not due, whatever the count: onboarding unfinished, a session in progress,
 * the Push Switch off, or no Device at all — the last because there is nothing
 * to send to and no timezone to send it in.
 *
 * "The nudge hour" is the whole of it, not one quarter of it. The command runs
 * every fifteen minutes so that half-hour offsets get a turn; a user is due at
 * each of those runs during their 18:00 hour, and the sent record makes every
 * run after the first a no-op. That is what lets a late or skipped tick cost
 * nothing.
 *
 * Six queries, whatever the number of users, and the rows they touch grow
 * with the users whose clock says it is the hour, not with the user base: the
 * timezones in use are read first (LocalHour), and everything after is scoped to
 * the timezones that are at the hour and the users whose latest Device is in one.
 * Whether a step was already sent is the Sent Record's answer, bounded below
 * by the earliest date any user in the batch is being measured from.
 */
final class Inactivity
{
    /**
     * @return Collection<int, InactivityNudgeCandidate>
     */
    public static function dueAt(CarbonImmutable $now): Collection
    {
        $hour = LocalHour::fromConfig('notifications.inactivity.local_hour');
        $timezones = LocalHour::timezoneOfLatestDevice($hour->deviceTimezonesNow($now));

        if ($timezones->isEmpty()) {
            return collect();
        }

        $ids = $timezones->keys()->all();

        $users = User::query()
            ->whereKey($ids)
            ->where('push_enabled', true)
            ->whereNotNull('onboarding_completed_at')
            ->get();

        if ($users->isEmpty()) {
            return collect();
        }

        $lastCompleted = WorkoutSession::query()
            ->whereIn('user_id', $ids)
            ->completed()
            ->selectRaw('user_id, MAX(completed_at) as last_completed_at')
            ->groupBy('user_id')
            ->pluck('last_completed_at', 'user_id');

        $inProgress = WorkoutSession::query()
            ->whereIn('user_id', $ids)
            ->whereIn('status', [WorkoutSessionStatus::Draft, WorkoutSessionStatus::Active])
            ->distinct()
            ->pluck('user_id')
            ->flip();

        $since = $users->mapWithKeys(fn (User $user) => [
            $user->id => self::measuredFrom($user, $lastCompleted[$user->id] ?? null),
        ]);

        $sent = SentRecord::since(InactivityNudge::class, $ids, $since->min());

        $ladder = Ladder::fromConfig('notifications.inactivity.ladder');

        return $users
            ->reject(fn (User $user) => $inProgress->has($user->id))
            ->map(function (User $user) use ($now, $timezones, $since, $sent, $ladder) {
                $step = $ladder->stepReached(
                    LocalHour::wholeDaysBetween($since[$user->id], $now, $timezones[$user->id])
                );

                if ($step === null) {
                    return null;
                }

                $alreadySent = $sent->for($user->id)->contains(
                    fn (DatabaseNotification $row) => ($row->data['step'] ?? null) === $step
                        && $row->created_at->greaterThan($since[$user->id])
                );

                return $alreadySent ? null : new InactivityNudgeCandidate($user, $step);
            })
            ->filter()
            ->values();
    }

    /**
     * When the user's inactivity is counted from: their last Completed Session,
     * or finishing onboarding if they have none. Aggregated timestamps come back
     * as strings in the app timezone, which is what Eloquent wrote.
     */
    private static function measuredFrom(User $user, ?string $lastCompletedAt): CarbonImmutable
    {
        return $lastCompletedAt !== null
            ? CarbonImmutable::parse($lastCompletedAt, config('app.timezone'))
            : CarbonImmutable::instance($user->onboarding_completed_at);
    }
}
