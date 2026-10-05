<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_overview_renders_for_an_admin(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Overview');
    }

    public function test_the_dashboard_sends_an_admin_to_the_overview(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/dashboard')
            ->assertRedirect(route('admin.overview'));
    }

    public function test_the_admin_sidebar_has_the_six_items_with_content_as_a_group(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))->get('/admin')->getContent();

        $this->assertSame([
            ['Overview', '/admin'],
            ['Users', '/admin/users'],
            ['Partners', '/admin/partners'],
            ['Exercises', '/admin/exercises'],
            ['Workout Splits', '/admin/workout-splits'],
            ['Generator Preview', '/admin/workout-preview'],
            ['Insights', '/admin/insights'],
            ['System', '/admin/system'],
        ], array_map(fn (array $link) => [$link['label'], $link['href']], $this->sidebarLinks($html)));

        $this->assertSame(['Content'], $this->sidebarGroups($html));
    }

    #[DataProvider('activeItems')]
    public function test_the_sidebar_highlights_the_current_item(string $path, string $active, bool $contentOpen): void
    {
        // The splits page's empty state does not render (component-card without a title).
        $this->seed(WorkoutSplitSeeder::class);

        $html = $this->actingAs($this->userWithRole('admin'))->get($path)->assertOk()->getContent();

        $activeLinks = array_values(array_map(
            fn (array $link) => $link['label'],
            array_filter($this->sidebarLinks($html), fn (array $link) => $link['active']),
        ));

        $this->assertSame([$active], $activeLinks);
        $this->assertSame($contentOpen, str_contains($html, 'data-group-open="Content"'));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function activeItems(): array
    {
        return [
            'overview' => ['/admin', 'Overview', false],
            'users' => ['/admin/users', 'Users', false],
            'partners' => ['/admin/partners', 'Partners', false],
            'partner create' => ['/partners/create', 'Partners', false],
            'exercises' => ['/admin/exercises', 'Exercises', true],
            'exercise create' => ['/admin/exercises?create=1', 'Exercises', true],
            'workout splits' => ['/admin/workout-splits', 'Workout Splits', true],
            'generator preview' => ['/admin/workout-preview', 'Generator Preview', true],
            'insights' => ['/admin/insights', 'Insights', false],
            'system' => ['/admin/system', 'System', false],
        ];
    }

    public function test_insights_is_a_coming_soon_placeholder(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/insights')
            ->assertOk()
            ->assertSee('Insights')
            ->assertSee('Coming soon');
    }

    public function test_the_system_and_users_pages_render_for_an_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get('/admin/system')->assertOk()->assertSee('System');
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Users');
    }

    #[DataProvider('superAdminPages')]
    public function test_super_admin_pages_are_forbidden_to_partner_admins_and_users(string $path): void
    {
        $this->actingAs($this->partnerAdmin())->get($path)->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get($path)->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function superAdminPages(): array
    {
        return [
            'overview' => ['/admin'],
            'users' => ['/admin/users'],
            'insights' => ['/admin/insights'],
            'system' => ['/admin/system'],
        ];
    }

    /**
     * The sidebar's menu links, top to bottom, with whether each is highlighted.
     *
     * @return list<array{label: string, href: string, active: bool}>
     */
    private function sidebarLinks(string $html): array
    {
        preg_match('#<aside id="sidebar".*?</aside>#s', $html, $aside);
        preg_match_all('#<a href="([^"]*)" class="(menu-(?:item|dropdown-item)[^"]*)"[^>]*>(.*?)</a>#s', $aside[0] ?? '', $links, PREG_SET_ORDER);

        return array_map(fn (array $link) => [
            'label' => trim(preg_replace('/\s+/', ' ', strip_tags($link[3]))),
            'href' => $link[1],
            'active' => (bool) preg_match('/\bmenu-(?:item|dropdown-item)-active\b/', $link[2]),
        ], $links);
    }

    /**
     * Labels of the sidebar's expandable groups.
     *
     * @return list<string>
     */
    private function sidebarGroups(string $html): array
    {
        preg_match_all('#data-group="([^"]+)"#', $html, $groups);

        return $groups[1];
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
}
