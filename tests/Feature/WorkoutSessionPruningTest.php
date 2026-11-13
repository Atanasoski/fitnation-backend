<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft Sessions and Cancelled ones the user never logged a set in are kept
 * as long as Insights can look back (ADR-0012), then pruned.
 */
class WorkoutSessionPruningTest extends TestCase
{
    use RefreshDatabase;

    private function aged(WorkoutSessionStatus $status, int $daysOld): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'status' => $status,
            'performed_at' => null,
            'completed_at' => null,
            'created_at' => now()->subDays($daysOld),
        ]);
    }

    private function prune(): void
    {
        $this->artisan('model:prune', ['--model' => [WorkoutSession::class]])->assertSuccessful();
    }

    public function test_a_draft_older_than_the_longest_insights_range_is_pruned(): void
    {
        $draft = $this->aged(WorkoutSessionStatus::Draft, daysOld: 91);

        $this->prune();

        $this->assertModelMissing($draft);
    }

    public function test_a_swapped_session_older_than_the_longest_insights_range_is_pruned(): void
    {
        $swapped = $this->aged(WorkoutSessionStatus::Cancelled, daysOld: 91);
        $replacement = $this->aged(WorkoutSessionStatus::Completed, daysOld: 91);
        $replacement->update(['replaced_session_id' => $swapped->id]);

        $this->prune();

        $this->assertModelMissing($swapped);
        $this->assertModelExists($replacement);
    }

    public function test_insights_can_still_see_every_session_in_its_longest_range(): void
    {
        $draft = $this->aged(WorkoutSessionStatus::Draft, daysOld: 89);
        $cancelled = $this->aged(WorkoutSessionStatus::Cancelled, daysOld: 89);

        $this->prune();

        $this->assertModelExists($draft);
        $this->assertModelExists($cancelled);
    }

    public function test_a_cancelled_session_with_a_set_logged_is_kept(): void
    {
        $cancelled = $this->aged(WorkoutSessionStatus::Cancelled, daysOld: 200);
        SetLog::create([
            'workout_session_id' => $cancelled->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'set_number' => 1,
            'weight' => 50,
            'reps' => 8,
            'rest_seconds' => 90,
        ]);

        $this->prune();

        $this->assertModelExists($cancelled);
    }

    public function test_completed_and_active_sessions_are_never_pruned(): void
    {
        $completed = $this->aged(WorkoutSessionStatus::Completed, daysOld: 200);
        $active = $this->aged(WorkoutSessionStatus::Active, daysOld: 200);

        $this->prune();

        $this->assertModelExists($completed);
        $this->assertModelExists($active);
    }
}
