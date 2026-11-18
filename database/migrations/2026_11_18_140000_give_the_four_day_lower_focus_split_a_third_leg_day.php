<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 4-day lower-focus Split was the balanced Split reordered (#146):
 * Legs / Push / Legs / Pull against balanced's Push / Legs / Pull / Legs
 * — the same four day types, so the same weekly volume. A member who
 * picked Lower Body at four days a week changed nothing about their week.
 *
 * It becomes Legs / Upper Body / Legs / Legs+Core: three leg days to one
 * upper, in line with the 5-day lower-focus Split's three.
 *
 * Accepted consequence: an Upper Body day's arms Slot is tier-3
 * (ADR-0010), so a 4-day lower-focus member doing 45- or 60-minute
 * sessions now gets no direct arm work, where the outgoing shape carried
 * tier-1 triceps on its Push day and tier-1 biceps on its Pull day. The
 * third leg day was judged worth it; a Pull day in place of the Upper
 * Body day would keep biceps at the same 3:1 ratio if that is ever
 * revisited.
 *
 * Written as a migration rather than applied through the splits admin
 * CRUD so that production and WorkoutSplitSeeder move together — the
 * seeder is updated in the same commit — and so the change has a record.
 * Idempotent: it writes the target state by (days_per_week, focus,
 * day_index) rather than reading what is there.
 */
return new class extends Migration
{
    private const FOCUS = 'lower_focus';

    private const DAYS = 4;

    /** @var array<int, list<string>> */
    private const AFTER = [
        0 => ['LOWER'],
        1 => ['UPPER_PUSH', 'UPPER_PULL'],
        2 => ['LOWER'],
        3 => ['LOWER', 'CORE'],
    ];

    /** @var array<int, list<string>> */
    private const BEFORE = [
        0 => ['LOWER'],
        1 => ['UPPER_PUSH'],
        2 => ['LOWER', 'CORE'],
        3 => ['UPPER_PULL'],
    ];

    public function up(): void
    {
        $this->write(self::AFTER);
    }

    public function down(): void
    {
        $this->write(self::BEFORE);
    }

    /**
     * @param  array<int, list<string>>  $regionsByDayIndex
     */
    private function write(array $regionsByDayIndex): void
    {
        foreach ($regionsByDayIndex as $dayIndex => $regions) {
            DB::table('workout_splits')
                ->where('days_per_week', self::DAYS)
                ->where('focus', self::FOCUS)
                ->where('day_index', $dayIndex)
                ->update([
                    'target_regions' => json_encode($regions),
                    'updated_at' => now(),
                ]);
        }
    }
};
