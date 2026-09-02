<?php

namespace App\Services\Notifications;

use App\Enums\WorkoutSessionStatus;
use App\Models\Device;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Notifications\InactivityNudge;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
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
 * timezones in use are read first, and everything after is scoped to the
 * timezones that are at the hour and the users whose latest Device is in one.
 * Whether a step was already sent is read from the notifications table — the
 * sent record is the fact — bounded below by the earliest date any user in the
 * batch is being measured from, since nothing older can matter to any of them.
 */
final class Inactivity
{
    /**
     * @return Collection<int, InactivityNudgeCandidate>
     */
    public static function dueAt(CarbonImmutable $now): Collection
    {
        $timezones = self::timezoneOfLatestDevice(self::timezonesAtTheHour($now));

        if ($timezones->isEmpty()) {
            return collect();
        }

        $ids = $timezones->keys()->all();

        $users = User::query()
            ->whereKey($ids)
            ->where('push_enabled', true)
            ->whereNotNull('onboarding_completed_at')
            ->get();

        $lastCompleted = WorkoutSession::query()
            ->whereIn('user_id', $ids)
            ->where('status', WorkoutSessionStatus::Completed)
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

        $sent = self::sentSince($ids, $since->min());

        $ladder = collect(config('notifications.inactivity.ladder'))->sortDesc()->values();

        return $users
            ->reject(fn (User $user) => $inProgress->has($user->id))
            ->map(function (User $user) use ($now, $timezones, $since, $sent, $ladder) {
                $step = self::stepReached($since[$user->id], $now, $timezones[$user->id], $ladder);

                if ($step === null) {
                    return null;
                }

                $alreadySent = ($sent[$user->id] ?? collect())->contains(
                    fn (DatabaseNotification $row) => ($row->data['step'] ?? null) === $step
                        && $row->created_at->greaterThan($since[$user->id])
                );

                return $alreadySent ? null : new InactivityNudgeCandidate($user, $step);
            })
            ->filter()
            ->values();
    }

    /**
     * The timezones any Device is in — a Device that never reported one counts
     * as the home timezone — narrowed to those where it is now the nudge hour.
     *
     * @return Collection<int, string> IANA names; null stands for the home timezone
     */
    private static function timezonesAtTheHour(CarbonImmutable $now): Collection
    {
        $hour = (int) config('notifications.inactivity.local_hour');

        return Device::query()
            ->distinct()
            ->pluck('timezone')
            ->filter(fn (?string $name) => $now->setTimezone(self::timezone($name))->hour === $hour);
    }

    /**
     * Each user whose most recently seen Device is in one of these timezones,
     * with that timezone. The ranking happens in the database so that a user
     * with an older Device in a due timezone and a newer one elsewhere is not
     * mistaken for due.
     *
     * @param  Collection<int, ?string>  $timezones
     * @return Collection<int, CarbonTimeZone> user id => timezone
     */
    private static function timezoneOfLatestDevice(Collection $timezones): Collection
    {
        if ($timezones->isEmpty()) {
            return collect();
        }

        $ranked = Device::query()->selectRaw(
            'user_id, timezone, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY last_seen_at DESC, id DESC) AS rank_in_user'
        );

        return Device::query()
            ->fromSub($ranked, 'latest')
            ->where('rank_in_user', 1)
            ->where(function ($query) use ($timezones) {
                $query->whereIn('timezone', $timezones->filter()->values());

                if ($timezones->contains(null)) {
                    $query->orWhereNull('timezone');
                }
            })
            ->get(['user_id', 'timezone'])
            ->mapWithKeys(fn (Device $device) => [$device->user_id => self::timezone($device->timezone)]);
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

    /**
     * The highest step of the ladder the user's inactivity has reached, in
     * whole local calendar days, or null below the first.
     *
     * @param  Collection<int, int>  $ladder  descending
     */
    private static function stepReached(CarbonImmutable $since, CarbonImmutable $now, CarbonTimeZone $timezone, Collection $ladder): ?int
    {
        $days = $since->setTimezone($timezone)->startOfDay()
            ->diffInDays($now->setTimezone($timezone)->startOfDay());

        return $ladder->first(fn (int $step) => $days >= $step);
    }

    /**
     * Every Inactivity Nudge sent to these users since the given instant,
     * grouped by user.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Collection<int, DatabaseNotification>>
     */
    private static function sentSince(array $ids, ?CarbonImmutable $earliest): Collection
    {
        if ($earliest === null) {
            return collect();
        }

        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $ids)
            ->where('type', InactivityNudge::class)
            ->where('created_at', '>', $earliest)
            ->get(['notifiable_id', 'data', 'created_at'])
            ->groupBy('notifiable_id');
    }

    private static function timezone(?string $name): CarbonTimeZone
    {
        return CarbonTimeZone::create($name ?? config('notifications.default_timezone'));
    }
}
