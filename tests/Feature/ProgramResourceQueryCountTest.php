<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Enums\WorkoutSessionStatus;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards issue 011: serializing a program must not fan out queries per workout
 * template. Progress, the next workout, the active week and every template's
 * last completed session all come from one batched lookup, so the query count
 * is flat in the number of templates.
 */
class ProgramResourceQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_query_count_does_not_grow_with_template_count(): void
    {
        $user = $this->makeUser();

        $small = $this->makeProgram($user, 3);
        $large = $this->makeProgram($user, 12);

        $smallQueries = $this->countQueriesForShow($user, $small);
        $largeQueries = $this->countQueriesForShow($user, $large);

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            "Query count scaled with template count: {$smallQueries} for 3 templates, ".
            "{$largeQueries} for 12. The per-template completion lookups must be batched."
        );
    }

    public function test_index_query_count_does_not_grow_with_template_count(): void
    {
        $user = $this->makeUser();
        $this->makeProgram($user, 3);

        $smallQueries = $this->countQueriesForIndex($user);

        $otherUser = $this->makeUser();
        $this->makeProgram($otherUser, 12);

        $largeQueries = $this->countQueriesForIndex($otherUser);

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            "Listing query count scaled with template count: {$smallQueries} for a program with ".
            "3 templates, {$largeQueries} for one with 12."
        );
    }

    public function test_next_workout_query_count_does_not_grow_with_template_count(): void
    {
        $user = $this->makeUser();

        // Fully completed programs: the old implementation walked every
        // template before concluding there was no next workout, so this is
        // where the fan-out is worst.
        $small = $this->completeEvery($this->makeProgram($user, 3));
        $large = $this->completeEvery($this->makeProgram($user, 12));

        $smallQueries = $this->countQueries(
            fn (User $actor) => $this->actingAs($actor, 'sanctum')
                ->getJson("/api/programs/{$small->id}/next-workout")
                ->assertOk(),
            $user
        );
        $largeQueries = $this->countQueries(
            fn (User $actor) => $this->actingAs($actor, 'sanctum')
                ->getJson("/api/programs/{$large->id}/next-workout")
                ->assertOk(),
            $user
        );

        $this->assertSame(
            $smallQueries,
            $largeQueries,
            "next-workout query count scaled with template count: {$smallQueries} for 3 templates, ".
            "{$largeQueries} for 12."
        );
    }

    private function completeEvery(Plan $program): Plan
    {
        foreach ($program->workoutTemplates as $template) {
            WorkoutSession::factory()->create([
                'user_id' => $program->user_id,
                'workout_template_id' => $template->id,
                'status' => WorkoutSessionStatus::Completed,
                'completed_at' => now(),
            ]);
        }

        return $program;
    }

    private function countQueriesForShow(User $user, Plan $program): int
    {
        return $this->countQueries(
            fn (User $actor) => $this->actingAs($actor, 'sanctum')
                ->getJson("/api/programs/{$program->id}")
                ->assertOk(),
            $user
        );
    }

    private function countQueriesForIndex(User $user): int
    {
        return $this->countQueries(
            fn (User $actor) => $this->actingAs($actor, 'sanctum')
                ->getJson('/api/programs')
                ->assertOk(),
            $user
        );
    }

    private function countQueries(callable $request, User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        // A fresh instance per request: actingAs() authenticates the object it
        // is handed, so a reused $user carries relations cached by the previous
        // request and undercounts the second one.
        $request($user->fresh());

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    private function makeUser(): User
    {
        $partner = Partner::factory()->create();

        return User::factory()->create(['partner_id' => $partner->id]);
    }

    /**
     * A program with $count templates spread over weeks, the first of which the
     * user has completed — so the batched lookup has both a hit and misses.
     */
    private function makeProgram(User $user, int $count): Plan
    {
        $plan = Plan::factory()->create([
            'user_id' => $user->id,
            'type' => PlanType::Program,
            'duration_weeks' => 4,
            'is_active' => true,
        ]);

        $first = null;

        for ($i = 0; $i < $count; $i++) {
            $template = WorkoutTemplate::factory()->create([
                'plan_id' => $plan->id,
                'week_number' => intdiv($i, 3) + 1,
                'order_index' => $i % 3,
            ]);

            $first ??= $template;
        }

        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $first->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        return $plan;
    }
}
