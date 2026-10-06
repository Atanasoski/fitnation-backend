<?php

namespace App\Services\Plan;

use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Support\Facades\DB;

/**
 * The order of a workout's exercise rows (023/07). Rows are numbered 0, 1, 2…
 * with no gaps or ties, which every change here restores, so older data with
 * gapped or duplicated orders is repaired the first time it is touched. Ties
 * break by id, the order rows were added in.
 */
final class WorkoutRowOrder
{
    /**
     * The order a row added to the end of this workout takes.
     */
    public static function next(WorkoutTemplate $workout): int
    {
        return $workout->workoutTemplateExercises()->count();
    }

    /**
     * Move a row one place up (-1) or down (+1). A row already at that end
     * stays where it is.
     */
    public static function move(WorkoutTemplateExercise $row, int $by): void
    {
        DB::transaction(function () use ($row, $by) {
            $ids = self::ids($row->workoutTemplate);
            $from = array_search($row->id, $ids, true);
            $to = $from + $by;

            if (isset($ids[$to])) {
                [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            }

            self::write($ids);
        });
    }

    /**
     * Renumber a workout's rows after one was removed.
     */
    public static function close(WorkoutTemplate $workout): void
    {
        DB::transaction(fn () => self::write(self::ids($workout)));
    }

    /**
     * @return array<int, int>
     */
    private static function ids(WorkoutTemplate $workout): array
    {
        return WorkoutTemplateExercise::query()
            ->where('workout_template_id', $workout->id)
            ->orderBy('order')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $ids  row ids in their new order
     */
    private static function write(array $ids): void
    {
        foreach ($ids as $order => $id) {
            WorkoutTemplateExercise::whereKey($id)->where('order', '!=', $order)->update(['order' => $order]);
        }
    }
}
