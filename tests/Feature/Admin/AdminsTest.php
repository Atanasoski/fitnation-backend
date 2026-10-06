<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminRoleRefused;
use App\Services\Admin\Admins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_grants_the_super_admin_role_by_email(): void
    {
        $me = $this->userWithRole('admin');
        $colleague = User::factory()->create(['email' => 'ana@example.com']);

        $this->actingAs($me)
            ->post('/admin/system/admins', ['email' => 'ana@example.com', 'role' => 'admin'])
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $this->assertTrue($colleague->fresh()->hasRole('admin'));
    }

    public function test_granting_partner_admin_sets_the_users_partner(): void
    {
        $gym = Partner::factory()->create();
        $coach = User::factory()->create(['email' => 'coach@example.com']);

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/system/admins', ['email' => 'coach@example.com', 'role' => 'partner_admin', 'partner_id' => $gym->id])
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $coach->refresh();
        $this->assertTrue($coach->hasRole('partner_admin'));
        $this->assertSame($gym->id, $coach->partner_id);
    }

    public function test_partner_admin_needs_a_partner(): void
    {
        $coach = User::factory()->create(['email' => 'coach@example.com']);

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/system/admins', ['email' => 'coach@example.com', 'role' => 'partner_admin'])
            ->assertSessionHasErrors('partner_id');

        $this->assertFalse($coach->fresh()->hasRole('partner_admin'));
    }

    public function test_an_unknown_or_deactivated_email_is_a_validation_error(): void
    {
        User::factory()->create(['email' => 'gone@example.com'])->delete();

        foreach (['nobody@example.com', 'gone@example.com'] as $email) {
            $this->actingAs($this->userWithRole('admin'))
                ->post('/admin/system/admins', ['email' => $email, 'role' => 'admin'])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame(2, User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->count());
    }

    public function test_granting_a_role_the_user_already_has_changes_nothing(): void
    {
        $first = $this->userWithRole('partner_admin', ['email' => 'coach@example.com']);
        $partnerBefore = $first->partner_id;
        $other = Partner::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/system/admins', ['email' => 'coach@example.com', 'role' => 'partner_admin', 'partner_id' => $other->id])
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'already'));

        $this->assertSame($partnerBefore, $first->fresh()->partner_id);
        $this->assertSame(1, $first->roles()->count());
    }

    public function test_a_granted_user_leaves_the_app_users(): void
    {
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->assertTrue(User::appUsers()->whereKey($member->id)->exists());

        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/system/admins', ['email' => 'member@example.com', 'role' => 'admin']);

        $this->assertFalse(User::appUsers()->whereKey($member->id)->exists());
    }

    public function test_revoking_super_admin_removes_the_role(): void
    {
        $colleague = $this->userWithRole('admin');

        $this->actingAs($this->userWithRole('admin'))
            ->delete("/admin/system/admins/{$colleague->id}/admin")
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $this->assertFalse($colleague->fresh()->hasRole('admin'));
        $this->assertTrue(User::appUsers()->whereKey($colleague->id)->exists());
    }

    public function test_revoking_partner_admin_leaves_the_partner_as_it_is(): void
    {
        $gym = Partner::factory()->create();
        $coach = $this->userWithRole('partner_admin', ['partner_id' => $gym->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->delete("/admin/system/admins/{$coach->id}/partner_admin")
            ->assertRedirect(route('admin.system'))
            ->assertSessionHas('success');

        $coach->refresh();
        $this->assertFalse($coach->hasRole('partner_admin'));
        $this->assertSame($gym->id, $coach->partner_id);
    }

    public function test_you_cannot_revoke_your_own_super_admin_role(): void
    {
        $me = $this->userWithRole('admin');
        $this->userWithRole('admin');

        $this->actingAs($me)
            ->delete("/admin/system/admins/{$me->id}/admin")
            ->assertRedirect(route('admin.system'))
            ->assertSessionHasErrors('admins');

        $this->assertTrue($me->fresh()->hasRole('admin'));
    }

    public function test_the_last_super_admin_cannot_be_revoked(): void
    {
        // Through HTTP the acting super admin is always another one, so the
        // last-super-admin rule is held at the module.
        $only = $this->userWithRole('admin');
        $this->userWithRole('admin')->delete();

        try {
            Admins::revoke($only, 'admin', User::factory()->create());
            $this->fail('Revoking the last super admin was allowed.');
        } catch (AdminRoleRefused $refused) {
            $this->assertStringContainsString('last super admin', $refused->getMessage());
        }

        $this->assertTrue($only->fresh()->hasRole('admin'));
    }

    public function test_revoking_a_role_the_user_does_not_have_is_a_404(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete("/admin/system/admins/{$member->id}/admin")
            ->assertNotFound();
    }

    public function test_a_partner_admin_or_app_user_cannot_reach_the_endpoints(): void
    {
        $target = User::factory()->create(['email' => 'target@example.com']);
        $colleague = $this->userWithRole('admin');

        foreach ([$this->userWithRole('partner_admin'), User::factory()->create()] as $outsider) {
            $this->actingAs($outsider)
                ->post('/admin/system/admins', ['email' => 'target@example.com', 'role' => 'admin'])
                ->assertForbidden();
            $this->actingAs($outsider)
                ->delete("/admin/system/admins/{$colleague->id}/admin")
                ->assertForbidden();
        }

        $this->assertFalse($target->fresh()->hasRole('admin'));
        $this->assertTrue($colleague->fresh()->hasRole('admin'));
    }

    public function test_the_list_holds_every_super_admin_and_partner_admin_with_partner_and_since(): void
    {
        $this->travelTo('2026-09-01 10:00:00');
        $boss = $this->userWithRole('admin', ['name' => 'Boss']);
        $gym = Partner::factory()->create(['name' => 'Iron Gym']);
        $this->travelTo('2026-09-15 10:00:00');
        $coach = $this->userWithRole('partner_admin', ['name' => 'Coach', 'partner_id' => $gym->id]);
        User::factory()->create(['name' => 'Member']);
        $this->userWithRole('admin', ['name' => 'Gone'])->delete();

        $rows = collect(Admins::list())->map(fn (array $row) => [
            $row['user']->id, $row['role'], $row['partner']?->id, $row['since']->toDateString(),
        ])->all();

        $this->assertSame([
            [$boss->id, 'admin', null, '2026-09-01'],
            [$coach->id, 'partner_admin', $gym->id, '2026-09-15'],
        ], $rows);
    }

    public function test_the_system_page_shows_the_admins_section(): void
    {
        $me = $this->userWithRole('admin', ['name' => 'Boss', 'email' => 'boss@example.com']);
        $gym = Partner::factory()->create(['name' => 'Iron Gym']);
        $this->userWithRole('partner_admin', ['name' => 'Coach', 'partner_id' => $gym->id]);

        $this->actingAs($me)
            ->get('/admin/system')
            ->assertOk()
            ->assertSeeInOrder(['Devices and push', 'Admins', 'Boss', 'boss@example.com', 'Super admin', 'Coach', 'Partner admin', 'Iron Gym', 'Failed jobs'])
            ->assertSee('leaves every app-user count');
    }

    public function test_a_role_row_without_timestamps_has_no_since_and_still_renders(): void
    {
        $me = $this->userWithRole('admin');
        $legacy = User::factory()->create(['name' => 'Legacy']);
        DB::table('role_user')->insert(['user_id' => $legacy->id, 'role_id' => Role::query()->where('slug', 'admin')->value('id')]);

        $row = collect(Admins::list())->firstWhere('user.id', $legacy->id);
        $this->assertNull($row['since']);

        $this->actingAs($me)->get('/admin/system')->assertOk()->assertSee('Legacy');
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
