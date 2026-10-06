<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FitnessGoal;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Notifications\InactivityNudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

    public function test_the_generator_and_skipped_cards_lead_with_their_answers(): void
    {
        $ada = $this->member('2026-08-01 12:00:00');
        $this->workoutSession($ada, '2026-09-20 12:00:00', generated: true);
        $this->workoutSession($ada, '2026-09-21 12:00:00', generated: false);

        $this->actingAs($this->admin())
            ->get('/admin/insights')
            ->assertOk()
            ->assertSeeInOrder(['Are generated workouts any good?', '100%', 'of generated sessions get completed', '100% for the rest'])
            ->assertSeeInOrder(['Which exercises get skipped?', 'No exercise was included 100 times in Completed Sessions in the last 30 days']);
    }

    public function test_the_skipped_list_links_each_exercise_to_its_catalogue_entry(): void
    {
        $session = $this->workoutSession($this->member('2026-08-01 12:00:00'), '2026-09-20 12:00:00');
        $plank = Exercise::factory()->create(['name' => 'Side Plank']);
        foreach (range(1, 100) as $row) {
            WorkoutSessionExercise::create(['workout_session_id' => $session->id, 'exercise_id' => $plank->id]);
        }

        $this->actingAs($this->admin())
            ->get('/admin/insights')
            ->assertOk()
            ->assertSeeInOrder(['Which exercises get skipped?', '100%', 'of Side Plank entries have no logged set'])
            ->assertSee(route('exercises.show', $plank->id))
            ->assertSee('100% · 100 of 100');
    }

    public function test_the_planned_nudges_and_who_cards_lead_with_their_answers(): void
    {
        // Onboarded before the range, three planned days, three Completed Sessions in 30 days.
        $ada = $this->member('2026-08-01 12:00:00');
        $ada->update(['notification_settings' => ['weekly_summary_email' => false]]);
        $ada->profile->update(['training_days_per_week' => 3, 'fitness_goal' => FitnessGoal::FatLoss, 'age' => 30]);
        $this->travelTo('2026-09-10 10:00:00');
        $ada->notifications()->create(['id' => (string) Str::uuid(), 'type' => InactivityNudge::class, 'data' => ['step' => 3]]);
        $this->travelTo('2026-10-07 12:00:00');
        foreach (['2026-09-11', '2026-09-20', '2026-09-21'] as $day) {
            $this->workoutSession($ada, "{$day} 12:00:00");
        }

        $this->actingAs($this->admin())
            ->get('/admin/insights')
            ->assertOk()
            ->assertSeeInOrder(['Do they train as often as they said?', '23%', 'of planned days happen'])
            ->assertSeeInOrder(['Do nudges work?', '100%', 'train within 48 h of the day-3 Inactivity Nudge', '1 of 1'])
            ->assertSeeInOrder(['Who are our users?', '1', 'onboarded app users', 'Fat loss', 'Not set', '25–34']);
    }

    public function test_without_data_the_new_cards_say_so(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/insights?range=7')
            ->assertOk()
            ->assertSee('nobody who onboarded before the last 7 days has planned training days')
            ->assertSee('no day-3 Inactivity Nudge was sent in the last 7 days');
    }

    private function member(string $signedUpAt): User
    {
        return User::factory()->create(['created_at' => $signedUpAt, 'onboarding_completed_at' => $signedUpAt]);
    }

    private function workoutSession(User $user, string $completedAt, bool $generated = false): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'is_auto_generated' => $generated,
            'created_at' => $completedAt,
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
