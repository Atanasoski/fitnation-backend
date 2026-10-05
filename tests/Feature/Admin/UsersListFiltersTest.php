<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Device;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Users list filters beyond partner / Activity Status / Access Source
 * (ticket 05): each narrows on its own, they combine, and the URL alone
 * reproduces the list.
 */
class UsersListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Partner $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->gym = Partner::factory()->create();
    }

    public function test_the_goal_filter_narrows_to_users_with_that_fitness_goal(): void
    {
        $this->member(['name' => 'Ada Lovelace'], profile: ['fitness_goal' => FitnessGoal::Strength]);
        $this->member(['name' => 'Grace Hopper'], profile: ['fitness_goal' => FitnessGoal::FatLoss]);
        $this->member(['name' => 'Linus Torvalds'], profile: ['fitness_goal' => FitnessGoal::MuscleGain]);

        $this->asAdmin('/admin/users?goal=strength')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Grace Hopper')
            ->assertDontSee('Linus Torvalds');
    }

    public function test_the_experience_filter_narrows_to_users_with_that_training_experience(): void
    {
        $this->member(['name' => 'Ada Lovelace'], profile: ['training_experience' => TrainingExperience::Advanced]);
        $this->member(['name' => 'Grace Hopper'], profile: ['training_experience' => TrainingExperience::Beginner]);

        $this->asAdmin('/admin/users?experience=advanced')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Grace Hopper');
    }

    public function test_the_platform_filter_narrows_to_users_with_a_device_on_that_platform(): void
    {
        $ada = $this->member(['name' => 'Ada Lovelace']);
        Device::factory()->create(['user_id' => $ada->id, 'platform' => 'ios']);
        $grace = $this->member(['name' => 'Grace Hopper']);
        Device::factory()->create(['user_id' => $grace->id, 'platform' => 'android']);
        $both = $this->member(['name' => 'Barbara Liskov']);
        Device::factory()->create(['user_id' => $both->id, 'platform' => 'android']);
        Device::factory()->create(['user_id' => $both->id, 'platform' => 'ios']);
        $this->member(['name' => 'Linus Torvalds']);

        $this->asAdmin('/admin/users?platform=ios')
            ->assertSee('Ada Lovelace')
            ->assertSee('Barbara Liskov')
            ->assertDontSee('Grace Hopper')
            ->assertDontSee('Linus Torvalds');
    }

    public function test_the_signin_filter_separates_social_from_password_accounts(): void
    {
        $this->member(['name' => 'Ada Lovelace', 'social_provider' => 'google', 'social_provider_id' => 'g-1']);
        $this->member(['name' => 'Grace Hopper', 'social_provider' => 'apple', 'social_provider_id' => 'a-1']);
        $this->member(['name' => 'Linus Torvalds']);

        $this->asAdmin('/admin/users?signin=social')
            ->assertSee('Ada Lovelace')
            ->assertSee('Grace Hopper')
            ->assertDontSee('Linus Torvalds');

        $this->asAdmin('/admin/users?signin=password')
            ->assertSee('Linus Torvalds')
            ->assertDontSee('Ada Lovelace')
            ->assertDontSee('Grace Hopper');
    }

    public function test_the_stuck_filter_finds_users_with_a_session_left_active_over_24_hours(): void
    {
        $stuck = $this->member(['name' => 'Ada Lovelace']);
        $this->workoutSession($stuck, WorkoutSessionStatus::Active, now()->subHours(25));
        $training = $this->member(['name' => 'Grace Hopper']);
        $this->workoutSession($training, WorkoutSessionStatus::Active, now()->subHours(23));
        $done = $this->member(['name' => 'Linus Torvalds']);
        $this->workoutSession($done, WorkoutSessionStatus::Completed, now()->subDays(3));
        $draft = $this->member(['name' => 'Barbara Liskov']);
        $this->workoutSession($draft, WorkoutSessionStatus::Draft, now()->subDays(3));

        $this->asAdmin('/admin/users?stuck=1')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Grace Hopper')
            ->assertDontSee('Linus Torvalds')
            ->assertDontSee('Barbara Liskov');
    }

    public function test_the_text_search_matches_part_of_a_name_or_an_email(): void
    {
        $this->member(['name' => 'Ada Lovelace', 'email' => 'countess@example.com']);
        $this->member(['name' => 'Grace Hopper', 'email' => 'grace@navy.example']);
        $this->member(['name' => 'Linus Torvalds', 'email' => 'linus@example.com']);

        $this->asAdmin('/admin/users?q=love')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Grace Hopper')
            ->assertDontSee('Linus Torvalds');

        $this->asAdmin('/admin/users?q=navy')
            ->assertSee('Grace Hopper')
            ->assertDontSee('Ada Lovelace')
            ->assertDontSee('Linus Torvalds');
    }

    public function test_the_deleted_filter_shows_only_deleted_users(): void
    {
        $this->member(['name' => 'Ada Lovelace']);
        $this->member(['name' => 'Gone Gary'])->delete();

        $this->asAdmin('/admin/users')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Gone Gary');

        $this->asAdmin('/admin/users?deleted=1')
            ->assertSee('Gone Gary')
            ->assertDontSee('Ada Lovelace');
    }

    public function test_the_list_sorts_by_signup_in_both_directions(): void
    {
        $this->member(['name' => 'Middle Mo', 'created_at' => now()->subDays(20)]);
        $this->member(['name' => 'Oldest Olga', 'created_at' => now()->subDays(40)]);
        $this->member(['name' => 'Newest Nia', 'created_at' => now()->subDays(1)]);

        $this->asAdmin('/admin/users')->assertSeeInOrder(['Newest Nia', 'Middle Mo', 'Oldest Olga']);
        $this->asAdmin('/admin/users?sort=-signup')->assertSeeInOrder(['Newest Nia', 'Middle Mo', 'Oldest Olga']);
        $this->asAdmin('/admin/users?sort=signup')->assertSeeInOrder(['Oldest Olga', 'Middle Mo', 'Newest Nia']);
    }

    public function test_the_list_sorts_by_last_completed_session_in_both_directions_with_never_trained_last(): void
    {
        $this->member(['name' => 'Never Ned', 'created_at' => now()->subDays(1)]);
        $this->member(['name' => 'Recent Rae', 'created_at' => now()->subDays(30)], completedDaysAgo: [1, 20]);
        $this->member(['name' => 'Lapsed Lou', 'created_at' => now()->subDays(20)], completedDaysAgo: [9]);

        $this->asAdmin('/admin/users?sort=-last_session')->assertSeeInOrder(['Recent Rae', 'Lapsed Lou', 'Never Ned']);
        $this->asAdmin('/admin/users?sort=last_session')->assertSeeInOrder(['Lapsed Lou', 'Recent Rae', 'Never Ned']);
    }

    public function test_filters_combine(): void
    {
        $match = $this->member(['name' => 'Ada Lovelace', 'social_provider' => 'google', 'social_provider_id' => 'g-1'],
            profile: ['fitness_goal' => FitnessGoal::Strength, 'training_experience' => TrainingExperience::Advanced]);
        Device::factory()->create(['user_id' => $match->id, 'platform' => 'ios']);
        $this->workoutSession($match, WorkoutSessionStatus::Active, now()->subDays(2));

        $wrongPlatform = $this->member(['name' => 'Ada Android', 'social_provider' => 'google', 'social_provider_id' => 'g-2'],
            profile: ['fitness_goal' => FitnessGoal::Strength, 'training_experience' => TrainingExperience::Advanced]);
        Device::factory()->create(['user_id' => $wrongPlatform->id, 'platform' => 'android']);
        $this->workoutSession($wrongPlatform, WorkoutSessionStatus::Active, now()->subDays(2));

        $password = $this->member(['name' => 'Ada Password'],
            profile: ['fitness_goal' => FitnessGoal::Strength, 'training_experience' => TrainingExperience::Advanced]);
        Device::factory()->create(['user_id' => $password->id, 'platform' => 'ios']);
        $this->workoutSession($password, WorkoutSessionStatus::Active, now()->subDays(2));

        $notStuck = $this->member(['name' => 'Ada Fine', 'social_provider' => 'apple', 'social_provider_id' => 'a-1'],
            profile: ['fitness_goal' => FitnessGoal::Strength, 'training_experience' => TrainingExperience::Advanced]);
        Device::factory()->create(['user_id' => $notStuck->id, 'platform' => 'ios']);

        $this->member(['name' => 'Ada Beginner', 'social_provider' => 'apple', 'social_provider_id' => 'a-2'],
            profile: ['fitness_goal' => FitnessGoal::Strength, 'training_experience' => TrainingExperience::Beginner]);

        $this->asAdmin('/admin/users?q=ada&goal=strength&experience=advanced&platform=ios&signin=social&stuck=1')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Ada Android')
            ->assertDontSee('Ada Password')
            ->assertDontSee('Ada Fine')
            ->assertDontSee('Ada Beginner');
    }

    public function test_every_filter_rides_along_on_page_links_and_the_url_reproduces_the_list(): void
    {
        foreach (range(1, 30) as $i) {
            $user = $this->member(['name' => "Lifter {$i}", 'created_at' => now()->subDays($i)],
                profile: ['fitness_goal' => FitnessGoal::Strength, 'training_experience' => TrainingExperience::Advanced]);
            Device::factory()->create(['user_id' => $user->id, 'platform' => 'android']);
        }
        $this->member(['name' => 'Runner Rex'], profile: ['fitness_goal' => FitnessGoal::FatLoss]);

        $query = 'q=lifter&goal=strength&experience=advanced&platform=android&signin=password&sort=signup';
        $first = $this->asAdmin("/admin/users?{$query}");
        $paginator = $first->viewData('users');
        $this->assertSame(30, $paginator->total());
        foreach (explode('&', $query) as $pair) {
            $this->assertStringContainsString($pair, $paginator->nextPageUrl());
        }
        $first->assertSeeInOrder(['Lifter 30', 'Lifter 29']);

        $second = $this->asAdmin($paginator->nextPageUrl());
        $this->assertSame(2, $second->viewData('users')->currentPage());
        $second->assertSee('Lifter 5')->assertDontSee('Lifter 6<')->assertDontSee('Runner Rex');

        $this->asAdmin("/admin/users?{$query}")->assertSeeInOrder(['Lifter 30', 'Lifter 29']);
    }

    public function test_the_form_keeps_every_chosen_value(): void
    {
        $this->asAdmin('/admin/users?q=ada&goal=strength&experience=advanced&platform=ios&signin=social&stuck=1&deleted=1&sort=-last_session')
            ->assertSee('value="ada"', false)
            ->assertSee('value="strength" selected', false)
            ->assertSee('value="advanced" selected', false)
            ->assertSee('value="ios" selected', false)
            ->assertSee('value="social" selected', false)
            ->assertSee('name="stuck" value="1" checked', false)
            ->assertSee('name="deleted" value="1" checked', false)
            ->assertSee('value="-last_session" selected', false);
    }

    public function test_unknown_filter_values_are_ignored_rather_than_an_error(): void
    {
        $this->member(['name' => 'Ada Lovelace']);

        $this->asAdmin('/admin/users?goal=bogus&experience=bogus&platform=windows&signin=carrier-pigeon&sort=bogus')
            ->assertSee('Ada Lovelace');
    }

    private function asAdmin(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('admin'))->get($uri)->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $profile  overrides on the factory's profile
     * @param  list<int>  $completedDaysAgo
     */
    private function member(array $attributes = [], array $profile = [], array $completedDaysAgo = []): User
    {
        $user = User::factory()->create([
            'partner_id' => $this->gym->id,
            'onboarding_completed_at' => now()->subDays(60),
            ...$attributes,
        ]);

        if ($profile !== []) {
            $user->profile->update($profile);
        }

        foreach ($completedDaysAgo as $d) {
            $this->workoutSession($user, WorkoutSessionStatus::Completed, now()->subDays($d));
        }

        return $user;
    }

    private function workoutSession(User $user, WorkoutSessionStatus $status, \DateTimeInterface $at): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => $status,
            'performed_at' => $at,
            'completed_at' => $status === WorkoutSessionStatus::Completed ? $at : null,
        ]);
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
