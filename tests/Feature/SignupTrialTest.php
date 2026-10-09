<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Models\User;
use App\Services\WelcomePlanGenerationService;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Spec 0038: a new user gets seven free days of app access when onboarding
 * completes — no card, no store — and meets the paywall only when they end.
 */
class SignupTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkoutSplitSeeder::class);
        Carbon::setTestNow('2026-10-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function onboardable(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->profile()->update([
            'training_days_per_week' => 3,
            'gender' => Gender::Male,
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Beginner,
            'workout_duration_minutes' => 60,
        ]);

        return $user;
    }

    private function completeOnboarding(User $user): void
    {
        $generator = $this->createMock(DeterministicWorkoutGenerator::class);
        $generator->method('generate')->willReturn(['exercises' => [], 'rationale' => 'test']);

        (new WelcomePlanGenerationService($generator))->generateWelcomePlan($user);
    }

    public function test_completing_onboarding_starts_a_seven_day_trial(): void
    {
        $user = $this->onboardable();
        $this->assertFalse($user->hasAppAccess());

        $this->completeOnboarding($user);

        $user->refresh();
        $this->assertEquals('2026-10-12 12:00:00', $user->grace_period_ends_at->toDateTimeString());
        $this->assertTrue($user->hasAppAccess());
    }

    public function test_the_api_reports_access_once_onboarding_is_complete(): void
    {
        $user = $this->onboardable();
        $this->completeOnboarding($user);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', ['app_access'])
            ->assertJsonPath('user.subscription.status', null)
            // Serialized in UTC whatever the app timezone (finding #3 territory).
            ->assertJsonPath('user.subscription.grace_period_ends_at', Carbon::parse('2026-10-12 12:00:00')->toJSON());
    }

    public function test_the_api_says_the_access_is_a_signup_trial_and_how_long_it_runs(): void
    {
        $user = $this->onboardable();
        $this->completeOnboarding($user);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.subscription.access_source', 'signup_trial')
            ->assertJsonPath('user.subscription.free_access_kind', 'signup_trial')
            ->assertJsonPath('user.subscription.enforced', true)
            ->assertJsonPath('user.subscription.signup_trial_days', 7);
    }

    public function test_the_trial_ends_on_its_date(): void
    {
        $user = $this->onboardable();
        $this->completeOnboarding($user);

        Carbon::setTestNow('2026-10-12 12:00:01');

        $this->assertFalse($user->fresh()->hasAppAccess());
    }

    public function test_a_grace_already_granted_is_never_moved(): void
    {
        $user = $this->onboardable(['grace_period_ends_at' => '2026-11-04 12:00:00']);

        $this->completeOnboarding($user);

        $this->assertEquals('2026-11-04 12:00:00', $user->fresh()->grace_period_ends_at->toDateTimeString());
    }

    public function test_the_trial_is_one_shot_per_account(): void
    {
        $user = $this->onboardable();

        $this->assertTrue($user->startSignupTrial());
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->assertFalse($user->startSignupTrial());

        $this->assertEquals('2026-10-12 12:00:00', $user->fresh()->grace_period_ends_at->toDateTimeString());
    }

    public function test_zero_days_disables_the_trial(): void
    {
        config(['subscriptions.signup_trial_days' => 0]);
        $user = $this->onboardable();

        $this->completeOnboarding($user);

        $this->assertNull($user->fresh()->grace_period_ends_at);
        $this->assertFalse($user->fresh()->hasAppAccess());
    }

    public function test_the_length_follows_the_config(): void
    {
        config(['subscriptions.signup_trial_days' => 14]);
        $user = $this->onboardable();

        $this->completeOnboarding($user);

        $this->assertEquals('2026-10-19 12:00:00', $user->fresh()->grace_period_ends_at->toDateTimeString());
    }

    public function test_the_launch_grace_command_skips_trialed_users(): void
    {
        $trialed = $this->onboardable();
        $this->completeOnboarding($trialed);
        $existing = User::factory()->create();

        $this->artisan('subscriptions:grant-launch-grace', ['--force' => true, '--days' => 30])
            ->expectsOutput('Granted 30 days of grace access to 1 user(s).')
            ->assertSuccessful();

        $this->assertEquals('2026-10-12 12:00:00', $trialed->fresh()->grace_period_ends_at->toDateTimeString());
        $this->assertEquals('2026-11-04 12:00:00', $existing->fresh()->grace_period_ends_at->toDateTimeString());

        $this->assertDatabaseHas('users', ['id' => $existing->id, 'free_access_kind' => 'complimentary']);
        $this->actingAs($existing->fresh(), 'sanctum')->getJson('/api/user')
            ->assertJsonPath('user.subscription.free_access_kind', 'complimentary')
            ->assertJsonPath('user.subscription.access_source', 'complimentary');
        $this->actingAs($trialed->fresh(), 'sanctum')->getJson('/api/user')
            ->assertJsonPath('user.subscription.free_access_kind', 'signup_trial');
    }

    public function test_the_api_reports_the_trial_length_and_enforcement_even_when_off(): void
    {
        config(['subscriptions.enforced' => false, 'subscriptions.signup_trial_days' => 14]);
        $user = $this->onboardable();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.entitlements', ['app_access'])
            ->assertJsonPath('user.subscription.enforced', false)
            ->assertJsonPath('user.subscription.signup_trial_days', 14)
            ->assertJsonPath('user.subscription.access_source', 'none')
            ->assertJsonPath('user.subscription.free_access_kind', null)
            ->assertJsonPath('user.subscription.grace_period_ends_at', null);
    }

    public function test_the_trial_gets_the_user_past_the_gate_and_its_end_closes_it(): void
    {
        $user = $this->onboardable();
        $this->completeOnboarding($user);

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/plans')->assertOk();

        Carbon::setTestNow('2026-10-12 12:00:01');

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/plans')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_required');
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/user')
            ->assertJsonPath('user.subscription.access_source', 'none')
            ->assertJsonPath('user.subscription.free_access_kind', 'signup_trial');
    }
}
