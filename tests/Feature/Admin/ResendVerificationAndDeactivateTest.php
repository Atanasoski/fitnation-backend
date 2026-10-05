<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResendVerificationAndDeactivateTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
    }

    public function test_an_admin_resends_the_verification_email_to_an_unverified_user(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $member = $this->member(['email_verified_at' => null, 'onboarding_completed_at' => null]);

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSee('Resend verification');

        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/verification")
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success');

        Notification::assertSentToTimes($member, VerifyEmail::class, 1);
    }

    public function test_a_verified_user_is_not_offered_and_does_not_get_a_verification_email(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $member = $this->member(['email_verified_at' => now()->subDay(), 'onboarding_completed_at' => null]);

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertDontSee('Resend verification');

        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/verification")
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHasErrors('verification')
            ->assertSessionMissing('success');

        Notification::assertNothingSent();
    }

    public function test_an_admin_deactivates_a_user_who_then_shows_as_deleted_and_restores_them(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->member(['name' => 'Ada Lovelace']);
        $member->createToken('auth-token');

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSee('Deactivate user')
            ->assertDontSee('Restore user');

        $this->actingAs($admin)
            ->delete("/admin/users/{$member->id}")
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success');

        $this->assertSoftDeleted($member);
        // Signed out everywhere, which also ends their Devices (ADR-0003).
        $this->assertSame(0, $member->tokens()->count());

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertOk()
            ->assertSeeInOrder(['Activity Status', 'Deleted'])
            ->assertSee('Restore user')
            ->assertDontSee('Deactivate user');
        $this->actingAs($admin)->get('/admin/users')->assertDontSee('Ada Lovelace');
        $this->actingAs($admin)->get('/admin/users?deleted=1')->assertSee('Ada Lovelace');

        $this->actingAs($admin)
            ->post("/admin/users/{$member->id}/restore")
            ->assertRedirect("/admin/users/{$member->id}")
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($member);
        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}")
            ->assertSee('Deactivate user')
            ->assertDontSee('Restore user');
        $this->actingAs($admin)->get('/admin/users')->assertSee('Ada Lovelace');
    }

    public function test_only_an_admin_may_resend_deactivate_or_restore(): void
    {
        Notification::fake();
        $gym = Partner::factory()->create();
        $unverified = $this->member(['partner_id' => $gym->id, 'email_verified_at' => null]);
        $live = $this->member(['partner_id' => $gym->id]);
        $deleted = $this->member(['partner_id' => $gym->id]);
        $deleted->delete();

        foreach ([
            $this->userWithRole('partner_admin', ['partner_id' => $gym->id]),
            $this->member(['partner_id' => $gym->id]),
        ] as $notAdmin) {
            $this->actingAs($notAdmin)->post("/admin/users/{$unverified->id}/verification")->assertForbidden();
            $this->actingAs($notAdmin)->delete("/admin/users/{$live->id}")->assertForbidden();
            $this->actingAs($notAdmin)->post("/admin/users/{$deleted->id}/restore")->assertForbidden();
        }

        Notification::assertNothingSent();
        $this->assertNotSoftDeleted($live);
        $this->assertSoftDeleted($deleted);
    }

    public function test_staff_accounts_cannot_be_deactivated(): void
    {
        $admin = $this->userWithRole('admin');
        $otherAdmin = $this->userWithRole('admin');
        $owner = $this->userWithRole('partner_admin', ['partner_id' => Partner::factory()->create()->id]);

        $this->actingAs($admin)->delete("/admin/users/{$otherAdmin->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/users/{$owner->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertNotFound();

        $this->assertNotSoftDeleted($otherAdmin);
        $this->assertNotSoftDeleted($owner);
        $this->assertNotSoftDeleted($admin);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create([
            'partner_id' => Partner::factory()->create()->id,
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
