<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\SubscriptionStore;
use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Models\Device;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Notifications\InactivityNudge;
use App\Notifications\UnfinishedAccountNudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserPageTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
    }

    public function test_only_an_admin_may_open_a_user_page(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym, ['name' => 'Ada Lovelace']);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('Ada Lovelace');

        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $gym->id]))
            ->get("/admin/users/{$member->id}")
            ->assertForbidden();

        $this->actingAs($this->member($gym))
            ->get("/admin/users/{$member->id}")
            ->assertForbidden();
    }

    public function test_admin_and_partner_admin_accounts_are_not_viewable_as_users(): void
    {
        $gym = Partner::factory()->create();
        $owner = $this->userWithRole('partner_admin', ['partner_id' => $gym->id]);
        $otherAdmin = $this->userWithRole('admin');
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get("/admin/users/{$owner->id}")->assertNotFound();
        $this->actingAs($admin)->get("/admin/users/{$otherAdmin->id}")->assertNotFound();
        $this->actingAs($admin)->get('/admin/users/999999')->assertNotFound();
    }

    public function test_a_deleted_user_still_has_a_page(): void
    {
        $member = $this->member(Partner::factory()->create(), ['name' => 'Ada Lovelace']);
        $member->delete();

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('Deleted');
    }

    public function test_the_strip_for_a_subscribed_house_member_on_a_program(): void
    {
        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $house->id]);
        $member = $this->member($house);
        Subscription::factory()->yearly()->create([
            'user_id' => $member->id,
            'product_id' => 'com.fitnation.app.premium.yearly:yearly',
            'store' => SubscriptionStore::PlayStore,
            'expires_at' => Carbon::parse('2027-03-12 09:00:00'),
        ]);
        $program = Plan::factory()->program()->create(['user_id' => $member->id, 'name' => 'Hypertrophy 4-day', 'is_active' => true, 'duration_weeks' => 8]);
        $week1 = $this->templates($program, week: 1, names: ['Upper', 'Lower']);
        $this->templates($program, week: 2, names: ['Upper', 'Lower']);
        foreach ($week1 as $template) {
            $this->completedSession($member, $template, daysAgo: 2);
        }

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Activity Status', 'Active',
                'Access Source', 'Subscribed', 'Yearly · Google Play · renews 12 Mar 2027',
                'Partner', 'Fit Nation', 'House Partner',
                'Active plan', 'Hypertrophy 4-day', 'Upper / Lower · week 2 of 8',
            ]);
    }

    public function test_the_strip_for_a_member_of_a_sponsoring_partner_on_a_routine(): void
    {
        $gym = Partner::factory()->sponsor()->create(['name' => 'Iron Temple', 'plan_expires_at' => Carbon::parse('2027-01-31 00:00:00')]);
        $member = $this->member($gym, ['onboarding_completed_at' => now()->subDays(3)]);
        $routine = Plan::factory()->create(['user_id' => $member->id, 'name' => 'Saturday Mobility', 'is_active' => true]);
        $this->templates($routine, week: 1, names: ['Hips', 'Shoulders']);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Activity Status', 'New',
                'Access Source', 'Sponsored', 'Iron Temple pays · sponsorship until 31 Jan 2027',
                'Partner', 'Iron Temple', 'Sponsoring Partner',
                'Active plan', 'Saturday Mobility', 'Routine · Hips / Shoulders',
            ]);
    }

    public function test_the_strip_for_a_complimentary_member_of_a_plain_partner_with_no_plan(): void
    {
        $gym = Partner::factory()->create(['name' => 'Flow Studio']);
        $member = $this->member($gym, ['grace_period_ends_at' => Carbon::parse('2026-12-01 00:00:00')]);
        $this->completedSession($member, null, daysAgo: 10);
        Plan::factory()->create(['user_id' => $member->id, 'name' => 'Old Plan', 'is_active' => false]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Activity Status', 'Slipping',
                'Access Source', 'Complimentary', 'Complimentary until 1 Dec 2026',
                'Partner', 'Flow Studio', 'Partner',
                'Active plan', 'No active plan',
            ]);
    }

    public function test_the_strip_for_a_cancelled_member_with_no_access_left_afterwards(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym);
        Subscription::factory()->cancelled()->create([
            'user_id' => $member->id,
            'expires_at' => Carbon::parse('2026-11-03 09:00:00'),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Activity Status', 'Inactive',
                'Access Source', 'Cancelled, paid until', 'Monthly · App Store · cancelled 4 Oct 2026 · paid until 3 Nov 2026',
            ]);
    }

    public function test_the_profile_is_shown_in_an_imperial_users_unit_system(): void
    {
        $member = $this->member(Partner::factory()->create());
        $member->profile->update([
            'unit_system' => UnitSystem::Imperial,
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
            'gender' => Gender::Female,
            'age' => 34,
            'height' => 178,
            'weight' => 82.3,
            'training_days_per_week' => 4,
            'workout_duration_minutes' => 60,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Profile',
                'Goal', 'Muscle gain',
                'Experience', 'Intermediate',
                'Gender', 'Female',
                'Age', '34',
                'Height', '5 ft 10 in',
                'Weight', '181.5 lbs',
                'Training days', '4 per week',
                'Workout duration', '60 min',
                'Unit System', 'Imperial',
            ])
            ->assertDontSee('82.3 kg')
            ->assertDontSee('178 cm');
    }

    public function test_the_profile_is_shown_in_a_metric_users_unit_system(): void
    {
        $member = $this->member(Partner::factory()->create());
        $member->profile->update(['unit_system' => UnitSystem::Metric, 'height' => 178, 'weight' => 82.3]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder(['Height', '178 cm', 'Weight', '82.3 kg', 'Unit System', 'Metric']);
    }

    public function test_a_user_without_a_profile_says_so(): void
    {
        $member = $this->member(Partner::factory()->create(), ['onboarding_completed_at' => null]);
        $member->profile()->delete();

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder(['Profile', 'No profile yet']);
    }

    public function test_recent_sessions_show_status_date_and_duration_with_stuck_ones_flagged(): void
    {
        $member = $this->member(Partner::factory()->create());
        $plan = Plan::factory()->create(['user_id' => $member->id]);
        [$upper, $push, $legs] = $this->templates($plan, week: 1, names: ['Upper', 'Push', 'Legs']);
        WorkoutSession::factory()->create([
            'user_id' => $member->id, 'workout_template_id' => $upper->id, 'status' => WorkoutSessionStatus::Completed,
            'performed_at' => Carbon::parse('2026-10-03 18:00:00'), 'completed_at' => Carbon::parse('2026-10-03 18:55:00'),
        ]);
        WorkoutSession::factory()->create([
            'user_id' => $member->id, 'workout_template_id' => $push->id, 'status' => WorkoutSessionStatus::Active,
            'performed_at' => now()->subHours(30), 'completed_at' => null,
        ]);
        WorkoutSession::factory()->create([
            'user_id' => $member->id, 'workout_template_id' => $legs->id, 'status' => WorkoutSessionStatus::Active,
            'performed_at' => now()->subHours(2), 'completed_at' => null,
        ]);

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Recent sessions',
                '5 Oct 2026', 'Legs', 'In Progress',
                '4 Oct 2026', 'Push', 'Stuck',
                '3 Oct 2026', 'Upper', '55 min', 'Completed',
            ]);

        $this->assertSame(1, substr_count($response->getContent(), 'Stuck'), 'only the session active for over 24 hours is flagged');
    }

    public function test_best_sets_are_the_best_completed_set_per_exercise_in_the_users_unit_system(): void
    {
        $member = $this->member(Partner::factory()->create());
        $member->profile->update(['unit_system' => UnitSystem::Imperial]);
        $bench = Exercise::factory()->create(['name' => 'Bench Press']);
        $older = $this->completedSession($member, null, daysAgo: 9);
        $newer = $this->completedSession($member, null, daysAgo: 2);
        $inProgress = WorkoutSession::factory()->create([
            'user_id' => $member->id, 'workout_template_id' => $newer->workout_template_id,
            'status' => WorkoutSessionStatus::Active, 'performed_at' => now()->subHour(),
        ]);
        $this->set($older, $bench, kg: 100, reps: 5);
        $this->set($newer, $bench, kg: 90, reps: 10);
        $this->set($inProgress, $bench, kg: 150, reps: 10);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder(['Best sets', 'Bench Press', '200 lbs × 10', '3 Oct 2026'])
            ->assertDontSee('330 lbs');
    }

    public function test_devices_show_platform_app_version_timezone_last_seen_and_push(): void
    {
        $member = $this->member(Partner::factory()->create(), ['push_enabled' => false]);
        Device::factory()->create([
            'user_id' => $member->id, 'platform' => 'ios', 'device_name' => 'iPhone 15',
            'app_version' => '1.4.2', 'timezone' => 'Europe/Skopje', 'last_seen_at' => Carbon::parse('2026-10-02 08:30:00'),
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder(['Devices', 'iOS', 'iPhone 15', 'App 1.4.2', 'Europe/Skopje', 'seen 2 Oct 2026', 'push off']);
    }

    public function test_recent_sent_records_show_kind_and_when(): void
    {
        $member = $this->member(Partner::factory()->create());
        $this->travelTo('2026-09-20 18:00:00');
        $member->notifications()->create(['id' => (string) Str::uuid(), 'type' => UnfinishedAccountNudge::class, 'data' => ['step' => 1, 'title' => 'Finish setting up']]);
        $this->travelTo('2026-10-01 18:00:00');
        $member->notifications()->create(['id' => (string) Str::uuid(), 'type' => InactivityNudge::class, 'data' => ['step' => 7, 'title' => 'Ready for another one?']]);
        $this->travelTo(self::NOW);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Sent Records',
                'Inactivity Nudge', 'Ready for another one?', '1 Oct 2026',
                'Unfinished Account Nudge', 'Finish setting up', '20 Sep 2026',
            ]);
    }

    /**
     * Invitations are gone (spec 025 ticket 03): the page for a member a
     * partner admin once invited renders, with no Invitation section.
     */
    public function test_the_page_of_a_member_who_was_invited_renders_without_an_invitation_section(): void
    {
        $gym = Partner::factory()->create(['name' => 'Iron Temple']);
        $this->userWithRole('partner_admin', ['name' => 'Gym Owner', 'partner_id' => $gym->id]);
        $member = $this->member($gym, ['email' => 'ada@example.com']);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('ada@example.com')
            ->assertDontSee('Invitation')
            ->assertDontSee('Invited by');
    }

    public function test_the_page_of_a_member_who_joined_on_their_own_renders_without_an_invitation_section(): void
    {
        $member = $this->member(Partner::factory()->create(), ['email' => 'solo@example.com']);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('solo@example.com')
            ->assertDontSee('Invitation')
            ->assertDontSee('Joined without an invitation');
    }

    public function test_the_back_link_returns_to_the_filtered_list_the_user_was_opened_from(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym, ['name' => 'Ada Lovelace']);
        $admin = $this->userWithRole('admin');
        $list = "/admin/users?partner={$gym->id}&activity=inactive&sort=signup";

        $rowLink = route('admin.users.show', ['user' => $member->id, 'back' => "partner={$gym->id}&activity=inactive&sort=signup"]);
        $this->actingAs($admin)->get($list)->assertOk()->assertSee('href="'.e($rowLink).'"', false);

        $this->actingAs($admin)
            ->get($rowLink)
            ->assertOk()
            ->assertSee('href="'.e(url($list)).'"', false);
    }

    public function test_without_an_originating_list_the_back_link_is_the_plain_list(): void
    {
        $member = $this->member(Partner::factory()->create());

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('href="'.e(route('admin.users.index')).'"', false);
    }

    public function test_the_plans_section_lists_every_plan_and_links_each_to_its_outline_node(): void
    {
        $member = $this->member(Partner::factory()->create());
        $program = Plan::factory()->program()->create(['user_id' => $member->id, 'name' => 'Hypertrophy 4-day', 'is_active' => true, 'duration_weeks' => 8]);
        $this->templates($program, week: 1, names: ['Upper', 'Lower']);
        $routine = Plan::factory()->create(['user_id' => $member->id, 'name' => 'Saturday Mobility', 'is_active' => false]);
        $this->templates($routine, week: 1, names: ['Hips']);
        $program->forceFill(['updated_at' => Carbon::parse('2026-10-03 09:00:00')])->save();
        $routine->forceFill(['updated_at' => Carbon::parse('2026-09-01 09:00:00')])->save();

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Open plan outline',
                'Hypertrophy 4-day', 'Program', 'Active', '2', '3 Oct 2026',
                'Saturday Mobility', 'Routine', 'Inactive', '1', '1 Sep 2026',
            ])
            ->assertSee('href="'.e(route('plans.index', $member)).'"', false)
            ->assertSee('href="'.e(route('plans.index', ['user' => $member->id, 'plan' => $program->id])).'"', false)
            ->assertSee('href="'.e(route('plans.index', ['user' => $member->id, 'plan' => $routine->id])).'"', false);
    }

    public function test_a_user_without_plans_says_so_and_still_links_to_the_outline(): void
    {
        $member = $this->member(Partner::factory()->create());

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('No plans yet.')
            ->assertSee('href="'.e(route('plans.index', $member)).'"', false);
    }

    public function test_the_active_plan_fact_links_to_its_outline_node(): void
    {
        $member = $this->member(Partner::factory()->create());
        $program = Plan::factory()->program()->create(['user_id' => $member->id, 'name' => 'Hypertrophy 4-day', 'is_active' => true, 'duration_weeks' => 8]);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder([
                'Active plan',
                'href="'.e(route('plans.index', ['user' => $member->id, 'plan' => $program->id])).'"',
                'Hypertrophy 4-day',
                'Recent sessions',
            ], false);
    }

    private function set(WorkoutSession $session, Exercise $exercise, float $kg, int $reps): SetLog
    {
        return SetLog::query()->create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => $kg,
            'reps' => $reps,
        ]);
    }

    /**
     * @param  list<string>  $names
     * @return list<WorkoutTemplate>
     */
    private function templates(Plan $plan, int $week, array $names): array
    {
        return array_map(fn (string $name, int $i) => WorkoutTemplate::factory()->create([
            'plan_id' => $plan->id,
            'name' => $name,
            'week_number' => $week,
            'order_index' => $i,
        ]), $names, array_keys($names));
    }

    private function completedSession(User $user, ?WorkoutTemplate $template, int $daysAgo): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => $template?->id ?? WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id, 'is_active' => false])]),
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => now()->subDays($daysAgo)->subHour(),
            'completed_at' => now()->subDays($daysAgo),
        ]);
    }

    private function member(Partner $partner, array $attributes = []): User
    {
        return User::factory()->create([
            'partner_id' => $partner->id,
            'onboarding_completed_at' => now()->subDays(60),
            ...$attributes,
        ]);
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
