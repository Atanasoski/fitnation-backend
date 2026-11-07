<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminChangeKind;
use App\Enums\WorkoutSessionStatus;
use App\Models\AdminChange;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The super admin cancelling a Stuck Session from the session page: the same
 * transition as the athlete's own cancel (status → cancelled, set logs kept),
 * allowed only while the session is stuck, and recorded as an Admin Change.
 */
class CancelStuckSessionTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
    }

    public function test_an_admin_cancels_a_stuck_session_with_a_reason(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->stuckSession($member);
        $set = SetLog::query()->create([
            'workout_session_id' => $session->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'set_number' => 1,
            'weight' => 100,
            'reps' => 8,
        ]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.users.sessions.show', [$member, $session]))
            ->post(route('admin.users.sessions.cancel', [$member, $session]), ['reason' => 'App lost the session'])
            ->assertRedirect(route('admin.users.sessions.show', [$member, $session]))
            ->assertSessionHas('success');

        $this->assertSame(WorkoutSessionStatus::Cancelled, $session->fresh()->status);
        $this->assertNull($session->fresh()->completed_at);
        $this->assertDatabaseHas('workout_session_set_logs', ['id' => $set->id]);
        $this->assertDatabaseHas('admin_changes', [
            'user_id' => $member->id,
            'admin_id' => $admin->id,
            'kind' => AdminChangeKind::SessionCancel->value,
            'workout_session_id' => $session->id,
            'reason' => 'App lost the session',
        ]);
    }

    public function test_the_reason_is_optional(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->stuckSession($member);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.users.sessions.cancel', [$member, $session]))
            ->assertRedirect();

        $this->assertSame(WorkoutSessionStatus::Cancelled, $session->fresh()->status);
        $this->assertDatabaseHas('admin_changes', [
            'workout_session_id' => $session->id,
            'reason' => null,
        ]);
    }

    public function test_a_session_that_is_not_stuck_cannot_be_cancelled(): void
    {
        $member = $this->member(Partner::factory()->create());
        $admin = $this->userWithRole('admin');

        $fresh = $this->makeSession($member, WorkoutSessionStatus::Active, hoursAgo: 2);
        $completed = $this->makeSession($member, WorkoutSessionStatus::Completed, hoursAgo: 48);
        $cancelled = $this->makeSession($member, WorkoutSessionStatus::Cancelled, hoursAgo: 48);

        foreach ([$fresh, $completed, $cancelled] as $session) {
            $this->actingAs($admin)
                ->from(route('admin.users.sessions.show', [$member, $session]))
                ->post(route('admin.users.sessions.cancel', [$member, $session]))
                ->assertRedirect(route('admin.users.sessions.show', [$member, $session]))
                ->assertSessionHasErrors();

            $this->assertSame($session->status, $session->fresh()->status);
        }

        $this->assertSame(0, AdminChange::query()->count());
    }

    public function test_only_an_admin_may_cancel(): void
    {
        $gym = Partner::factory()->create();
        $member = $this->member($gym);
        $session = $this->stuckSession($member);

        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $gym->id]))
            ->post(route('admin.users.sessions.cancel', [$member, $session]))
            ->assertForbidden();

        $this->assertSame(WorkoutSessionStatus::Active, $session->fresh()->status);
    }

    public function test_the_session_page_offers_cancel_only_while_stuck(): void
    {
        $member = $this->member(Partner::factory()->create());
        $admin = $this->userWithRole('admin');

        $stuck = $this->stuckSession($member);
        $this->actingAs($admin)
            ->get(route('admin.users.sessions.show', [$member, $stuck]))
            ->assertOk()
            ->assertSee('Cancel this session');

        $fresh = $this->makeSession($member, WorkoutSessionStatus::Active, hoursAgo: 2);
        $this->actingAs($admin)
            ->get(route('admin.users.sessions.show', [$member, $fresh]))
            ->assertOk()
            ->assertDontSee('Cancel this session');
    }

    public function test_the_cancel_appears_in_the_user_pages_admin_changes(): void
    {
        $member = $this->member(Partner::factory()->create());
        $session = $this->stuckSession($member);
        $admin = $this->userWithRole('admin', ['name' => 'Grace Hopper']);

        $this->actingAs($admin)
            ->post(route('admin.users.sessions.cancel', [$member, $session]), ['reason' => 'Stuck for two days']);

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder(['Admin changes', 'Session cancelled', 'by Grace Hopper', '5 Oct 2026', 'Stuck for two days'])
            ->assertSee('href="'.e(route('admin.users.sessions.show', [$member, $session])).'"', false);
    }

    private function stuckSession(User $user): WorkoutSession
    {
        return $this->makeSession($user, WorkoutSessionStatus::Active, hoursAgo: 48);
    }

    private function makeSession(User $user, WorkoutSessionStatus $status, int $hoursAgo): WorkoutSession
    {
        $performedAt = now()->subHours($hoursAgo);

        return WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->create([
                'plan_id' => Plan::factory()->create(['user_id' => $user->id, 'is_active' => false])->id,
                'name' => 'Push Day',
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
