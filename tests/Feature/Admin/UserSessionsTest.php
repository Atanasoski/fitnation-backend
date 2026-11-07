<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WorkoutSessionStatus;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The per-user sessions list: every workout session of one user, paginated,
 * filterable by status and by Stuck (a predicate on active, not a status).
 */
class UserSessionsTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
    }

    public function test_only_an_admin_may_open_the_sessions_list(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions")
            ->assertOk();

        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $gym->id]))
            ->get("/admin/users/{$member->id}/sessions")
            ->assertForbidden();

        $this->actingAs($this->member($gym))
            ->get("/admin/users/{$member->id}/sessions")
            ->assertForbidden();
    }

    public function test_staff_accounts_have_no_sessions_list(): void
    {
        $staff = $this->userWithRole('admin');

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$staff->id}/sessions")
            ->assertNotFound();
    }

    public function test_lists_every_session_newest_first_linking_to_each(): void
    {
        $member = $this->member(Partner::factory()->create());
        $old = $this->workout($member, 'Leg Day', WorkoutSessionStatus::Completed, daysAgo: 9);
        $new = $this->workout($member, 'Push Day', WorkoutSessionStatus::Completed, daysAgo: 1);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions")
            ->assertOk()
            ->assertSeeInOrder(['Push Day', 'Leg Day'])
            ->assertSee('href="'.e(route('admin.users.sessions.show', [$member, $new])).'"', false)
            ->assertSee('href="'.e(route('admin.users.sessions.show', [$member, $old])).'"', false);
    }

    public function test_a_stuck_session_is_badged_stuck(): void
    {
        $member = $this->member(Partner::factory()->create());
        $this->workout($member, 'Pull Day', WorkoutSessionStatus::Active, daysAgo: 2);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}/sessions")
            ->assertOk()
            ->assertSee('Stuck');
    }

    public function test_filters_by_status_and_by_stuck(): void
    {
        $member = $this->member(Partner::factory()->create());
        $this->workout($member, 'Done Day', WorkoutSessionStatus::Completed, daysAgo: 1);
        $this->workout($member, 'Stuck Day', WorkoutSessionStatus::Active, daysAgo: 3);
        $this->workout($member, 'Fresh Day', WorkoutSessionStatus::Active, hoursAgo: 2);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/sessions?status=completed")
            ->assertOk()
            ->assertSee('Done Day')
            ->assertDontSee('Stuck Day')
            ->assertDontSee('Fresh Day');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/sessions?status=active")
            ->assertOk()
            ->assertSee('Stuck Day')
            ->assertSee('Fresh Day')
            ->assertDontSee('Done Day');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/sessions?status=stuck")
            ->assertOk()
            ->assertSee('Stuck Day')
            ->assertDontSee('Fresh Day')
            ->assertDontSee('Done Day');
    }

    public function test_paginates_past_the_first_page(): void
    {
        $member = $this->member(Partner::factory()->create());
        $oldest = $this->workout($member, 'Oldest Day', WorkoutSessionStatus::Completed, daysAgo: 30);
        foreach (range(1, 25) as $i) {
            $this->workout($member, "Day {$i}", WorkoutSessionStatus::Completed, hoursAgo: $i);
        }

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/sessions")
            ->assertOk()
            ->assertDontSee('Oldest Day');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/sessions?page=2")
            ->assertOk()
            ->assertSee('Oldest Day');
    }

    public function test_the_user_page_links_to_the_full_list(): void
    {
        $member = $this->member(Partner::factory()->create());

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('href="'.e(route('admin.users.sessions.index', $member)).'"', false);
    }

    public function test_the_user_pages_recent_sessions_link_to_each_session(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->workout($member, 'Push Day', WorkoutSessionStatus::Completed, daysAgo: 1);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSee('href="'.e(route('admin.users.sessions.show', [$member, $session])).'"', false);
    }

    private function workout(User $user, string $templateName, WorkoutSessionStatus $status, int $daysAgo = 0, int $hoursAgo = 0): WorkoutSession
    {
        $performedAt = now()->subDays($daysAgo)->subHours($hoursAgo);

        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->create([
                'plan_id' => Plan::factory()->create(['user_id' => $user->id, 'is_active' => false])->id,
                'name' => $templateName,
            ])->id,
            'status' => $status,
            'performed_at' => $performedAt,
            'completed_at' => $status === WorkoutSessionStatus::Completed ? $performedAt->copy()->addHour() : null,
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
