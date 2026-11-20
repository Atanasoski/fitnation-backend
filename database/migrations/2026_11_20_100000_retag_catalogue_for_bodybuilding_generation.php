<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0014: generated workouts are Bodybuilding, so the TRX, Band and
 * Bodyweight movements that are genuine hypertrophy work gain the
 * BODYBUILDING Training Style (alongside whatever they already carry), and
 * Goblet Squat moves from Kettlebell to Dumbbell. Mirrors the inline data in
 * `exercises:import`. Matched by exact name; names missing from this
 * database are skipped.
 */
return new class extends Migration
{
    private const BODYBUILDING_ADDED = [
        'TRX Row', 'TRX Wide Row', 'TRX Biceps Curl', 'TRX Triceps Extension', 'TRX Push-Up',
        'TRX Chest Fly', 'TRX Pec Fly', 'TRX Y Fly', 'TRX T Fly', 'TRX Wide Fly',
        'TRX Hamstring Curl', 'TRX Bulgarian Split Squat', 'TRX Squat',
        'Band Biceps Curl', 'Band Chest Press', 'Band Chest Fly', 'Band Overhead Press',
        'Band Lateral Raise', 'Band Row', 'Seated Band Row', 'Band Face Pull',
        'Band Pull-Apart', 'Band Triceps Pushdown', 'Band Squat',
        'Pike Push-ups', 'Handstand Push-ups', 'Cossack Lunge',
    ];

    public function up(): void
    {
        $styleId = DB::table('training_styles')->where('code', 'BODYBUILDING')->value('id');

        if ($styleId !== null) {
            foreach (DB::table('workout_exercises')->whereIn('name', self::BODYBUILDING_ADDED)->pluck('id') as $exerciseId) {
                DB::table('exercise_training_style')->insertOrIgnore([
                    'exercise_id' => $exerciseId,
                    'training_style_id' => $styleId,
                ]);
            }
        }

        $this->moveGobletSquat('KETTLEBELL', 'DUMBBELL');
    }

    public function down(): void
    {
        $styleId = DB::table('training_styles')->where('code', 'BODYBUILDING')->value('id');

        if ($styleId !== null) {
            DB::table('exercise_training_style')
                ->where('training_style_id', $styleId)
                ->whereIn('exercise_id', DB::table('workout_exercises')->whereIn('name', self::BODYBUILDING_ADDED)->select('id'))
                ->delete();
        }

        $this->moveGobletSquat('DUMBBELL', 'KETTLEBELL');
    }

    private function moveGobletSquat(string $from, string $to): void
    {
        $toId = DB::table('equipment_types')->where('code', $to)->value('id');
        $fromId = DB::table('equipment_types')->where('code', $from)->value('id');

        if ($toId === null || $fromId === null) {
            return;
        }

        DB::table('workout_exercises')
            ->where('name', 'Goblet Squat')
            ->where('equipment_type_id', $fromId)
            ->update(['equipment_type_id' => $toId]);
    }
};
