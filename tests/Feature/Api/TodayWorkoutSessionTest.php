<?php

namespace Tests\Feature\Api;

use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /workout-sessions/today offers the session to resume. An abandoned
 * Draft Session (CONTEXT.md) is not one: the web dashboard's "Start workout"
 * would reopen a weeks-old preview instead of generating today's.
 */
class TodayWorkoutSessionTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $user, int $hoursOld): WorkoutSession
    {
        return WorkoutSession::factory()->for($user)->create([
            'workout_template_id' => null,
            'status' => WorkoutSessionStatus::Draft,
            'performed_at' => null,
            'completed_at' => null,
            'created_at' => now()->subHours($hoursOld),
        ]);
    }

    private function todaysSessionId(User $user): ?int
    {
        return $this->actingAs($user, 'sanctum')
            ->getJson('/api/workout-sessions/today')
            ->assertOk()
            ->json('data.session.id');
    }

    public function test_an_abandoned_draft_is_not_offered(): void
    {
        $user = User::factory()->entitled()->create();
        $this->draft($user, hoursOld: 48);

        $this->assertNull($this->todaysSessionId($user));
    }

    public function test_a_fresh_draft_is_offered(): void
    {
        $user = User::factory()->entitled()->create();
        $draft = $this->draft($user, hoursOld: 1);

        $this->assertSame($draft->id, $this->todaysSessionId($user));
    }
}
