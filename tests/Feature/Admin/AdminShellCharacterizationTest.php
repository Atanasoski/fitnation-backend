<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSplit;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks what the super-admin panel rebuild must not move: who can reach the
 * existing /admin catalogue pages. (The partner admin's dashboard and sidebar
 * went with spec 025.)
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

    public function test_a_partner_admin_has_no_dashboard(): void
    {
        $this->actingAs($this->partnerAdmin())
            ->get('/dashboard')
            ->assertForbidden();
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
