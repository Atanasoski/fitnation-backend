<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PartnerPlan;
use App\Enums\WorkoutSessionStatus;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * The Overview page (ticket 11): the module's numbers appear, and every
 * number that names people opens the Users list with the matching filter.
 * The counts themselves are OverviewTest's job.
 */
class OverviewPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_the_overview_shows_the_kpis_funnel_paywall_and_needs_attention_numbers(): void
    {
        // 7 users signed up this week (and never verified): KPI, funnel,
        // None and Unfinished all read 7.
        User::factory()->count(7)->create(['created_at' => '2026-10-06 10:00:00', 'email_verified_at' => null]);
        // 3 failed jobs, 4 users with a Stuck Session, 5 sponsorships running out.
        foreach (range(1, 3) as $_) {
            app('queue.failer')->log('database', 'default', json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'SomeJob']), new RuntimeException('boom'));
        }
        User::factory()->count(4)->create(['created_at' => '2026-08-01 10:00:00', 'onboarding_completed_at' => '2026-08-01 10:00:00'])
            ->each(fn (User $user) => $this->stuckSession($user));
        Partner::factory()->count(5)->create(['plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-10-20 00:00:00']);

        $this->asAdmin()
            ->assertOk()
            ->assertSeeInOrder(['Users', '11', 'Signups', '7'])
            ->assertSee('Activation funnel')
            ->assertSee('Subscriptions enforced')
            ->assertSeeInOrder(['Failed jobs', '3'])
            ->assertSeeInOrder(['Stuck sessions', '4'])
            ->assertSeeInOrder(['Sponsorships expiring', '5']);
    }

    public function test_every_number_that_names_people_opens_the_filtered_users_list(): void
    {
        $html = $this->asAdmin()->assertOk()->getContent();

        foreach ([
            route('admin.users.index', ['access' => 'none']),
            route('admin.users.index', ['activity' => 'unfinished']),
            route('admin.users.index', ['stuck' => 1]),
            route('admin.users.index'),
            route('admin.system'),
        ] as $url) {
            $this->assertStringContainsString('href="'.e($url).'"', $html, "Missing link to {$url}");
        }
    }

    public function test_the_overview_is_for_admins_only(): void
    {
        $this->actingAs($this->userWithRole('partner_admin'))->get('/admin')->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get('/admin')->assertForbidden();
    }

    private function asAdmin(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->userWithRole('admin'))->get('/admin');
    }

    private function stuckSession(User $user): void
    {
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => WorkoutSessionStatus::Active,
            'performed_at' => '2026-10-05 10:00:00',
            'completed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
