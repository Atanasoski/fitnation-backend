<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Locks the JSON the program read paths emit, so the ProgramProgress refactor
 * (issue 011) can demonstrate — rather than assert — that it changed nothing.
 *
 * These tests describe current behaviour, not desired behaviour. Every fixture
 * value is set explicitly rather than faked, so the whole payload can be
 * compared for equality instead of spot checked: a refactor that drops a key,
 * reorders one, or stops eager-loading the next workout's exercises is then a
 * failure rather than something someone has to notice in review.
 */
class ProgramProgressCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    /** @var array<int, WorkoutTemplate> */
    private array $templates = [];

    private Exercise $exercise;

    private string $ts;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-30 10:00:00');
        $this->ts = now()->toJSON();

        $partner = Partner::factory()->create();
        $this->user = User::factory()->create(['partner_id' => $partner->id]);

        $this->plan = Plan::factory()->create([
            'user_id' => $this->user->id,
            'partner_id' => $partner->id,
            'name' => 'Hypertrophy Block',
            'description' => 'Four weeks.',
            'cover_image' => 'plans/cover.jpg',
            'is_active' => true,
            'is_auto_generated' => false,
            'type' => PlanType::Program,
            'duration_weeks' => 4,
        ]);

        // Deliberately created out of program order: the payload must come back
        // ordered by week_number then order_index, not by id.
        foreach ([2 => [2, 0], 0 => [1, 0], 1 => [1, 1]] as $index => [$week, $order]) {
            $this->templates[$index] = WorkoutTemplate::factory()->create([
                'plan_id' => $this->plan->id,
                'name' => "Day {$index}",
                'description' => "Desc {$index}",
                'day_of_week' => $index,
                'week_number' => $week,
                'order_index' => $order,
            ]);
        }

        $this->exercise = Exercise::factory()->create([
            'name' => 'Bench Press',
            'description' => null,
            'default_rest_sec' => 90,
        ]);
        $this->exercise->partners()->attach($partner->id);

        // Only on the second template — the one that is the next workout in the
        // partly-completed case — so the nested exercise load is under test.
        $this->templates[1]->exercises()->attach($this->exercise->id, [
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 60,
            'rest_seconds' => 90,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_show_payload_for_a_partly_completed_program(): void
    {
        $firstSession = $this->complete($this->templates[0]);

        $payload = $this->showProgram();

        $this->assertSame([
            'id' => $this->plan->id,
            'name' => 'Hypertrophy Block',
            'description' => 'Four weeks.',
            'cover_image' => Storage::url('plans/cover.jpg'),
            'duration_weeks' => 4,
            'is_active' => true,
            'is_auto_generated' => false,
            'is_library_plan' => false,
            'progress_percentage' => 33.33,
            'next_workout' => $this->templatePayload(
                $this->templates[1],
                lastCompletedSessionId: null,
                withExercise: true,
            ),
            'current_active_week' => 1,
            'workout_templates' => [
                $this->templatePayload($this->templates[0], lastCompletedSessionId: $firstSession->id),
                $this->templatePayload($this->templates[1], lastCompletedSessionId: null, withExercise: true),
                $this->templatePayload($this->templates[2], lastCompletedSessionId: null),
            ],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ], $payload);
    }

    public function test_show_payload_when_every_workout_is_complete(): void
    {
        $sessions = [
            $this->complete($this->templates[0])->id,
            $this->complete($this->templates[1])->id,
            $this->complete($this->templates[2])->id,
        ];

        $payload = $this->showProgram();

        // json_encode drops the zero fraction, so a whole percentage arrives
        // as an int.
        $this->assertSame(100, $payload['progress_percentage']);
        // A finished program has no next workout, and the resource emits null
        // rather than omitting the key.
        $this->assertNull($payload['next_workout']);
        // The last week that has a template, not duration_weeks.
        $this->assertSame(2, $payload['current_active_week']);
        $this->assertSame([
            $this->templatePayload($this->templates[0], lastCompletedSessionId: $sessions[0]),
            $this->templatePayload($this->templates[1], lastCompletedSessionId: $sessions[1], withExercise: true),
            $this->templatePayload($this->templates[2], lastCompletedSessionId: $sessions[2]),
        ], $payload['workout_templates']);
    }

    public function test_show_payload_for_an_untouched_program(): void
    {
        $payload = $this->showProgram();

        $this->assertSame(0, $payload['progress_percentage']);
        $this->assertSame($this->templates[0]->id, $payload['next_workout']['id']);
        $this->assertSame(1, $payload['current_active_week']);
    }

    public function test_progress_keys_are_absent_for_a_partner_library_program(): void
    {
        $partner = Partner::factory()->create();
        $library = Plan::factory()->partnerLibrary($partner)->create(['is_active' => true]);
        WorkoutTemplate::factory()->create(['plan_id' => $library->id]);

        $user = User::factory()->create(['partner_id' => $partner->id]);

        $payload = $this->actingAs($user, 'sanctum')
            ->getJson('/api/programs/library')
            ->assertOk()
            ->json('data.0');

        // user_id is null on a library plan, so `when()` drops all three keys.
        $this->assertArrayNotHasKey('progress_percentage', $payload);
        $this->assertArrayNotHasKey('next_workout', $payload);
        $this->assertArrayNotHasKey('current_active_week', $payload);
        $this->assertTrue($payload['is_library_plan']);
    }

    public function test_next_workout_endpoint_payload(): void
    {
        $this->complete($this->templates[0]);

        $payload = $this->actingAs($this->user->fresh(), 'sanctum')
            ->getJson("/api/programs/{$this->plan->id}/next-workout")
            ->assertOk()
            ->json('data');

        $this->assertSame(
            $this->templatePayload($this->templates[1], lastCompletedSessionId: null, withExercise: true),
            $payload
        );
    }

    public function test_next_workout_endpoint_returns_null_for_a_finished_program(): void
    {
        foreach ($this->templates as $template) {
            $this->complete($template);
        }

        $this->actingAs($this->user->fresh(), 'sanctum')
            ->getJson("/api/programs/{$this->plan->id}/next-workout")
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showProgram(): array
    {
        // A fresh instance per request: actingAs() authenticates the object it
        // is handed, so a reused $user carries relations cached earlier.
        return $this->actingAs($this->user->fresh(), 'sanctum')
            ->getJson("/api/programs/{$this->plan->id}")
            ->assertOk()
            ->json('data');
    }

    private function complete(WorkoutTemplate $template): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $this->user->id,
            'workout_template_id' => $template->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(
        WorkoutTemplate $template,
        ?int $lastCompletedSessionId,
        bool $withExercise = false,
    ): array {
        return [
            'id' => $template->id,
            'plan_id' => $this->plan->id,
            'name' => $template->name,
            'description' => $template->description,
            'day_of_week' => $template->day_of_week,
            'week_number' => $template->week_number,
            'order_index' => $template->order_index,
            'last_completed_session_id' => $lastCompletedSessionId,
            'exercises' => $withExercise ? [[
                'id' => $this->exercise->id,
                'name' => 'Bench Press',
                'description' => null,
                'image' => null,
                'video' => null,
                'muscle_group_image' => null,
                'default_rest_sec' => 90,
                'category' => null,
                'muscle_groups' => [],
                'pivot' => [
                    'id' => $template->exercises()->first()->pivot->id,
                    'order' => 0,
                    'target_sets' => 3,
                    'min_target_reps' => 8,
                    'max_target_reps' => 12,
                    'target_weight' => 60,
                    'rest_seconds' => 90,
                ],
            ]] : [],
            'created_at' => $this->ts,
            'updated_at' => $this->ts,
        ];
    }
}
