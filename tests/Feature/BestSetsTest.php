<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutSession\BestSets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BestSetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-28 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_picks_the_set_with_the_highest_estimated_one_rep_max_before_this_session(): void
    {
        $user = User::factory()->create();
        $exercise = $this->makeExercise('BENCH', 'BARBELL');
        $current = $this->makeSession($user, WorkoutSessionStatus::Active, now());

        // 60 × 12 → 84 · 70 × 5 → 81.7 · 65 × 8 → 82.3 (Epley): the lighter, longer set wins.
        $older = $this->makeSession($user, WorkoutSessionStatus::Completed, now()->subWeeks(3));
        $this->logSet($older, $exercise, 70, 5);
        $recent = $this->makeSession($user, WorkoutSessionStatus::Completed, now()->subWeek());
        $this->logSet($recent, $exercise, 60, 12);
        $this->logSet($recent, $exercise, 65, 8);
        // Today's own sets never count as "ever before", however good.
        $this->logSet($current, $exercise, 80, 6);
        // Nor does an unfinished session, nor another user's.
        $abandoned = $this->makeSession($user, WorkoutSessionStatus::Cancelled, now()->subDays(2));
        $this->logSet($abandoned, $exercise, 100, 10);
        $other = $this->makeSession(User::factory()->create(), WorkoutSessionStatus::Completed, now()->subDay());
        $this->logSet($other, $exercise, 120, 10);

        $best = BestSets::forSession($current, [$exercise->id]);

        $this->assertEquals(
            ['weight' => 60.0, 'reps' => 12, 'performed_at' => now()->subWeek()->toJSON()],
            $best->get($exercise->id)
        );
    }

    public function test_bodyweight_ties_are_decided_by_reps(): void
    {
        $user = User::factory()->create();
        $exercise = $this->makeExercise('PULL_UP', 'BODYWEIGHT');
        $current = $this->makeSession($user, WorkoutSessionStatus::Active, now());
        $done = $this->makeSession($user, WorkoutSessionStatus::Completed, now()->subWeek());
        $this->logSet($done, $exercise, 0, 8);
        $this->logSet($done, $exercise, 0, 11);
        $this->logSet($done, $exercise, 0, 9);

        $this->assertSame(11, BestSets::forSession($current, [$exercise->id])->get($exercise->id)['reps']);
    }

    public function test_no_history_means_no_entry(): void
    {
        $user = User::factory()->create();
        $exercise = $this->makeExercise('ROW', 'DUMBBELL');
        $current = $this->makeSession($user, WorkoutSessionStatus::Active, now());

        $this->assertTrue(BestSets::forSession($current, [$exercise->id])->isEmpty());
        $this->assertTrue(BestSets::forSession($current, [])->isEmpty());
    }

    private function makeExercise(string $movement, string $equipment): Exercise
    {
        $pattern = MovementPattern::firstOrCreate(['code' => $movement], ['name' => $movement, 'display_order' => 1]);
        $type = EquipmentType::firstOrCreate(['code' => $equipment], ['name' => $equipment, 'display_order' => 1]);

        return Exercise::factory()->create([
            'movement_pattern_id' => $pattern->id,
            'equipment_type_id' => $type->id,
        ]);
    }

    private function makeSession(User $user, WorkoutSessionStatus $status, Carbon $performedAt): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => $performedAt,
            'completed_at' => $status === WorkoutSessionStatus::Completed ? $performedAt->copy()->addHour() : null,
            'status' => $status,
        ]);
    }

    private function logSet(WorkoutSession $session, Exercise $exercise, float $weight, int $reps): SetLog
    {
        return SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exercise->id,
            'set_number' => $session->setLogs()->count() + 1,
            'weight' => $weight,
            'reps' => $reps,
            'rest_seconds' => 90,
        ]);
    }
}
