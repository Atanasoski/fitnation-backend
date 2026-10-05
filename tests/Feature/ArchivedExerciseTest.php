<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\Exercise\ArchiveOutcome;
use App\Services\Exercise\ExerciseArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Archived Exercise rule (spec 023, Seam 2): an exercise anyone ever used
 * is archived and keeps every row that references it; one nobody used is
 * deleted outright.
 */
class ArchivedExerciseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_exercise_used_only_in_a_template_is_archived_and_the_template_row_kept(): void
    {
        $exercise = Exercise::factory()->create();
        $row = $this->templateRow($exercise);

        ExerciseArchive::archiveOrDelete($exercise);

        $this->assertNotNull($exercise->fresh()?->archived_at);
        $this->assertDatabaseHas('workout_template_exercises', ['id' => $row->id, 'exercise_id' => $exercise->id]);
    }

    public function test_an_exercise_used_only_in_a_session_is_archived_and_the_session_row_kept(): void
    {
        $exercise = Exercise::factory()->create();
        $row = $this->sessionRow($exercise);

        ExerciseArchive::archiveOrDelete($exercise);

        $this->assertNotNull($exercise->fresh()?->archived_at);
        $this->assertDatabaseHas('workout_session_exercises', ['id' => $row->id, 'exercise_id' => $exercise->id]);
    }

    public function test_an_exercise_used_only_in_set_logs_is_archived_and_the_sets_kept(): void
    {
        $exercise = Exercise::factory()->create();
        $set = $this->setLog($exercise);

        ExerciseArchive::archiveOrDelete($exercise);

        $this->assertNotNull($exercise->fresh()?->archived_at);
        $this->assertDatabaseHas('workout_session_set_logs', ['id' => $set->id, 'exercise_id' => $exercise->id]);
    }

    public function test_an_exercise_nobody_used_is_deleted(): void
    {
        $exercise = Exercise::factory()->create();

        ExerciseArchive::archiveOrDelete($exercise);

        $this->assertDatabaseMissing('workout_exercises', ['id' => $exercise->id]);
    }

    public function test_the_outcome_says_whether_the_exercise_was_archived_or_deleted(): void
    {
        $used = Exercise::factory()->create();
        $this->setLog($used);

        $this->assertSame(ArchiveOutcome::Archived, ExerciseArchive::archiveOrDelete($used));
        $this->assertSame(ArchiveOutcome::Deleted, ExerciseArchive::archiveOrDelete(Exercise::factory()->create()));
    }

    public function test_restore_clears_the_archive(): void
    {
        $exercise = Exercise::factory()->create();
        $this->templateRow($exercise);
        ExerciseArchive::archiveOrDelete($exercise);

        ExerciseArchive::restore($exercise);

        $this->assertNull($exercise->fresh()?->archived_at);
    }

    public function test_bulk_follows_the_same_rule_and_counts_archived_and_deleted(): void
    {
        $inTemplate = Exercise::factory()->create();
        $this->templateRow($inTemplate);
        $inSession = Exercise::factory()->create();
        $this->sessionRow($inSession);
        $unused = Exercise::factory()->create();
        $untouched = Exercise::factory()->create();

        $counts = ExerciseArchive::archiveOrDeleteMany([$inTemplate->id, $inSession->id, $unused->id]);

        $this->assertSame(['archived' => 2, 'deleted' => 1], $counts);
        $this->assertNotNull($inTemplate->fresh()?->archived_at);
        $this->assertNotNull($inSession->fresh()?->archived_at);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $unused->id]);
        $this->assertNull($untouched->fresh()?->archived_at);
    }

    private function templateRow(Exercise $exercise): WorkoutTemplateExercise
    {
        return WorkoutTemplateExercise::create([
            'workout_template_id' => WorkoutTemplate::factory()->create()->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
    }

    private function sessionRow(Exercise $exercise): WorkoutSessionExercise
    {
        return WorkoutSessionExercise::create([
            'workout_session_id' => WorkoutSession::factory()->create()->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
    }

    private function setLog(Exercise $exercise): SetLog
    {
        return SetLog::create([
            'workout_session_id' => WorkoutSession::factory()->create()->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 60,
            'reps' => 8,
        ]);
    }
}
