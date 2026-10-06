<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Insights Training tab (spec 024): the module's headline answers appear
 * on their cards. The numbers themselves are InsightsTest's job.
 */
class InsightsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_the_training_tab_shows_the_retention_and_first_workout_headlines(): void
    {
        // Signed up 29 days ago: first Completed Session 12 h later, and again in week 4.
        $ada = $this->member('2026-09-08 12:00:00');
        $this->workoutSession($ada, '2026-09-09 00:00:00');
        $this->workoutSession($ada, '2026-10-01 12:00:00');
        // The 30 days before: started after 20 h.
        $this->workoutSession($this->member('2026-08-20 12:00:00'), '2026-08-21 08:00:00');

        $this->actingAs($this->admin())
            ->get('/admin/insights')
            ->assertOk()
            ->assertSeeInOrder(['Do people keep training?', '100%', 'still log a Completed Session in week 4'])
            ->assertSeeInOrder(['How fast do they start?', '12 h', 'median from signup to first Completed Session', '−8 h vs the 30 days before']);
    }

    public function test_without_old_enough_signups_the_cards_say_so(): void
    {
        $this->member('2026-10-06 12:00:00');

        $this->actingAs($this->admin())
            ->get('/admin/insights?range=7')
            ->assertOk()
            ->assertSee('no signup in the last 7 days has reached week 4 yet')
            ->assertSee('no signup in the last 7 days has trained yet');
    }

    private function member(string $signedUpAt): User
    {
        return User::factory()->create(['created_at' => $signedUpAt, 'onboarding_completed_at' => $signedUpAt]);
    }

    private function workoutSession(User $user, string $completedAt): void
    {
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => Carbon::parse($completedAt)->subHour(),
            'completed_at' => $completedAt,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $user;
    }
}
