<?php

namespace Tests\Unit\Support;

use App\Support\StoredClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Tests\TestCase;

/**
 * The one fact StoredClock knows: timestamp columns are written in the app
 * timezone (Europe/Skopje here), and Eloquent binds a Carbon as its own clock
 * without converting. So a local instant must be re-read on the stored clock
 * before it meets a column, and a stored value re-read on the local clock
 * before it is placed in a day or a week.
 */
class StoredClockTest extends TestCase
{
    public function test_bind_re_reads_an_instant_on_the_stored_clock(): void
    {
        $newYorkMidnight = CarbonImmutable::parse('2026-08-31 00:00:00', 'America/New_York');

        $bound = StoredClock::bind($newYorkMidnight);

        $this->assertSame('2026-08-31 06:00:00', $bound->format('Y-m-d H:i:s'));
        $this->assertSame(config('app.timezone'), $bound->getTimezone()->getName());
        $this->assertTrue($bound->equalTo($newYorkMidnight), 'the same instant, differently written');
    }

    public function test_an_instant_already_on_the_stored_clock_is_unchanged(): void
    {
        $skopje = CarbonImmutable::parse('2026-09-07 08:00:00', config('app.timezone'));

        $this->assertSame('2026-09-07 08:00:00', StoredClock::bind($skopje)->format('Y-m-d H:i:s'));
    }

    public function test_between_binds_both_ends_for_a_where_between(): void
    {
        $monday = CarbonImmutable::parse('2026-08-31 00:00:00', 'America/New_York');
        $sunday = CarbonImmutable::parse('2026-09-06 23:59:59', 'America/New_York');

        [$from, $to] = StoredClock::between($monday, $sunday);

        $this->assertSame('2026-08-31 06:00:00', $from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-07 05:59:59', $to->format('Y-m-d H:i:s'));
    }

    public function test_read_places_a_stored_value_on_another_clock(): void
    {
        // Stored as the app clock: Monday 05:30 in Skopje …
        $stored = CarbonImmutable::parse('2026-09-07 05:30:00', config('app.timezone'));

        // … which is still Sunday to a New Yorker.
        $local = StoredClock::read($stored, CarbonTimeZone::create('America/New_York'));

        $this->assertSame('2026-09-06 23:30:00', $local->format('Y-m-d H:i:s'));
        $this->assertSame(7, $local->dayOfWeekIso);

        $sameClockAs = CarbonImmutable::parse('2026-09-07 08:00', 'America/New_York');
        $this->assertSame('America/New_York', StoredClock::read($stored, $sameClockAs)->getTimezone()->getName());
    }
}
