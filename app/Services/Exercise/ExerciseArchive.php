<?php

namespace App\Services\Exercise;

use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Support\Facades\DB;

/**
 * Removing an exercise from the catalogue, without ever destroying anyone's
 * training history: the Archived Exercise rule (spec 023).
 *
 * An exercise is used when any plan row, logged session exercise or logged set
 * references it. A used exercise is archived: it leaves searching, picking and
 * generating (Exercise::available()) but still loads through every relation, so
 * plans and history keep showing it. Only an exercise nobody used is deleted,
 * and then ON DELETE CASCADE touches nothing but its pivots (muscle groups,
 * training styles, partner_exercises).
 *
 * Before this, delete was a plain $exercise->delete(), and the same cascades
 * took every user's logged sets and session exercises for it with it.
 */
final class ExerciseArchive
{
    /**
     * Archive the exercise if anyone used it, delete it if nobody did.
     * Archiving an exercise that is already archived keeps its original date.
     */
    public static function archiveOrDelete(Exercise $exercise): ArchiveOutcome
    {
        if (! self::isUsed($exercise)) {
            $exercise->delete();

            return ArchiveOutcome::Deleted;
        }

        if ($exercise->archived_at === null) {
            $exercise->forceFill(['archived_at' => now()])->save();
        }

        return ArchiveOutcome::Archived;
    }

    /**
     * The same rule over several exercises. Ids that do not exist are ignored.
     *
     * @param  iterable<int>  $exerciseIds
     * @return array{archived: int, deleted: int}
     */
    public static function archiveOrDeleteMany(iterable $exerciseIds): array
    {
        $counts = ['archived' => 0, 'deleted' => 0];

        $exercises = Exercise::whereKey(collect($exerciseIds)->all())->get();

        DB::transaction(function () use ($exercises, &$counts) {
            foreach ($exercises as $exercise) {
                $counts[self::archiveOrDelete($exercise)->value]++;
            }
        });

        return $counts;
    }

    /**
     * Put an archived exercise back in the catalogue.
     */
    public static function restore(Exercise $exercise): void
    {
        $exercise->forceFill(['archived_at' => null])->save();
    }

    private static function isUsed(Exercise $exercise): bool
    {
        return WorkoutTemplateExercise::where('exercise_id', $exercise->id)->exists()
            || WorkoutSessionExercise::where('exercise_id', $exercise->id)->exists()
            || SetLog::where('exercise_id', $exercise->id)->exists();
    }
}
