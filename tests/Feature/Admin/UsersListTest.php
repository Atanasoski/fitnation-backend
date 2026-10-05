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

class UsersListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
    }

    public function test_the_admin_sees_every_user_across_partners_with_partner_and_activity_status(): void
    {
        $gym = Partner::factory()->create(['name' => 'Iron Temple']);
        $studio = Partner::factory()->create(['name' => 'Flow Studio']);
        $this->member($gym, ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'], completedDaysAgo: [2]);
        $this->member($studio, ['name' => 'Grace Hopper', 'email' => 'grace@example.com'], completedDaysAgo: [10]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('ada@example.com')
            ->assertSee('Iron Temple')
            ->assertSee('Grace Hopper')
            ->assertSee('Flow Studio')
            ->assertSeeInOrder(['Grace Hopper', 'Slipping'])
            ->assertSeeInOrder(['Ada Lovelace', 'Active']);
    }

    public function test_the_row_shows_signup_date_last_completed_session_and_completed_sessions_in_30_days(): void
    {
        $gym = Partner::factory()->create();
        $this->member($gym, ['name' => 'Ada Lovelace', 'created_at' => '2026-03-14 08:00:00'], completedDaysAgo: [3, 20, 25, 45], otherDaysAgo: [1]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSeeInOrder(['Ada Lovelace', '14 Mar 2026', '2 Oct 2026', '>3</td>'], false);
    }

    public function test_admin_and_partner_admin_accounts_never_appear(): void
    {
        $gym = Partner::factory()->create();
        $this->member($gym, ['name' => 'Ada Lovelace']);
        $this->userWithRole('partner_admin', ['name' => 'Gym Owner', 'partner_id' => $gym->id]);
        $this->userWithRole('admin', ['name' => 'Other Admin']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Gym Owner')
            ->assertDontSee('Other Admin');

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users?activity=unfinished')
            ->assertDontSee('Gym Owner')
            ->assertDontSee('Other Admin');
    }

    public function test_deleted_users_are_hidden_unless_the_deleted_status_is_filtered(): void
    {
        $gym = Partner::factory()->create();
        $this->member($gym, ['name' => 'Ada Lovelace']);
        $this->member($gym, ['name' => 'Gone Gary'])->delete();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Gone Gary');

        $this->actingAs($admin)->get('/admin/users?activity=deleted')
            ->assertSee('Gone Gary')
            ->assertSee('Deleted')
            ->assertDontSee('Ada Lovelace');
    }

    public function test_the_activity_filter_narrows_the_list(): void
    {
        $gym = Partner::factory()->create();
        $this->member($gym, ['name' => 'Ada Lovelace'], completedDaysAgo: [2]);
        $this->member($gym, ['name' => 'Grace Hopper'], completedDaysAgo: [10]);
        $this->member($gym, ['name' => 'Linus Torvalds', 'onboarding_completed_at' => null]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users?activity=slipping')
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertDontSee('Ada Lovelace')
            ->assertDontSee('Linus Torvalds');
    }

    public function test_the_partner_filter_narrows_the_list(): void
    {
        $gym = Partner::factory()->create(['name' => 'Iron Temple']);
        $studio = Partner::factory()->create(['name' => 'Flow Studio']);
        $this->member($gym, ['name' => 'Ada Lovelace']);
        $this->member($studio, ['name' => 'Grace Hopper']);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users?partner={$studio->id}")
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertDontSee('Ada Lovelace');
    }

    public function test_an_unknown_activity_status_is_ignored_rather_than_an_error(): void
    {
        $this->member(Partner::factory()->create(), ['name' => 'Ada Lovelace']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users?activity=bogus')
            ->assertOk()
            ->assertSee('Ada Lovelace');
    }

    public function test_filters_survive_pagination(): void
    {
        $gym = Partner::factory()->create();
        $other = Partner::factory()->create();
        foreach (range(1, 30) as $i) {
            $this->member($gym, ['name' => "Gym Member {$i}"], completedDaysAgo: [2]);
        }
        $this->member($other, ['name' => 'Elsewhere Ed'], completedDaysAgo: [2]);

        $admin = $this->userWithRole('admin');

        $first = $this->actingAs($admin)->get("/admin/users?partner={$gym->id}&activity=active")->assertOk();
        $paginator = $first->viewData('users');
        $this->assertSame(30, $paginator->total());
        $this->assertStringContainsString("partner={$gym->id}", $paginator->nextPageUrl());
        $this->assertStringContainsString('activity=active', $paginator->nextPageUrl());

        $second = $this->actingAs($admin)->get($paginator->nextPageUrl())->assertOk();
        $this->assertSame(2, $second->viewData('users')->currentPage());
        $this->assertSame(30, $second->viewData('users')->total());
        $second->assertDontSee('Elsewhere Ed');
    }

    public function test_the_filter_form_keeps_the_chosen_values(): void
    {
        $gym = Partner::factory()->create(['name' => 'Iron Temple']);

        $this->actingAs($this->userWithRole('admin'))
            ->get("/admin/users?partner={$gym->id}&activity=slipping")
            ->assertOk()
            ->assertSee('value="'.$gym->id.'" selected', false)
            ->assertSee('value="slipping" selected', false);
    }

    public function test_partner_admins_and_plain_users_get_403(): void
    {
        $gym = Partner::factory()->create();

        $this->actingAs($this->userWithRole('partner_admin', ['partner_id' => $gym->id]))
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($this->member($gym))
            ->get('/admin/users')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $completedDaysAgo
     * @param  list<int>  $otherDaysAgo
     */
    private function member(Partner $partner, array $attributes = [], array $completedDaysAgo = [], array $otherDaysAgo = []): User
    {
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'onboarding_completed_at' => now()->subDays(60),
            ...$attributes,
        ]);

        foreach ([[$completedDaysAgo, WorkoutSessionStatus::Completed], [$otherDaysAgo, WorkoutSessionStatus::Active]] as [$days, $status]) {
            foreach ($days as $d) {
                WorkoutSession::factory()->create([
                    'user_id' => $user->id,
                    'workout_template_id' => WorkoutTemplate::factory()->state(['plan_id' => Plan::factory()->state(['user_id' => $user->id])]),
                    'status' => $status,
                    'performed_at' => now()->subDays($d),
                    'completed_at' => now()->subDays($d),
                ]);
            }
        }

        return $user;
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
