<?php

namespace App\Services\Notifications;

use App\Models\Device;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Illuminate\Support\Collection;

/**
 * "At HH:00 in the user's own time" — the clock every scheduled rule
 * (Inactivity Nudge, Unfinished Account nudge) reads before deciding who is due.
 *
 * A user's time is that of their most recently seen Device; a Device that never
 * reported a timezone, or a user with no Device at all, is read in the home
 * timezone (config notifications.default_timezone). "The hour" is the whole of
 * it: a rule that runs every quarter hour finds the same user due at each run
 * during that hour and relies on its sent record to send only once.
 *
 * The Device reads are shaped so a rule's query count stays constant: the
 * timezones in use are read first, and only users whose latest Device is in a
 * timezone that is at the hour are looked up.
 */
final class LocalHour
{
    public function __construct(public readonly int $hour) {}

    public static function fromConfig(string $key): self
    {
        return new self((int) config($key));
    }

    /**
     * Whether it is now this hour in the given timezone.
     */
    public function isNow(CarbonImmutable $now, CarbonTimeZone $timezone): bool
    {
        return $now->setTimezone($timezone)->hour === $this->hour;
    }

    /**
     * The timezones any Device is in — a Device that never reported one counts
     * as the home timezone — narrowed to those where it is now this hour.
     *
     * @return Collection<int, ?string> IANA names; null stands for the home timezone
     */
    public function deviceTimezonesNow(CarbonImmutable $now): Collection
    {
        return Device::query()
            ->distinct()
            ->pluck('timezone')
            ->filter(fn (?string $name) => $this->isNow($now, self::timezone($name)));
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
    public static function timezoneOfLatestDevice(Collection $timezones): Collection
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
     * Whole local calendar days from one instant to another, both read in the
     * given timezone — "days since" as a person would count them.
     */
    public static function wholeDaysBetween(CarbonImmutable $since, CarbonImmutable $now, CarbonTimeZone $timezone): int
    {
        return (int) $since->setTimezone($timezone)->startOfDay()
            ->diffInDays($now->setTimezone($timezone)->startOfDay());
    }

    /**
     * A named timezone, or the home timezone when there is none.
     */
    public static function timezone(?string $name): CarbonTimeZone
    {
        return CarbonTimeZone::create($name ?? config('notifications.default_timezone'));
    }

    public static function home(): CarbonTimeZone
    {
        return self::timezone(null);
    }
}
