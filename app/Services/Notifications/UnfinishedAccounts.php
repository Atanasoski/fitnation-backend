<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\UnfinishedAccountNudge;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The Unfinished Account rule, as a read: who is due a nudge at this instant.
 *
 * An Unfinished Account (CONTEXT.md) is a user who has not finished onboarding.
 * They are due when, in their own time, it is the nudge hour and a whole number
 * of local days since they registered reaches a step of the ladder (1, 3, 7)
 * that has not been sent yet. Finishing onboarding ends it; so does deleting
 * the account (the default scope hides soft-deleted users). Nothing resets the
 * count — it is measured from users.created_at, once.
 *
 * "Their own time" is the timezone of their most recently seen Device, but most
 * of these users have none — Device permission is only asked after onboarding
 * — and so are read in the home timezone. A user whose latest Device is in a
 * timezone that is not at the hour is not due, even when the home timezone is.
 *
 * The Push Switch is not consulted: this is mail about an account the user
 * created, and they have never seen the switch.
 *
 * The step and the sent record are keyed exactly as the Inactivity Nudge does
 * it; what the user is stuck at is read at evaluation time, so a user who
 * verifies between two steps gets the "finish setting up" content next.
 *
 * Query count is constant in the number of users: Device timezones, latest
 * Device per user, the users, and their Sent Record — four at most.
 */
final class UnfinishedAccounts
{
    /**
     * @return Collection<int, UnfinishedAccountCandidate>
     */
    public static function dueAt(CarbonImmutable $now): Collection
    {
        $hour = LocalHour::fromConfig('notifications.unfinished_account.local_hour');
        $homeIsAtTheHour = $hour->isNow($now, LocalHour::home());

        $deviceTimezones = LocalHour::timezoneOfLatestDevice($hour->deviceTimezonesNow($now));

        if (! $homeIsAtTheHour && $deviceTimezones->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereNull('onboarding_completed_at')
            ->where(function ($query) use ($deviceTimezones, $homeIsAtTheHour) {
                $query->whereKey($deviceTimezones->keys()->all());

                if ($homeIsAtTheHour) {
                    $query->orWhereDoesntHave('devices');
                }
            })
            ->get();

        if ($users->isEmpty()) {
            return collect();
        }

        $since = $users->mapWithKeys(fn (User $user) => [$user->id => CarbonImmutable::instance($user->created_at)]);

        $sent = SentRecord::since(UnfinishedAccountNudge::class, $users->modelKeys(), $since->min());

        $ladder = Ladder::fromConfig('notifications.unfinished_account.ladder');

        return $users
            ->map(function (User $user) use ($now, $deviceTimezones, $since, $sent, $ladder) {
                $timezone = $deviceTimezones[$user->id] ?? LocalHour::home();

                $step = $ladder->stepReached(LocalHour::wholeDaysBetween($since[$user->id], $now, $timezone));

                if ($step === null) {
                    return null;
                }

                $alreadySent = $sent->for($user->id)->contains(
                    fn (DatabaseNotification $row) => ($row->data['step'] ?? null) === $step
                );

                return $alreadySent
                    ? null
                    : new UnfinishedAccountCandidate($user, $step, UnfinishedAccountCandidate::stuckAt($user));
            })
            ->filter()
            ->values();
    }
}
