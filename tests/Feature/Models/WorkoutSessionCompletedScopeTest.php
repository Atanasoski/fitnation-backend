<?php

namespace Tests\Feature\Models;

use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Completed Session definition (CONTEXT.md) as a query: status decides,
 * completed_at does not. Every reader composes this scope rather than
 * restating the column.
 */
class WorkoutSessionCompletedScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_status_decides(): void
    {
        $user = User::factory()->create();

        $completed = WorkoutSession::factory()->for($user)->create(['status' => WorkoutSessionStatus::Completed, 'completed_at' => now()]);
        $completedWithoutTimestamp = WorkoutSession::factory()->for($user)->create(['status' => WorkoutSessionStatus::Completed, 'completed_at' => null]);
        WorkoutSession::factory()->for($user)->create(['status' => WorkoutSessionStatus::Active, 'completed_at' => now()]);
        WorkoutSession::factory()->for($user)->create(['status' => WorkoutSessionStatus::Cancelled, 'completed_at' => now()]);
        WorkoutSession::factory()->for($user)->create(['status' => WorkoutSessionStatus::Draft, 'completed_at' => null]);

        $this->assertEqualsCanonicalizing(
            [$completed->id, $completedWithoutTimestamp->id],
            WorkoutSession::query()->completed()->pluck('id')->all(),
        );
    }

    public function test_it_composes_through_relations(): void
    {
        $trained = User::factory()->create();
        WorkoutSession::factory()->for($trained)->create(['status' => WorkoutSessionStatus::Completed]);
        $started = User::factory()->create();
        WorkoutSession::factory()->for($started)->create(['status' => WorkoutSessionStatus::Active]);

        $this->assertSame(1, $trained->workoutSessions()->completed()->count());
        $this->assertSame(0, $started->workoutSessions()->completed()->count());
        $this->assertSame(
            [$trained->id],
            User::query()->whereHas('workoutSessions', fn ($q) => $q->completed())->whereKey([$trained->id, $started->id])->pluck('id')->all(),
        );
    }
}
