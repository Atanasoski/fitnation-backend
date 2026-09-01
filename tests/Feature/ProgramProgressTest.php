<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Enums\WorkoutSessionStatus;
use App\Http\Resources\Api\ProgramResource;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Behaviour of program progress: which sessions count as having completed a
 * workout, and whose. Issue 011.
 */
class ProgramProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    /** @var array<int, WorkoutTemplate> */
    private array $templates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $partner = Partner::factory()->create();
        $this->user = User::factory()->create(['partner_id' => $partner->id]);

        $this->plan = Plan::factory()->create([
            'user_id' => $this->user->id,
            'type' => PlanType::Program,
            'duration_weeks' => 4,
            'is_active' => true,
        ]);

        foreach ([0, 1] as $index) {
            $this->templates[$index] = WorkoutTemplate::factory()->create([
                'plan_id' => $this->plan->id,
                'week_number' => 1,
                'order_index' => $index,
            ]);
        }
    }

    /**
     * @return iterable<string, array{WorkoutSessionStatus}>
     */
    public static function nonCompletedStatuses(): iterable
    {
        yield 'cancelled' => [WorkoutSessionStatus::Cancelled];
        yield 'active' => [WorkoutSessionStatus::Active];
        yield 'draft' => [WorkoutSessionStatus::Draft];
    }

    /**
     * Locks rather than catches: the raw 'completed' string this replaced was
     * equal to WorkoutSessionStatus::Completed's backing value, so the defect
     * was latent — no payload was ever wrong. The test earns its place by
     * failing if that backing value ever moves.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonCompletedStatuses')]
    public function test_only_completed_sessions_count_towards_progress(WorkoutSessionStatus $status): void
    {
        foreach ($this->templates as $template) {
            $this->logSession($template, $status, $this->user);
        }

        $payload = $this->showProgram();

        $this->assertSame(0, $payload['progress_percentage']);
        $this->assertSame($this->templates[0]->id, $payload['next_workout']['id']);
        $this->assertSame(1, $payload['current_active_week']);
        $this->assertNull($payload['workout_templates'][0]['last_completed_session_id']);
    }

    public function test_another_users_completed_session_does_not_count(): void
    {
        $other = User::factory()->create(['partner_id' => $this->user->partner_id]);

        foreach ($this->templates as $template) {
            $this->logSession($template, WorkoutSessionStatus::Completed, $other);
        }

        $payload = $this->showProgram();

        $this->assertSame(0, $payload['progress_percentage']);
        $this->assertSame($this->templates[0]->id, $payload['next_workout']['id']);
        $this->assertNull($payload['workout_templates'][0]['last_completed_session_id']);
    }

    public function test_last_completed_session_id_is_the_most_recent_one(): void
    {
        $older = $this->logSession($this->templates[0], WorkoutSessionStatus::Completed, $this->user, now()->subWeek());
        $newer = $this->logSession($this->templates[0], WorkoutSessionStatus::Completed, $this->user, now());

        $payload = $this->showProgram();

        $this->assertSame($newer->id, $payload['workout_templates'][0]['last_completed_session_id']);
        $this->assertNotSame($older->id, $payload['workout_templates'][0]['last_completed_session_id']);
        // json_encode drops the zero fraction, so 50.0 arrives as an int.
        $this->assertSame(50, $payload['progress_percentage']);
    }

    /**
     * The resource must take its user from the request rather than the global
     * auth() helper, so a program can be serialized from a queue job or a test
     * with no authenticated session.
     */
    public function test_the_resource_serializes_outside_a_request(): void
    {
        $this->logSession($this->templates[0], WorkoutSessionStatus::Completed, $this->user);

        $this->plan->load(['workoutTemplates' => fn ($query) => $query->orderedByProgram()]);

        $request = Request::create("/api/programs/{$this->plan->id}");
        $request->setUserResolver(fn () => $this->user);

        $payload = (new ProgramResource($this->plan))->toArray($request);

        $this->assertNull(auth()->user(), 'The test must run with no authenticated user.');
        $this->assertSame(50.0, $payload['progress_percentage']);
        $this->assertSame($this->templates[1]->id, $payload['next_workout']->resource->id);
        $this->assertSame(1, $payload['current_active_week']);
    }

    /**
     * @return array<string, mixed>
     */
    private function showProgram(): array
    {
        return $this->actingAs($this->user->fresh(), 'sanctum')
            ->getJson("/api/programs/{$this->plan->id}")
            ->assertOk()
            ->json('data');
    }

    private function logSession(
        WorkoutTemplate $template,
        WorkoutSessionStatus $status,
        User $user,
        ?\Illuminate\Support\Carbon $completedAt = null,
    ): WorkoutSession {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template->id,
            'status' => $status,
            'completed_at' => $status === WorkoutSessionStatus::Completed
                ? ($completedAt ?? now())
                : null,
        ]);
    }
}
