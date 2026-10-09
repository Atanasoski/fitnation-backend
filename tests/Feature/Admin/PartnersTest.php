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
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The super admin's Partners list and partner page (ticket 12). "Now" is
 * Wednesday 7 Oct 2026; this week started Monday 5 Oct.
 */
class PartnersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_the_list_shows_each_partner_with_its_kind_plan_expiry_and_active_flag(): void
    {
        config(['partners.house_partner_id' => Partner::factory()->create(['name' => 'Fit Nation', 'plan' => PartnerPlan::Free])->id]);
        Partner::factory()->create(['name' => 'Iron Temple', 'plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-12-01 00:00:00']);
        Partner::factory()->create(['name' => 'Lift Club', 'plan' => PartnerPlan::Free, 'is_active' => false]);

        $this->asAdmin('/admin/partners')
            ->assertOk()
            ->assertSeeInOrder(['Fit Nation', 'House Partner', 'Free'])
            ->assertSeeInOrder(['Iron Temple', 'Sponsoring Partner', 'Sponsor', '1 Dec 2026', 'Active'])
            ->assertSeeInOrder(['Lift Club', 'Partner', 'Free', 'Inactive']);
    }

    public function test_member_counts_leave_out_staff_and_deleted_users_and_active_means_trained_this_week(): void
    {
        $gym = Partner::factory()->create(['name' => 'Iron Temple', 'slug' => 'iron-temple', 'plan' => PartnerPlan::Free]);
        $trainedMonday = $this->member($gym);
        $this->completedSession($trainedMonday, '2026-10-05 09:00:00');
        $trainedLastWeek = $this->member($gym);
        $this->completedSession($trainedLastWeek, '2026-10-04 20:00:00');
        $this->member($gym);
        $this->member($gym, ['deleted_at' => '2026-10-01 00:00:00']);
        $this->completedSession($this->userWithRole('partner_admin', ['partner_id' => $gym->id]), '2026-10-06 09:00:00');
        $this->userWithRole('admin', ['partner_id' => $gym->id]);

        $this->asAdmin('/admin/partners')
            ->assertOk()
            ->assertSeeInOrder(['Iron Temple', 'Partner', '3', '1', 'Free']);

        $this->asAdmin("/admin/partners/{$gym->slug}")
            ->assertOk()
            ->assertSeeInOrder(['Members', '3', 'active this week', '1']);
    }

    public function test_the_partner_page_shows_members_with_chips_admins_plan_and_branding(): void
    {
        $gym = Partner::factory()->create([
            'name' => 'Iron Temple',
            'plan' => PartnerPlan::Sponsor,
            'plan_expires_at' => '2026-12-01 00:00:00',
        ]);
        $gym->identity()->create([
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'primary_color_dark' => '#778899',
        ]);
        $this->member($gym, ['name' => 'Ada Lovelace']);
        $this->member($gym, ['name' => 'Grace Hopper', 'email_verified_at' => null]);
        $this->userWithRole('partner_admin', ['partner_id' => $gym->id, 'name' => 'Pat Manager', 'email' => 'pat@iron.test']);
        $this->member(Partner::factory()->create(), ['name' => 'Linus Torvalds']);

        $this->asAdmin("/admin/partners/{$gym->slug}")
            ->assertOk()
            ->assertSee('Sponsoring Partner')
            ->assertSee('1 Dec 2026')
            ->assertSeeInOrder(['Admins', 'Pat Manager', 'pat@iron.test'])
            ->assertSeeInOrder(['Ada Lovelace', 'New', 'Sponsored'])
            ->assertSeeInOrder(['Grace Hopper', 'Unfinished', 'Sponsored'])
            ->assertDontSee('Linus Torvalds')
            ->assertSee('Light')
            ->assertSee('Dark')
            ->assertSee('#112233')
            ->assertSee('#778899');
    }

    public function test_all_members_opens_the_users_list_filtered_by_the_partner(): void
    {
        $gym = Partner::factory()->create();

        $this->asAdmin("/admin/partners/{$gym->slug}")
            ->assertOk()
            ->assertSee('href="'.e(route('admin.users.index', ['partner' => $gym->id])).'"', escape: false)
            ->assertSee('All members');
    }

    public function test_the_list_links_each_partner_to_its_page(): void
    {
        $gym = Partner::factory()->create();

        $this->asAdmin('/admin/partners')
            ->assertSee('href="'.e(route('admin.partners.show', $gym)).'"', escape: false);
    }

    public function test_an_admin_deactivates_and_reactivates_a_partner(): void
    {
        $gym = Partner::factory()->create(['is_active' => true]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from("/admin/partners/{$gym->slug}")
            ->patch("/admin/partners/{$gym->slug}/active", ['active' => '0'])
            ->assertRedirect("/admin/partners/{$gym->slug}")
            ->assertSessionHas('success');
        $this->assertFalse($gym->refresh()->is_active);

        $this->actingAs($admin)
            ->patch("/admin/partners/{$gym->slug}/active", ['active' => '1'])
            ->assertSessionHas('success');
        $this->assertTrue($gym->refresh()->is_active);
    }

    public function test_the_deactivate_button_asks_for_confirmation(): void
    {
        $gym = Partner::factory()->create(['name' => 'Iron Temple', 'is_active' => true]);

        $this->asAdmin("/admin/partners/{$gym->slug}")
            ->assertSee('Deactivate partner')
            ->assertSee('return confirm(', escape: false);
    }

    public function test_the_house_partner_cannot_be_deactivated(): void
    {
        $house = Partner::factory()->create(['name' => 'Fit Nation', 'is_active' => true]);
        config(['partners.house_partner_id' => $house->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->patch("/admin/partners/{$house->slug}/active", ['active' => '0'])
            ->assertSessionHasErrors('active');
        $this->assertTrue($house->refresh()->is_active);

        $this->asAdmin("/admin/partners/{$house->slug}")
            ->assertDontSee('Deactivate partner');
    }

    public function test_the_expiring_filter_lists_only_sponsorships_running_out_within_30_days(): void
    {
        Partner::factory()->create(['name' => 'Iron Temple', 'plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-10-20 00:00:00']);
        Partner::factory()->create(['name' => 'Lift Club', 'plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2027-01-01 00:00:00']);
        Partner::factory()->create(['name' => 'Old Gym', 'plan' => PartnerPlan::Sponsor, 'plan_expires_at' => '2026-10-01 00:00:00']);
        Partner::factory()->create(['name' => 'Free Gym', 'plan' => PartnerPlan::Free, 'plan_expires_at' => '2026-10-20 00:00:00']);

        $this->asAdmin('/admin/partners?expiring=1')
            ->assertSee('Iron Temple')
            ->assertDontSee('Lift Club')
            ->assertDontSee('Old Gym')
            ->assertDontSee('Free Gym');
    }

    public function test_the_active_flag_must_be_given(): void
    {
        $gym = Partner::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole('admin'))
            ->patch("/admin/partners/{$gym->slug}/active", [])
            ->assertSessionHasErrors('active');
        $this->assertTrue($gym->refresh()->is_active);
    }

    #[DataProvider('nonAdmins')]
    public function test_non_admins_are_forbidden(string $role): void
    {
        $gym = Partner::factory()->create(['is_active' => true]);
        $user = $this->userWithRole($role, ['partner_id' => $gym->id]);

        $this->actingAs($user)->get('/admin/partners')->assertForbidden();
        $this->actingAs($user)->get("/admin/partners/{$gym->slug}")->assertForbidden();
        $this->actingAs($user)->patch("/admin/partners/{$gym->slug}/active", ['active' => '0'])->assertForbidden();
        $this->assertTrue($gym->refresh()->is_active);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdmins(): array
    {
        return ['partner admin' => ['partner_admin'], 'user' => ['user']];
    }

    private function asAdmin(string $path): TestResponse
    {
        return $this->actingAs($this->userWithRole('admin'))->get($path);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(Partner $partner, array $attributes = []): User
    {
        return User::factory()->create([
            'partner_id' => $partner->id,
            'onboarding_completed_at' => now()->subDays(3),
            ...$attributes,
        ]);
    }

    private function completedSession(User $user, string $at): void
    {
        WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $at,
            'completed_at' => $at,
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
