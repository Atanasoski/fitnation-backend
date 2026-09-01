<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutSession\PersonalRecord;
use App\Services\WorkoutSession\PersonalRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What POST /api/workout-sessions/{session}/complete counts as a personal
 * record, and what App\Services\WorkoutSession\PersonalRecords detects for the
 * same session with no HTTP involved. Written against the behaviour that
 * shipped, before the rules moved out of the controller, so the extraction is
 * shown to have changed nothing rather than claimed to.
 *
 * These lock current behaviour, not desired behaviour. Two of them lock things
 * issue 010 argues are wrong — a first-ever session records a PR for every
 * exercise, and weight and reps are independent maxima that need not come from
 * the same set — and one locks issue 003's re-completion bug. Each says so.
 * Changing any of those is a separate decision, and these tests are where it
 * gets made.
 */
class PersonalRecordDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 10:00:00');

        $this->user = User::factory()->create(['partner_id' => Partner::factory()->create()->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Questionable, and locked deliberately: "no history" counts as beaten by
     * both rules, so a first workout with six exercises produces twelve
     * records. Issue 010, consequence 2.
     */
    public function test_a_first_ever_session_records_a_weight_and_a_reps_pr_for_every_exercise(): void
    {
        $bench = $this->exercise('Bench Press');
        $squat = $this->exercise('Back Squat');

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 80.0, 8);
        $this->logSet($session, $squat, 2, 100.0, 5);

        $this->assertRecords([
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'weight', 'previous_best' => 0, 'new_best' => 80.0],
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'reps', 'previous_best' => 0, 'new_best' => 8],
            ['exercise_id' => $squat->id, 'exercise_name' => 'Back Squat', 'pr_type' => 'weight', 'previous_best' => 0, 'new_best' => 100.0],
            ['exercise_id' => $squat->id, 'exercise_name' => 'Back Squat', 'pr_type' => 'reps', 'previous_best' => 0, 'new_best' => 5],
        ], $session);
    }

    public function test_beating_a_previous_weight_but_not_previous_reps_records_one_pr(): void
    {
        $bench = $this->exercise('Bench Press');
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 110.0, 6);

        $this->assertRecords([
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'weight', 'previous_best' => 100.0, 'new_best' => 110.0],
        ], $session);
    }

    public function test_beating_neither_records_nothing(): void
    {
        $bench = $this->exercise('Bench Press');
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 90.0, 10);

        $this->assertRecords([], $session);
    }

    /**
     * Equalling a best is not beating it: both rules are strict `>`.
     */
    public function test_equalling_a_previous_best_records_nothing(): void
    {
        $bench = $this->exercise('Bench Press');
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 100.0, 12);

        $this->assertRecords([], $session);
    }

    /**
     * Questionable, and locked deliberately: the two rules read the session's
     * own maxima independently, so a heavy single and a light high-rep set
     * produce a weight PR and a reps PR describing a performance nobody
     * achieved in one set. Issue 010, consequence 1.
     */
    public function test_weight_and_reps_prs_need_not_come_from_the_same_set(): void
    {
        $bench = $this->exercise('Bench Press');
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 110.0, 1);
        $this->logSet($session, $bench, 2, 40.0, 20);

        $this->assertRecords([
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'weight', 'previous_best' => 100.0, 'new_best' => 110.0],
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'reps', 'previous_best' => 12, 'new_best' => 20],
        ], $session);
    }

    /**
     * Weights keep their fraction. They are in Canonical Units — kilograms —
     * and nothing converts them on the way out, so an imperial user reads this
     * number as if it were pounds. That contradicts ADR-0001 and is left
     * exactly as it was: locked here rather than fixed, so the extraction stays
     * behaviour-preserving and the fix is a decision of its own.
     */
    public function test_a_fractional_weight_survives_unconverted(): void
    {
        $bench = $this->exercise('Bench Press');
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 102.5, 6);

        $this->assertRecords([
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'weight', 'previous_best' => 100.0, 'new_best' => 102.5],
        ], $session);
    }

    /**
     * Only the user's own completed sessions count as history: another user's
     * heavier set, and this user's cancelled or still-active ones, do not.
     */
    public function test_history_is_the_users_own_completed_sessions(): void
    {
        $bench = $this->exercise('Bench Press');

        $stranger = User::factory()->create(['partner_id' => $this->user->partner_id]);
        $this->logSet($this->completedSession($stranger), $bench, 1, 200.0, 30);
        $this->logSet($this->makeSession(WorkoutSessionStatus::Cancelled), $bench, 1, 200.0, 30);
        $this->logSet($this->activeSession(), $bench, 1, 200.0, 30);
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 110.0, 13);

        $this->assertRecords([
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'weight', 'previous_best' => 100.0, 'new_best' => 110.0],
            ['exercise_id' => $bench->id, 'exercise_name' => 'Bench Press', 'pr_type' => 'reps', 'previous_best' => 12, 'new_best' => 13],
        ], $session);
    }

    /**
     * A session nobody logged a set in beats nothing, rather than beating
     * everything the way an unlogged exercise does.
     */
    public function test_a_session_with_no_sets_records_nothing(): void
    {
        $this->assertRecords([], $this->activeSession());
    }

    /**
     * Issue 003's bug, locked here so that issue owns changing it: completing a
     * session that is already completed re-emits every record, because the
     * session excludes itself from its own history whatever its status.
     */
    public function test_completing_an_already_completed_session_re_emits_the_same_records(): void
    {
        $bench = $this->exercise('Bench Press');
        $this->history($bench, 100.0, 12);

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 110.0, 6);

        $first = $this->complete($session);
        $second = $this->complete($session);

        $this->assertSame($first, $second);
        $this->assertCount(1, $first);
    }

    /**
     * Detection is a read: it neither completes the session nor touches it. The
     * caller decides a session is finished and persists that itself.
     */
    public function test_detecting_records_changes_nothing_about_the_session(): void
    {
        $bench = $this->exercise('Bench Press');

        $session = $this->activeSession();
        $this->logSet($session, $bench, 1, 110.0, 6);

        $this->assertCount(2, PersonalRecords::detect($session));

        $this->assertDatabaseHas('workout_sessions', [
            'id' => $session->id,
            'status' => WorkoutSessionStatus::Active->value,
            'completed_at' => null,
        ]);
    }

    // ------------------------------------------------------------ assertions

    /**
     * The same expectation twice: what the module detects for a session with no
     * HTTP involved, and what the endpoint emits for it. Asserting both from
     * one list is what makes the extraction demonstrable — the module is asked
     * first, while the session is still active, so it is also the assertion
     * that detection needs neither a request nor a completed session.
     *
     * Expectations are written in the records' own types — weights float, reps
     * int — and the wire is compared after the JSON round trip the response
     * goes through, which renders a whole float as a bare number.
     *
     * @param  list<array<string, mixed>>  $expected
     */
    private function assertRecords(array $expected, WorkoutSession $session): void
    {
        $this->assertSame($expected, $this->detect($session), 'The module detected different records.');

        $this->assertSame(
            json_decode(json_encode($expected), true),
            $this->complete($session),
            'The endpoint emitted different records than the module detected.'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detect(WorkoutSession $session): array
    {
        return PersonalRecords::detect($session)
            ->map(fn (PersonalRecord $record) => [
                'exercise_id' => $record->exerciseId,
                'exercise_name' => $record->exerciseName,
                'pr_type' => $record->type->value,
                'previous_best' => $record->previousBest,
                'new_best' => $record->newBest,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function complete(WorkoutSession $session): array
    {
        return $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/complete")
            ->assertOk()
            ->json('new_prs');
    }

    // --------------------------------------------------------------- fixture

    private function exercise(string $name): Exercise
    {
        $exercise = Exercise::factory()->create(['name' => $name]);
        $exercise->partners()->attach($this->user->partner_id);

        return $exercise;
    }

    /**
     * A completed session of this user's holding one set: the prior best the
     * rules compare against.
     */
    private function history(Exercise $exercise, float $weight, int $reps): WorkoutSession
    {
        $session = $this->completedSession($this->user);

        $this->logSet($session, $exercise, 1, $weight, $reps);

        return $session;
    }

    private function activeSession(): WorkoutSession
    {
        return $this->makeSession(WorkoutSessionStatus::Active);
    }

    private function completedSession(User $user): WorkoutSession
    {
        return $this->makeSession(WorkoutSessionStatus::Completed, $user);
    }

    private function makeSession(WorkoutSessionStatus $status, ?User $user = null): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => ($user ?? $this->user)->id,
            'workout_template_id' => null,
            'performed_at' => now()->subHour(),
            'completed_at' => $status === WorkoutSessionStatus::Completed ? now()->subHour() : null,
            'notes' => null,
            'status' => $status,
        ]);
    }

    private function logSet(WorkoutSession $session, Exercise $exercise, int $setNumber, float $weight, int $reps): SetLog
    {
        return SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exercise->id,
            'set_number' => $setNumber,
            'weight' => $weight,
            'reps' => $reps,
            'rest_seconds' => 90,
        ]);
    }
}
