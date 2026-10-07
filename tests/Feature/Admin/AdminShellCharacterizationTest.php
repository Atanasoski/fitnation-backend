<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSplit;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks what the super-admin panel rebuild must not move: the partner admin's
 * dashboard and sidebar, and who can reach the existing /admin catalogue pages.
 */
class AdminShellCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The splits page's empty state does not render (component-card without a title).
        $this->seed(WorkoutSplitSeeder::class);
    }

    public function test_partner_admin_dashboard_renders_their_partner_dashboard(): void
    {
        $this->actingAs($this->partnerAdmin())
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard.partner');
    }

    public function test_partner_admin_sidebar_is_unchanged(): void
    {
        $html = $this->actingAs($this->partnerAdmin())->get('/dashboard')->getContent();

        $this->assertSame([
            ['Dashboard', '/dashboard'],
            ['Users', '/users'],
            ['Programs', '/partner/programs'],
            ['Exercises', '/partner/exercises'],
        ], $this->sidebarLinks($html));
    }

    public function test_existing_admin_catalogue_pages_render_for_an_admin(): void
    {
        $admin = $this->userWithRole('admin');

        foreach (['/admin/exercises', '/admin/workout-splits', '/admin/workout-preview'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_existing_admin_catalogue_pages_are_forbidden_to_partner_admins_and_users(): void
    {
        foreach ([$this->partnerAdmin(), $this->userWithRole('user')] as $user) {
            foreach (['/admin/exercises', '/admin/workout-splits', '/admin/workout-preview'] as $path) {
                $this->actingAs($user)->get($path)->assertForbidden();
            }
        }
    }

    public function test_every_workout_split_and_preview_route_is_forbidden_to_partner_admins_and_users(): void
    {
        $split = WorkoutSplit::query()->firstOrFail();

        foreach ([$this->partnerAdmin(), $this->userWithRole('user')] as $user) {
            $this->actingAs($user)->get('/admin/workout-splits/create')->assertForbidden();
            $this->actingAs($user)->post('/admin/workout-splits', [])->assertForbidden();
            $this->actingAs($user)->get("/admin/workout-splits/{$split->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/admin/workout-splits/{$split->id}", [])->assertForbidden();
            $this->actingAs($user)->delete("/admin/workout-splits/{$split->id}")->assertForbidden();
            $this->actingAs($user)->post('/admin/workout-preview', [])->assertForbidden();
        }

        $this->assertModelExists($split);
    }

    public function test_partner_admin_dashboard_counts_and_lists_only_their_app_users(): void
    {
        $partner = Partner::factory()->create();
        $partnerAdmin = $this->userWithRole('partner_admin', ['partner_id' => $partner->id]);
        $this->userWithRole('admin', ['partner_id' => $partner->id]);
        $active = User::factory()->create(['partner_id' => $partner->id]);
        $idle = User::factory()->create(['partner_id' => $partner->id]);
        User::factory()->create(['partner_id' => Partner::factory()->create()->id]);
        WorkoutSession::factory()->create(['user_id' => $active->id, 'performed_at' => now()]);
        WorkoutSession::factory()->create(['user_id' => $partnerAdmin->id, 'performed_at' => now()]);

        $this->actingAs($partnerAdmin)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('totalMembers', 2)
            ->assertViewHas('activeMembersThisWeek', 1)
            ->assertViewHas('topMembers', fn ($members) => $members->pluck('id')->all() === [$active->id])
            ->assertViewHas('recentMembers', fn ($members) => $members->pluck('id')->sort()->values()->all() === collect([$active->id, $idle->id])->sort()->values()->all());
    }

    private function partnerAdmin(): User
    {
        return $this->userWithRole('partner_admin', ['partner_id' => Partner::factory()->create()->id]);
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

    /**
     * The sidebar's menu links as [label, href], top to bottom.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function sidebarLinks(string $html): array
    {
        preg_match('#<aside id="sidebar".*?</aside>#s', $html, $aside);
        preg_match_all('#<a href="([^"]*)" class="menu-(?:item|dropdown-item)[^"]*"[^>]*>(.*?)</a>#s', $aside[0] ?? '', $links, PREG_SET_ORDER);

        return array_map(
            fn (array $link) => [trim(preg_replace('/\s+/', ' ', strip_tags($link[2]))), $link[1]],
            $links,
        );
    }
}
