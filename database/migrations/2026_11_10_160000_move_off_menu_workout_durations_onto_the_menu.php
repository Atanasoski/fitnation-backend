<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Session length is a fixed menu (30/45/60/90/120, ADR-0010), but profiles
 * saved before the menu can store other values (75 in production). Clients
 * echo the stored value back on every profile save, so validation rejected
 * the whole save for those members.
 *
 * Each off-menu value moves to the nearest menu value of the same Tier
 * (under 40 Express, 40–60 Standard, over 60 Extended), so the member's
 * sessions keep their length class: 75 becomes 90, not 60. Ties go to the
 * shorter option. Empty values stay empty.
 *
 * The menu and tier thresholds are copied here rather than read from
 * WorkoutDuration and SessionTier, so this migration keeps doing what it did
 * when it ran even if those change later.
 */
return new class extends Migration
{
    private const MENU = [30, 45, 60, 90, 120];

    public function up(): void
    {
        DB::table('user_profiles')
            ->whereNotNull('workout_duration_minutes')
            ->whereNotIn('workout_duration_minutes', self::MENU)
            ->distinct()
            ->pluck('workout_duration_minutes')
            ->each(fn ($minutes) => DB::table('user_profiles')
                ->where('workout_duration_minutes', $minutes)
                ->update([
                    'workout_duration_minutes' => $this->nearestMenuValue((int) $minutes),
                    'updated_at' => now(),
                ]));
    }

    /**
     * No-op: the off-menu value a profile stored is not recorded anywhere,
     * so it cannot be restored. The menu values it moved to stay.
     */
    public function down(): void {}

    private function nearestMenuValue(int $minutes): int
    {
        // The menu values of this duration's Tier, shortest first.
        $options = match (true) {
            $minutes < 40 => [30],
            $minutes <= 60 => [45, 60],
            default => [90, 120],
        };

        $nearest = $options[0];
        foreach ($options as $option) {
            // Strictly closer only, so a tie keeps the shorter option.
            if (abs($option - $minutes) < abs($nearest - $minutes)) {
                $nearest = $option;
            }
        }

        return $nearest;
    }
};
