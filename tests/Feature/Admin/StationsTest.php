<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use App\Services\Stations\StationQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The super admin curates a Partner's Stations and prints their QRs
 * (spec qr-stations, ticket 05). Short codes are shown but never written;
 * stations soft-delete only (ADR-0005).
 */
class StationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_partner_page_lists_stations_with_short_code_and_qr_downloads(): void
    {
        $gym = Partner::factory()->create();
        $station = Station::factory()->for($gym)->create(['name' => 'Cable Crossover']);

        $this->actingAs($this->admin())
            ->get("/admin/partners/{$gym->slug}")
            ->assertOk()
            ->assertSeeInOrder(['Stations', 'Cable Crossover', $station->short_code])
            ->assertSee('New station')
            ->assertSee(route('admin.stations.qr', [$gym, $station, 'svg']))
            ->assertSee(route('admin.stations.qr', [$gym, $station, 'png']));
    }

    public function test_the_create_form_offers_only_exercises_linked_to_the_partner(): void
    {
        $gym = Partner::factory()->create();
        $linked = Exercise::factory()->create(['name' => 'Partner Cable Fly']);
        $archived = Exercise::factory()->create(['name' => 'Old Archived Fly', 'archived_at' => now()]);
        Exercise::factory()->create(['name' => 'Unlinked Leg Press']);
        $gym->exercises()->attach([$linked->id, $archived->id]);

        $this->actingAs($this->admin())
            ->get("/admin/partners/{$gym->slug}/stations/create")
            ->assertOk()
            ->assertSee('Partner Cable Fly')
            ->assertDontSee('Unlinked Leg Press')
            ->assertDontSee('Old Archived Fly');
    }

    public function test_an_admin_creates_a_station_with_an_ordered_exercise_list(): void
    {
        $gym = Partner::factory()->create();
        [$first, $second] = Exercise::factory()->count(2)->create();
        $gym->exercises()->attach([$first->id, $second->id]);

        $this->actingAs($this->admin())
            ->post("/admin/partners/{$gym->slug}/stations", [
                'name' => 'Cable Crossover',
                'exercise_ids' => [$second->id, $first->id],
            ])
            ->assertRedirect("/admin/partners/{$gym->slug}")
            ->assertSessionHas('success');

        $station = Station::sole();
        $this->assertSame('Cable Crossover', $station->name);
        $this->assertSame(8, strlen($station->short_code));
        $this->assertSame([$second->id, $first->id], $station->exercises()->pluck('workout_exercises.id')->all());
        $this->assertDatabaseHas('station_exercise', ['station_id' => $station->id, 'exercise_id' => $second->id, 'display_order' => 0]);
        $this->assertDatabaseHas('station_exercise', ['station_id' => $station->id, 'exercise_id' => $first->id, 'display_order' => 1]);
    }

    public function test_an_exercise_not_linked_to_the_partner_is_rejected(): void
    {
        $gym = Partner::factory()->create();
        $otherGym = Partner::factory()->create();
        $foreign = Exercise::factory()->create();
        $otherGym->exercises()->attach($foreign->id);

        $this->actingAs($this->admin())
            ->post("/admin/partners/{$gym->slug}/stations", [
                'name' => 'Cable Crossover',
                'exercise_ids' => [$foreign->id],
            ])
            ->assertSessionHasErrors('exercise_ids.0');

        $this->assertSame(0, Station::count());
    }

    public function test_an_admin_renames_and_reorders_a_station(): void
    {
        $gym = Partner::factory()->create();
        [$first, $second, $third] = Exercise::factory()->count(3)->create();
        $gym->exercises()->attach([$first->id, $second->id, $third->id]);
        $station = Station::factory()->for($gym)->create(['name' => 'Old Name']);
        $station->exercises()->attach([
            $first->id => ['display_order' => 0],
            $second->id => ['display_order' => 1],
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/partners/{$gym->slug}/stations/{$station->id}", [
                'name' => 'New Name',
                'exercise_ids' => [$third->id, $second->id, $first->id],
            ])
            ->assertRedirect("/admin/partners/{$gym->slug}")
            ->assertSessionHas('success');

        $this->assertSame('New Name', $station->refresh()->name);
        $this->assertSame(
            [$third->id, $second->id, $first->id],
            $station->exercises()->pluck('workout_exercises.id')->all()
        );
    }

    public function test_the_short_code_cannot_be_changed_through_the_form(): void
    {
        $gym = Partner::factory()->create();
        $station = Station::factory()->for($gym)->create();
        $original = $station->short_code;

        $this->actingAs($this->admin())
            ->put("/admin/partners/{$gym->slug}/stations/{$station->id}", [
                'name' => 'Renamed',
                'short_code' => 'hacked99',
            ])
            ->assertRedirect("/admin/partners/{$gym->slug}");

        $this->assertSame($original, $station->refresh()->short_code);
    }

    public function test_deleting_a_station_soft_deletes_and_keeps_its_short_code_reserved(): void
    {
        $gym = Partner::factory()->create();
        $station = Station::factory()->for($gym)->create();

        $this->actingAs($this->admin())
            ->delete("/admin/partners/{$gym->slug}/stations/{$station->id}")
            ->assertRedirect("/admin/partners/{$gym->slug}")
            ->assertSessionHas('success');

        $this->assertSoftDeleted('stations', ['id' => $station->id, 'short_code' => $station->short_code]);
    }

    public function test_a_station_of_another_partner_is_not_reachable_under_this_partner(): void
    {
        $gym = Partner::factory()->create();
        $otherStation = Station::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get("/admin/partners/{$gym->slug}/stations/{$otherStation->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/admin/partners/{$gym->slug}/stations/{$otherStation->id}", ['name' => 'X'])->assertNotFound();
        $this->actingAs($admin)->delete("/admin/partners/{$gym->slug}/stations/{$otherStation->id}")->assertNotFound();

        $this->assertNull($otherStation->refresh()->deleted_at);
    }

    public function test_a_soft_deleted_station_is_not_editable(): void
    {
        $gym = Partner::factory()->create();
        $station = Station::factory()->for($gym)->create();
        $station->delete();

        $this->actingAs($this->admin())
            ->get("/admin/partners/{$gym->slug}/stations/{$station->id}/edit")
            ->assertNotFound();
    }

    public function test_the_qr_downloads_come_back_as_svg_and_png_attachments(): void
    {
        $gym = Partner::factory()->create();
        $station = Station::factory()->for($gym)->create();
        $admin = $this->admin();

        $svg = $this->actingAs($admin)->get("/admin/partners/{$gym->slug}/stations/{$station->id}/qr.svg");
        $svg->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', "attachment; filename=\"station-{$station->short_code}.svg\"");
        $this->assertStringContainsString('<svg', $svg->content());

        $png = $this->actingAs($admin)->get("/admin/partners/{$gym->slug}/stations/{$station->id}/qr.png");
        $png->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', "attachment; filename=\"station-{$station->short_code}.png\"");
        $this->assertStringStartsWith("\x89PNG", $png->content());
    }

    public function test_the_qr_encodes_the_public_station_url_on_the_web_app_domain(): void
    {
        config(['app.webapp_url' => 'https://app.fitnation.test/']);
        $station = Station::factory()->create();

        $this->assertSame(
            "https://app.fitnation.test/s/{$station->short_code}",
            app(StationQr::class)->url($station)
        );
    }

    public function test_an_unsupported_qr_format_is_not_found(): void
    {
        $gym = Partner::factory()->create();
        $station = Station::factory()->for($gym)->create();

        $this->actingAs($this->admin())
            ->get("/admin/partners/{$gym->slug}/stations/{$station->id}/qr.gif")
            ->assertNotFound();
    }

    #[DataProvider('nonAdmins')]
    public function test_non_admins_cannot_touch_stations(string $role): void
    {
        $gym = Partner::factory()->create();
        $exercise = Exercise::factory()->create();
        $gym->exercises()->attach($exercise->id);
        $station = Station::factory()->for($gym)->create(['name' => 'Untouched']);
        $user = $this->userWithRole($role, ['partner_id' => $gym->id]);

        $this->actingAs($user)->get("/admin/partners/{$gym->slug}/stations/create")->assertForbidden();
        $this->actingAs($user)->post("/admin/partners/{$gym->slug}/stations", ['name' => 'New'])->assertForbidden();
        $this->actingAs($user)->get("/admin/partners/{$gym->slug}/stations/{$station->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/admin/partners/{$gym->slug}/stations/{$station->id}", ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($user)->delete("/admin/partners/{$gym->slug}/stations/{$station->id}")->assertForbidden();
        $this->actingAs($user)->get("/admin/partners/{$gym->slug}/stations/{$station->id}/qr.svg")->assertForbidden();

        $this->assertSame('Untouched', $station->refresh()->name);
        $this->assertNull($station->deleted_at);
        $this->assertSame(1, Station::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdmins(): array
    {
        return ['partner admin' => ['partner_admin'], 'user' => ['user']];
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
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
