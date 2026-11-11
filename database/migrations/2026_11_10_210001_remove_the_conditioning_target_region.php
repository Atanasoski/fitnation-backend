<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CONDITIONING served no exercise and no day (0 of each in the production
 * copy on 2026-11-10) and declared no Fragment, so requesting it could only
 * shrink a composed day (#127, ADR-0010). It is removed rather than
 * special-cased.
 *
 * Exercises reference regions by foreign key (workout_exercises.
 * target_region_id) and splits by code (the workout_splits.target_regions
 * JSON array). While either still references CONDITIONING the migration
 * refuses, deleting nothing: re-file those rows first.
 */
return new class extends Migration
{
    private const CODE = 'CONDITIONING';

    public function up(): void
    {
        $id = DB::table('target_regions')->where('code', self::CODE)->value('id');

        if ($id === null) {
            return;
        }

        $exercises = DB::table('workout_exercises')->where('target_region_id', $id)->count();
        $splits = DB::table('workout_splits')
            ->pluck('target_regions')
            ->filter(fn (?string $regions) => in_array(self::CODE, json_decode($regions ?? '[]', true) ?: [], true))
            ->count();

        if ($exercises > 0 || $splits > 0) {
            throw new RuntimeException(sprintf(
                'Cannot remove the %s target region: %d exercise(s) and %d workout split(s) still reference it. Re-file them under another region first.',
                self::CODE,
                $exercises,
                $splits,
            ));
        }

        DB::table('target_regions')->where('id', $id)->delete();
    }

    public function down(): void
    {
        if (DB::table('target_regions')->where('code', self::CODE)->exists()) {
            return;
        }

        DB::table('target_regions')->insert([
            'code' => self::CODE,
            'name' => 'Conditioning',
            'display_order' => 70,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
