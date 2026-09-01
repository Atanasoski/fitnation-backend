<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Services\WorkoutSession\SetOwnership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards issue 009: one module owns "which row does this set belong to".
 *
 * WorkoutSessionDuplicateExerciseTest covers the same rule through the API and
 * is the wider safety net; this file tests the module directly and pins the web
 * view to the same answer the API gives.
 */
class SetOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_legacy_set_belongs_to_neither_of_two_rows_carrying_its_exercise(): void
    {
        [, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->legacySet($session, $first->exercise_id, 1);

        $ownership = SetOwnership::forSession($session);

        $this->assertCount(0, $ownership->setsFor($first));
        $this->assertCount(0, $ownership->setsFor($second));
    }

    public function test_a_legacy_set_belongs_to_the_sole_row_carrying_its_exercise(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);
        $row = $this->addExerciseRow($session, $exercise, 0);

        $this->legacySet($session, $exercise->id, 1);

        $this->assertCount(1, SetOwnership::forSession($session)->setsFor($row));
    }

    public function test_attached_sets_belong_to_their_own_row(): void
    {
        [, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->attachedSet($first, 1, 100.0);
        $this->attachedSet($first, 2, 100.0);
        $this->attachedSet($second, 1, 60.0);

        $ownership = SetOwnership::forSession($session);

        $this->assertSame([100.0, 100.0], $ownership->setsFor($first)->map(fn ($s) => (float) $s->weight)->all());
        $this->assertSame([60.0], $ownership->setsFor($second)->map(fn ($s) => (float) $s->weight)->all());
    }

    public function test_sets_come_back_ordered_by_set_number(): void
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);
        $row = $this->addExerciseRow($session, $exercise, 0);

        $this->attachedSet($row, 3, 60.0);
        $this->attachedSet($row, 1, 60.0);
        $this->attachedSet($row, 2, 60.0);

        $this->assertSame(
            [1, 2, 3],
            SetOwnership::forSession($session)->setsFor($row)->pluck('set_number')->all()
        );
    }

    public function test_constrain_selects_exactly_the_sets_the_read_path_renders(): void
    {
        [, $session, $first, $second] = $this->sessionWithDuplicateExercise();
        $other = $this->makeExercise($session->user);
        $otherRow = $this->addExerciseRow($session, $other, 2);

        $this->attachedSet($first, 1, 100.0);
        $this->attachedSet($second, 1, 60.0);
        $this->legacySet($session, $first->exercise_id, 1);
        $this->legacySet($session, $other->id, 1);

        $ownership = SetOwnership::forSession($session);

        $claimed = collect();

        foreach ([$first, $second, $otherRow] as $row) {
            $constrained = $ownership->constrain(SetLog::query(), $row)->pluck('id')->all();

            $this->assertEqualsCanonicalizing(
                $ownership->setsFor($row)->pluck('id')->all(),
                $constrained,
                "constrain() and setsFor() disagree for row {$row->id}."
            );

            $claimed = $claimed->merge($constrained);
        }

        // Every set except the unattributable legacy one is claimed, and by
        // exactly one row.
        $this->assertSame($claimed->unique()->count(), $claimed->count(), 'No set may be claimed twice.');
        $this->assertSame(3, $claimed->count(), 'The legacy set on the duplicated exercise belongs to no row.');
    }

    public function test_the_write_side_places_a_row_less_set_on_the_earliest_row(): void
    {
        [, $session, $first] = $this->sessionWithDuplicateExercise();

        $this->assertSame(
            $first->id,
            SetOwnership::forSession($session)->rowForExercise($first->exercise_id)?->id
        );
    }

    /**
     * The bug in issue 009: the staff-facing view defaulted its legacy-set flag
     * to true and so showed an unattributable set under both rows.
     */
    public function test_the_web_view_agrees_with_the_api_on_which_sets_belong_where(): void
    {
        [$user, $session, $first, $second] = $this->sessionWithDuplicateExercise();

        $this->attachedSet($first, 1, 100.0);
        $this->legacySet($session, $first->exercise_id, 1);

        $staff = User::factory()->create(['partner_id' => $user->partner_id]);

        $rows = $this->actingAs($staff)
            ->get("/users/{$user->id}/workout-sessions/{$session->id}")
            ->assertOk()
            ->viewData('exerciseRows');

        $byRowId = $rows->keyBy(fn ($row) => $row->sessionExercise->id);

        $this->assertCount(1, $byRowId[$first->id]->setsForExercise, 'The first row owns only its attached set.');
        $this->assertCount(0, $byRowId[$second->id]->setsForExercise, 'The legacy set must not appear under the duplicate row.');
    }

    // ---------------------------------------------------------------- helpers

    private function legacySet(WorkoutSession $session, int $exerciseId, int $setNumber): SetLog
    {
        return SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exerciseId,
            'set_number' => $setNumber,
            'weight' => 60,
            'reps' => 10,
            'rest_seconds' => 90,
        ]);
    }

    private function attachedSet(WorkoutSessionExercise $row, int $setNumber, float $weight): SetLog
    {
        return SetLog::create([
            'workout_session_id' => $row->workout_session_id,
            'workout_session_exercise_id' => $row->id,
            'exercise_id' => $row->exercise_id,
            'set_number' => $setNumber,
            'weight' => $weight,
            'reps' => 10,
            'rest_seconds' => 90,
        ]);
    }

    /**
     * @return array{0: User, 1: WorkoutSession, 2: WorkoutSessionExercise, 3: WorkoutSessionExercise}
     */
    private function sessionWithDuplicateExercise(): array
    {
        $user = $this->makeUser();
        $session = $this->makeSession($user);
        $exercise = $this->makeExercise($user);

        return [
            $user,
            $session,
            $this->addExerciseRow($session, $exercise, 0),
            $this->addExerciseRow($session, $exercise, 1),
        ];
    }

    private function makeUser(): User
    {
        $partner = Partner::factory()->create();

        return User::factory()->create(['partner_id' => $partner->id]);
    }

    private function makeSession(User $user): WorkoutSession
    {
        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $session->setRelation('user', $user);

        return $session;
    }

    private function makeExercise(User $user): Exercise
    {
        $exercise = Exercise::factory()->press()->barbell()->flat()->create();
        $exercise->partners()->attach($user->partner_id);

        return $exercise;
    }

    private function addExerciseRow(WorkoutSession $session, Exercise $exercise, int $order): WorkoutSessionExercise
    {
        return WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 60,
            'rest_seconds' => 90,
        ]);
    }
}
