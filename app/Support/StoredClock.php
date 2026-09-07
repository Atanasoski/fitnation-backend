<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonTimeZone;

/**
 * The clock timestamp columns are written in — the app timezone — and the
 * one fact that follows from it: Eloquent binds a Carbon as its own clock,
 * without converting. A Monday 00:00 in New York bound as written would be
 * read back as a Monday 00:00 in Skopje, six hours early, and nothing would
 * fail loudly.
 *
 * So every local instant is re-read here before it meets a column, and every
 * stored value is re-read here before it is placed in a local day or week.
 * Test fixtures cross the same seam, so a fixture written in a user's clock
 * lands in the table as the app would have written it.
 */
final class StoredClock
{
    public static function timezone(): CarbonTimeZone
    {
        return CarbonTimeZone::create(config('app.timezone'));
    }

    /**
     * An instant, written the way the column is: the value to bind.
     */
    public static function bind(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::timezone());
    }

    /**
     * Both ends of a window, bound — the argument to whereBetween.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function between(CarbonInterface $from, CarbonInterface $to): array
    {
        return [self::bind($from), self::bind($to)];
    }

    /**
     * A stored value read on another clock — the user's — so that which day
     * and which week it falls in is theirs, not the server's.
     */
    public static function read(CarbonInterface $stored, CarbonTimeZone|CarbonInterface $inZoneOf): CarbonImmutable
    {
        $timezone = $inZoneOf instanceof CarbonInterface ? $inZoneOf->getTimezone() : $inZoneOf;

        return CarbonImmutable::instance($stored)->setTimezone($timezone);
    }
}
