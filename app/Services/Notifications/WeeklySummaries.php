<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\WeeklySummary;
use App\Support\StoredClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The Weekly Summary rule, as a read: who is due the email at this instant.
 *
 * A user is due when, on their own clock, it is Monday in the summary hour;
 * they have at least one Completed Session in the Mon–Sun week that just
 * ended or the one before it — with nothing in either, the Inactivity Nudge
 * owns them; the weekly_summary_email preference is on; and no summary has
 * been recorded since 00:00 that Monday. The Push Switch is not consulted:
 * it governs push, and this is mail.
 *
 * "Their own clock" is the timezone of their most recently seen Device, or the
 * home timezone for a user with none. Both weeks are bounded in that zone, so a
 * session at Sunday 23:30 in New York belongs to the week the New Yorker just
 * finished, whatever the app clock says. Since the bounds differ by zone, users
 * are grouped by zone and each group's window is written into the one query.
 *
 * "The hour" is the whole of it: the command runs every quarter hour and finds
 * the same user due at each run until the sent record says otherwise.
 *
 * Four queries at most, whatever the number of users: Device timezones, latest
 * Device per user, the users with a session in their window, and their sent
 * records. The Weekly Progress numbers are read per candidate, lazily.
 */
final class WeeklySummaries
{
    /**
     * @return Collection<int, WeeklySummaryCandidate>
     */
    public static function dueAt(CarbonImmutable $now): Collection
    {
        $hour = LocalHour::fromConfig('notifications.weekly_summary.local_hour')->on(CarbonInterface::MONDAY);
        $homeIsAtTheHour = $hour->isNow($now, LocalHour::home());

        $deviceTimezones = LocalHour::timezoneOfLatestDevice($hour->deviceTimezonesNow($now));

        if (! $homeIsAtTheHour && $deviceTimezones->isEmpty()) {
            return collect();
        }

        // User ids per timezone name; the home group, keyed by its name too, is
        // everyone with no Device and is written as such rather than as ids.
        $groups = $deviceTimezones
            ->groupBy(fn (CarbonTimeZone $timezone) => $timezone->getName(), preserveKeys: true)
            ->map(fn (Collection $users) => $users->keys()->all());

        $users = User::query()
            ->where(function (Builder $query) use ($groups, $homeIsAtTheHour, $now) {
                foreach ($groups as $name => $ids) {
                    $query->orWhere(fn (Builder $q) => $q->whereKey($ids)
                        ->where(self::trainedInWindow($now->setTimezone($name))));
                }

                if ($homeIsAtTheHour) {
                    $query->orWhere(fn (Builder $q) => $q->whereDoesntHave('devices')
                        ->where(self::trainedInWindow($now->setTimezone(LocalHour::home()))));
                }
            })
            ->where(fn (Builder $query) => $query
                ->whereNull('notification_settings')
                ->orWhereNull('notification_settings->'.WeeklySummary::SETTING)
                ->orWhere('notification_settings->'.WeeklySummary::SETTING, true))
            ->get();

        if ($users->isEmpty()) {
            return collect();
        }

        $asOf = $users->mapWithKeys(fn (User $user) => [
            $user->id => $now->setTimezone($deviceTimezones[$user->id] ?? LocalHour::home()),
        ]);

        $mondayStart = $asOf->map(fn (CarbonImmutable $local) => $local->startOfDay());

        $sent = self::sentSince($users->modelKeys(), $mondayStart->min());

        return $users
            ->reject(fn (User $user) => ($sent[$user->id] ?? collect())->contains(
                fn (DatabaseNotification $row) => $row->created_at->greaterThanOrEqualTo($mondayStart[$user->id])
            ))
            ->map(fn (User $user) => new WeeklySummaryCandidate($user, $asOf[$user->id]))
            ->values();
    }

    /**
     * "Has a Completed Session in the week that just ended or the one before",
     * both weeks bounded on the given local clock.
     */
    private static function trainedInWindow(CarbonImmutable $local): \Closure
    {
        $window = StoredClock::between($local->subWeeks(2)->startOfWeek(), $local->subWeek()->endOfWeek());

        return fn (Builder $query) => $query->whereHas('workoutSessions', fn (Builder $sessions) => $sessions
            ->completed()
            ->whereBetween('performed_at', $window));
    }

    /**
     * Every Weekly Summary recorded for these users since the given instant —
     * the earliest local Monday midnight in the batch — grouped by user.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Collection<int, DatabaseNotification>>
     */
    private static function sentSince(array $ids, CarbonImmutable $earliest): Collection
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $ids)
            ->where('type', WeeklySummary::class)
            ->where('created_at', '>=', StoredClock::bind($earliest))
            ->get(['notifiable_id', 'created_at'])
            ->groupBy('notifiable_id');
    }
}
