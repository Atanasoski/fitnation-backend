<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComplimentaryAccessAndPartnerChangeTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
    }

    public function test_an_admin_grants_complimentary_access_until_a_date_with_a_reason(): void
    {
        $admin = $this->userWithRole('admin', ['name' => 'Grace Hopper']);
        $member = $this->member(Partner::factory()->create());

        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/complimentary-access", ['until' => '2026-12-01', 'reason' => 'Stuck at the paywall'])
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success');

        $this->assertSame('2026-12-01 23:59:59', $member->fresh()->grace_period_ends_at->format('Y-m-d H:i:s'));

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder([
                'Access Source', 'Complimentary', 'Complimentary until 1 Dec 2026 · granted by Grace Hopper',
                'Grants &amp; partner changes', 'Complimentary Access', 'by Grace Hopper', '5 Oct 2026', 'until 1 Dec 2026', 'Stuck at the paywall',
            ], escape: false);
    }

    public function test_granting_needs_a_future_date_and_a_reason(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->member(Partner::factory()->create());

        foreach ([
            'past date' => [['until' => '2026-10-01', 'reason' => 'Help'], 'until'],
            'today' => [['until' => '2026-10-05', 'reason' => 'Help'], 'until'],
            'no date' => [['reason' => 'Help'], 'until'],
            'not a date' => [['until' => 'soon', 'reason' => 'Help'], 'until'],
            'no reason' => [['until' => '2026-12-01'], 'reason'],
            'blank reason' => [['until' => '2026-12-01', 'reason' => '  '], 'reason'],
        ] as $case => [$input, $field]) {
            $this->actingAs($admin)
                ->from("/admin/users/{$member->id}")
                ->post("/admin/users/{$member->id}/complimentary-access", $input)
                ->assertRedirect("/admin/users/{$member->id}")
                ->assertSessionHasErrors($field);

            $this->assertNull($member->fresh()->grace_period_ends_at, $case);
        }

        $this->assertDatabaseCount('admin_changes', 0);
    }

    public function test_granting_again_extends_and_keeps_both_records(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->member(Partner::factory()->create(), ['grace_period_ends_at' => now()->addDays(3)]);

        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/complimentary-access", ['until' => '2026-10-20', 'reason' => 'First'])
            ->assertSessionHas('success');
        $this->travel(1)->days();
        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/complimentary-access", ['until' => '2027-01-31', 'reason' => 'Extended'])
            ->assertSessionHas('success');

        $this->assertSame('2027-01-31', $member->fresh()->grace_period_ends_at->toDateString());

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder(['Complimentary until 31 Jan 2027', 'Grants', 'until 31 Jan 2027', 'Extended', 'until 20 Oct 2026', 'First']);
    }

    public function test_a_grant_over_a_running_signup_trial_becomes_complimentary(): void
    {
        config(['subscriptions.signup_trial_days' => 7]);
        $admin = $this->userWithRole('admin', ['name' => 'Grace Hopper']);
        $member = $this->member(Partner::factory()->create());
        $member->startSignupTrial();

        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.subscription.access_source', 'signup_trial')
            ->assertJsonPath('user.subscription.free_access_kind', 'signup_trial');
        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder(['Access Source', 'Signup Trial', 'Signup Trial ends 12 Oct 2026', 'Grant Complimentary Access']);

        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/complimentary-access", ['until' => '2026-12-01', 'reason' => 'App Review account'])
            ->assertSessionHas('success');

        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.subscription.access_source', 'complimentary')
            ->assertJsonPath('user.subscription.free_access_kind', 'complimentary')
            ->assertJsonPath('user.subscription.grace_period_ends_at', Carbon::parse('2026-12-01 23:59:59')->toJSON());
        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder(['Access Source', 'Complimentary', 'Complimentary until 1 Dec 2026 · granted by Grace Hopper', 'Extend Complimentary Access']);
    }

    public function test_ending_complimentary_access_does_not_open_a_signup_trial(): void
    {
        config(['subscriptions.signup_trial_days' => 7]);
        $admin = $this->userWithRole('admin');
        $member = $this->member(Partner::factory()->create());

        $this->actingAs($admin)->post("/admin/users/{$member->id}/complimentary-access", ['until' => '2026-12-01', 'reason' => 'Early access']);
        $this->actingAs($admin)->delete("/admin/users/{$member->id}/complimentary-access", ['reason' => 'Done']);

        $this->assertFalse($member->fresh()->startSignupTrial());
        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.subscription.access_source', 'none')
            ->assertJsonPath('user.subscription.free_access_kind', null)
            ->assertJsonPath('user.subscription.grace_period_ends_at', null);
    }

    public function test_an_admin_ends_complimentary_access_now(): void
    {
        $admin = $this->userWithRole('admin', ['name' => 'Grace Hopper']);
        $member = $this->member(Partner::factory()->create(), ['grace_period_ends_at' => now()->addMonth()]);

        $this->actingAs($admin)
            ->delete("/admin/users/{$member->id}/complimentary-access", ['reason' => 'Paid after all'])
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success');

        $this->assertFalse($member->fresh()->hasComplimentaryAccess());

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder(['Access Source', 'None', 'Grants', 'Complimentary Access', 'by Grace Hopper', 'ended', 'Paid after all']);
    }

    public function test_an_admin_ends_a_running_signup_trial_now(): void
    {
        config(['subscriptions.signup_trial_days' => 7]);
        $admin = $this->userWithRole('admin', ['name' => 'Grace Hopper']);
        $member = $this->member(Partner::factory()->create());
        $member->startSignupTrial();

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder(['Access Source', 'Signup Trial', 'End Signup Trial now']);

        $this->actingAs($admin)
            ->delete("/admin/users/{$member->id}/signup-trial", ['reason' => 'Abusing trials'])
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success', 'Signup Trial ended.');

        $this->assertFalse($member->fresh()->startSignupTrial());
        $this->actingAs($member->fresh(), 'sanctum')
            ->getJson('/api/user')
            ->assertJsonPath('user.subscription.access_source', 'none')
            ->assertJsonPath('user.subscription.grace_period_ends_at', null);
        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder(['Access Source', 'None', 'Grants', 'Signup Trial', 'by Grace Hopper', 'ended', 'Abusing trials'])
            ->assertDontSee('End Signup Trial now');
    }

    public function test_only_a_running_signup_trial_can_be_ended_as_one(): void
    {
        $admin = $this->userWithRole('admin');
        $complimentary = $this->member(Partner::factory()->create(), ['grace_period_ends_at' => now()->addMonth()]);

        $this->actingAs($admin)
            ->delete("/admin/users/{$complimentary->id}/signup-trial")
            ->assertRedirect("/admin/users/{$complimentary->id}")
            ->assertSessionHasErrors('signup_trial');

        $this->assertTrue($complimentary->fresh()->hasComplimentaryAccess());
        $this->assertDatabaseCount('admin_changes', 0);
    }

    public function test_an_admin_moves_a_user_to_another_partner_with_a_reason(): void
    {
        $admin = $this->userWithRole('admin', ['name' => 'Grace Hopper']);
        $house = Partner::factory()->create(['name' => 'Fit Nation']);
        $gym = Partner::factory()->sponsor()->create(['name' => 'Iron Gym']);
        $member = $this->member($house);

        $this->actingAs($admin)
            ->patch("/admin/users/{$member->id}/partner", ['partner_id' => $gym->id, 'reason' => 'Joined the gym'])
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success');

        $this->assertSame($gym->id, $member->fresh()->partner_id);

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSeeInOrder([
                'Access Source', 'Sponsored', 'Iron Gym pays',
                'Partner', 'Iron Gym', 'Sponsoring Partner',
                'Grants', 'Partner change', 'by Grace Hopper', 'Fit Nation → Iron Gym', 'Joined the gym',
            ]);
    }

    public function test_the_target_partner_must_be_active_and_a_reason_given(): void
    {
        $admin = $this->userWithRole('admin');
        $house = Partner::factory()->create();
        $gym = Partner::factory()->create();
        $closed = Partner::factory()->inactive()->create();
        $member = $this->member($house);

        foreach ([
            'inactive partner' => [['partner_id' => $closed->id, 'reason' => 'Move'], 'partner_id'],
            'unknown partner' => [['partner_id' => 999999, 'reason' => 'Move'], 'partner_id'],
            'no partner' => [['reason' => 'Move'], 'partner_id'],
            'same partner' => [['partner_id' => $house->id, 'reason' => 'Move'], 'partner_id'],
            'no reason' => [['partner_id' => $gym->id], 'reason'],
        ] as $case => [$input, $field]) {
            $this->actingAs($admin)
                ->from("/admin/users/{$member->id}")
                ->patch("/admin/users/{$member->id}/partner", $input)
                ->assertRedirect("/admin/users/{$member->id}")
                ->assertSessionHasErrors($field);

            $this->assertSame($house->id, $member->fresh()->partner_id, $case);
        }

        $this->assertDatabaseCount('admin_changes', 0);
    }

    public function test_only_an_admin_may_change_access_or_partner(): void
    {
        $gym = Partner::factory()->create();
        $other = Partner::factory()->create();
        $member = $this->member($gym);

        foreach ([
            $this->userWithRole('partner_admin', ['partner_id' => $gym->id]),
            $this->member($gym),
        ] as $notAdmin) {
            $this->actingAs($notAdmin)
                ->post("/admin/users/{$member->id}/complimentary-access", ['until' => '2026-12-01', 'reason' => 'x'])
                ->assertForbidden();
            $this->actingAs($notAdmin)
                ->delete("/admin/users/{$member->id}/complimentary-access")
                ->assertForbidden();
            $this->actingAs($notAdmin)
                ->delete("/admin/users/{$member->id}/signup-trial")
                ->assertForbidden();
            $this->actingAs($notAdmin)
                ->patch("/admin/users/{$member->id}/partner", ['partner_id' => $other->id, 'reason' => 'x'])
                ->assertForbidden();
        }

        $this->assertNull($member->fresh()->grace_period_ends_at);
        $this->assertSame($gym->id, $member->fresh()->partner_id);
        $this->assertDatabaseCount('admin_changes', 0);
    }

    public function test_staff_accounts_are_not_users_and_cannot_be_changed(): void
    {
        $admin = $this->userWithRole('admin');
        $gym = Partner::factory()->create();
        $owner = $this->userWithRole('partner_admin', ['partner_id' => $gym->id]);

        $this->actingAs($admin)
            ->post("/admin/users/{$owner->id}/complimentary-access", ['until' => '2026-12-01', 'reason' => 'x'])
            ->assertNotFound();
        $this->actingAs($admin)
            ->patch("/admin/users/{$owner->id}/partner", ['partner_id' => Partner::factory()->create()->id, 'reason' => 'x'])
            ->assertNotFound();

        $this->assertDatabaseCount('admin_changes', 0);
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
