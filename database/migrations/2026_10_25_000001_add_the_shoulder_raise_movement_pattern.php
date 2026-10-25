<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lateral and front raises had no movement pattern of their own, so the
 * classifier's fallback filed them as PRESS. That gave them a bench press's
 * starting weight in ProgressionCalculatorService (65% of body weight before
 * the equipment factor), so a 175 lb intermediate was told 60 lbs on cable
 * lateral raises and 55 lbs per dumbbell.
 *
 * SHOULDER_RAISE has its own starting-weight multiplier. Raises still filed
 * as PRESS move to it. A raise staff filed under another pattern is left
 * alone, and so is a rear-delt raise, which is REAR_DELT_FLY's. The workout
 * generator still selects raises as PRESS (workout_generator.selection_aliases),
 * so generated plans do not change.
 */
return new class extends Migration
{
    private const RAISE_NAMES = ['%lateral raise%', '%front raise%', '%side raise%'];

    public function up(): void
    {
        if (! DB::table('movement_patterns')->where('code', 'SHOULDER_RAISE')->exists()) {
            DB::table('movement_patterns')->insert([
                'code' => 'SHOULDER_RAISE',
                'name' => 'Shoulder Raise',
                'display_order' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $press = DB::table('movement_patterns')->where('code', 'PRESS')->value('id');
        $raise = DB::table('movement_patterns')->where('code', 'SHOULDER_RAISE')->value('id');

        if ($press === null) {
            return;
        }

        DB::table('workout_exercises')
            ->where('movement_pattern_id', $press)
            ->where(function ($query) {
                foreach (self::RAISE_NAMES as $name) {
                    $query->orWhere('name', 'like', $name);
                }
            })
            ->where('name', 'not like', '%rear%')
            ->update(['movement_pattern_id' => $raise, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $raise = DB::table('movement_patterns')->where('code', 'SHOULDER_RAISE')->value('id');

        if ($raise === null) {
            return;
        }

        DB::table('workout_exercises')
            ->where('movement_pattern_id', $raise)
            ->update(['movement_pattern_id' => DB::table('movement_patterns')->where('code', 'PRESS')->value('id')]);

        DB::table('movement_patterns')->where('id', $raise)->delete();
    }
};
