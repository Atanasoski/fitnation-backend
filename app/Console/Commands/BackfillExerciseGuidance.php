<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Services\Guidance\GuidanceGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fills the nullable guidance columns (`instructions`, `common_mistakes`) on
 * exercises from their catalogue data via an AI call (spec qr-stations,
 * ticket 06). Run manually by a content maintainer, never scheduled.
 *
 * It only ever writes a column that is null — existing content, hand-written
 * or generated, is never overwritten. A failed exercise is reported and
 * skipped; the run continues.
 */
class BackfillExerciseGuidance extends Command
{
    protected $signature = 'exercises:backfill-guidance
                            {--dry-run : Print what would be written without persisting anything}
                            {--exercise=* : Only these exercise ids}
                            {--limit= : Process at most this many exercises}';

    protected $description = 'Backfill AI-generated instructions and common mistakes on exercises with null guidance columns';

    public function handle(GuidanceGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $exercises = $this->candidates();

        if ($exercises->isEmpty()) {
            $this->info('Nothing to backfill: every targeted exercise already has both guidance columns.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Backfilling guidance for {$exercises->count()} exercise(s).");
        $this->newLine();

        $filled = 0;
        $failed = 0;

        foreach ($exercises as $exercise) {
            try {
                $guidance = $generator->generate($exercise);
            } catch (Throwable $e) {
                $failed++;
                $this->error("  [{$exercise->id}] {$exercise->name}: {$e->getMessage()} — skipped.");
                Log::warning('[BackfillExerciseGuidance] Generation failed', [
                    'exercise_id' => $exercise->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // Only null columns are written; existing content always wins.
            $writes = [];
            if ($exercise->instructions === null) {
                $writes['instructions'] = $guidance->instructions;
            }
            if ($exercise->common_mistakes === null) {
                $writes['common_mistakes'] = $guidance->commonMistakes;
            }

            if (! $dryRun) {
                $exercise->update($writes);
            }

            $filled++;
            $this->line(sprintf(
                '  [%d] %s: %s %s',
                $exercise->id,
                $exercise->name,
                $dryRun ? 'would write' : 'wrote',
                implode(' and ', array_keys($writes)),
            ));

            if ($dryRun) {
                foreach ($writes as $column => $values) {
                    $this->line("      {$column}:");
                    foreach ($values as $value) {
                        $this->line("        - {$value}");
                    }
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Done: %d %s, %d failed and skipped.',
            $filled,
            $dryRun ? 'would be filled' : 'filled',
            $failed,
        ));

        return self::SUCCESS;
    }

    /**
     * Exercises with at least one null guidance column, optionally narrowed
     * to given ids and capped. Archived exercises are included on purpose:
     * they can return to circulation with their guidance ready.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Exercise>
     */
    private function candidates()
    {
        $ids = array_filter($this->option('exercise'));
        $limit = $this->option('limit');

        return Exercise::query()
            ->where(fn (Builder $query) => $query
                ->whereNull('instructions')
                ->orWhereNull('common_mistakes'))
            ->when($ids !== [], fn (Builder $query) => $query->whereKey($ids))
            ->when($limit !== null, fn (Builder $query) => $query->limit(max(1, (int) $limit)))
            ->orderBy('id')
            ->with(['primaryMuscleGroups', 'secondaryMuscleGroups', 'equipmentType'])
            ->get();
    }
}
