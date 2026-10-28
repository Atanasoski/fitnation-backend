<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public side of a Station (ticket 01, ADR-0005): an anonymous scanner
 * fetches a station by short code and gets the chooser payload; stale stickers
 * get well-formed gone/not-found answers.
 */
class PublicStationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_station_with_partner_and_ordered_exercise_summaries(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Works', 'slug' => 'iron-works']);
        $station = Station::factory()->for($partner)->create(['name' => 'Cable Crossover']);

        $chest = MuscleGroup::factory()->create(['name' => 'Chest']);
        [$second, $first, $third] = Exercise::factory()->count(3)->create();
        $first->muscleGroups()->attach($chest->id, ['is_primary' => true]);

        // Attached out of creation order; display_order decides.
        $station->exercises()->attach([
            $second->id => ['display_order' => 2],
            $first->id => ['display_order' => 1],
            $third->id => ['display_order' => 3],
        ]);

        $response = $this->getJson("/api/public/stations/{$station->short_code}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Cable Crossover')
            ->assertJsonPath('data.short_code', $station->short_code)
            ->assertJsonPath('data.partner.name', 'Iron Works')
            ->assertJsonPath('data.partner.slug', 'iron-works')
            ->assertJsonPath('data.exercises.0.id', $first->id)
            ->assertJsonPath('data.exercises.1.id', $second->id)
            ->assertJsonPath('data.exercises.2.id', $third->id)
            ->assertJsonPath('data.exercises.0.primary_muscles.0', 'Chest');
    }

    public function test_applies_partner_media_overrides_to_exercise_summaries(): void
    {
        $partner = Partner::factory()->create();
        $station = Station::factory()->for($partner)->create();
        $exercise = Exercise::factory()->create(['image' => 'exercises/images/default.jpg']);

        $partner->exercises()->attach($exercise->id, ['image' => 'exercises/images/partner-own.jpg']);
        $station->exercises()->attach($exercise->id, ['display_order' => 1]);

        $this->getJson("/api/public/stations/{$station->short_code}")
            ->assertOk()
            ->assertJsonPath('data.exercises.0.image', fn ($url) => str_contains((string) $url, 'partner-own.jpg'));
    }

    public function test_excludes_archived_exercises(): void
    {
        $station = Station::factory()->create();
        $live = Exercise::factory()->create();
        $archived = Exercise::factory()->create(['archived_at' => now()]);
        $station->exercises()->attach([
            $live->id => ['display_order' => 1],
            $archived->id => ['display_order' => 2],
        ]);

        $this->getJson("/api/public/stations/{$station->short_code}")
            ->assertOk()
            ->assertJsonCount(1, 'data.exercises')
            ->assertJsonPath('data.exercises.0.id', $live->id);
    }

    public function test_unknown_code_returns_not_found_shape(): void
    {
        $this->getJson('/api/public/stations/nosuchcode')
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_soft_deleted_station_returns_gone_shape(): void
    {
        $station = Station::factory()->create();
        $code = $station->short_code;
        $station->delete();

        $this->getJson("/api/public/stations/{$code}")
            ->assertStatus(410)
            ->assertJsonPath('status', 'gone');
    }

    public function test_requires_no_authentication_and_is_rate_limited(): void
    {
        $station = Station::factory()->create();

        $this->getJson("/api/public/stations/{$station->short_code}")
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 60)
            ->assertHeader('Cache-Control', 'max-age=300, public');
    }

    public function test_short_code_is_generated_once_and_survives_renames(): void
    {
        $station = Station::factory()->create();
        $code = $station->short_code;

        $this->assertNotNull($code);
        $this->assertSame(8, strlen($code));

        $station->update(['name' => 'Renamed Machine']);

        $this->assertSame($code, $station->fresh()->short_code);
    }
}
