<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercise detail in a Station's context (ticket 02): structured guidance with
 * the description fallback, partner overrides applied, and nothing served that
 * is not on the station's curated list. The mobile catalogue stays untouched
 * by the new columns.
 */
class PublicStationExerciseTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::factory()->create(['name' => 'Iron Works', 'slug' => 'iron-works']);
        $this->station = Station::factory()->for($this->partner)->create(['name' => 'Cable Crossover']);
    }

    public function test_serves_structured_guidance_with_station_context(): void
    {
        $exercise = Exercise::factory()->create([
            'instructions' => ['Set the pulleys', 'Press forward'],
            'common_mistakes' => ['Flaring elbows'],
        ]);
        $this->station->exercises()->attach($exercise->id, ['display_order' => 1]);

        $this->getJson("/api/public/stations/{$this->station->short_code}/exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $exercise->id)
            ->assertJsonPath('data.instructions', ['Set the pulleys', 'Press forward'])
            ->assertJsonPath('data.common_mistakes', ['Flaring elbows'])
            ->assertJsonPath('station.name', 'Cable Crossover')
            ->assertJsonPath('station.short_code', $this->station->short_code)
            ->assertJsonPath('station.partner.slug', 'iron-works');
    }

    public function test_falls_back_to_description_with_partner_override_when_instructions_missing(): void
    {
        $exercise = Exercise::factory()->create([
            'description' => 'Default description',
            'instructions' => null,
            'common_mistakes' => null,
        ]);
        $this->partner->exercises()->attach($exercise->id, ['description' => 'Partner description']);
        $this->station->exercises()->attach($exercise->id, ['display_order' => 1]);

        $this->getJson("/api/public/stations/{$this->station->short_code}/exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.instructions', null)
            ->assertJsonPath('data.common_mistakes', null)
            ->assertJsonPath('data.description', 'Partner description');
    }

    public function test_exercise_not_on_the_station_is_not_served(): void
    {
        $unattached = Exercise::factory()->create();

        $this->getJson("/api/public/stations/{$this->station->short_code}/exercises/{$unattached->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_archived_exercise_is_not_served(): void
    {
        $archived = Exercise::factory()->create(['archived_at' => now()]);
        $this->station->exercises()->attach($archived->id, ['display_order' => 1]);

        $this->getJson("/api/public/stations/{$this->station->short_code}/exercises/{$archived->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_gone_station_answers_gone_for_its_exercises_too(): void
    {
        $exercise = Exercise::factory()->create();
        $this->station->exercises()->attach($exercise->id, ['display_order' => 1]);
        $code = $this->station->short_code;
        $this->station->delete();

        $this->getJson("/api/public/stations/{$code}/exercises/{$exercise->id}")
            ->assertStatus(410)
            ->assertJsonPath('status', 'gone');
    }

    public function test_mobile_catalogue_is_untouched_by_the_guidance_columns(): void
    {
        $exercise = Exercise::factory()->create([
            'instructions' => ['Step one'],
            'common_mistakes' => ['Mistake one'],
        ]);
        $this->partner->exercises()->attach($exercise->id);
        $member = User::factory()->entitled()->create(['partner_id' => $this->partner->id]);

        $payload = $this->actingAs($member, 'sanctum')
            ->getJson('/api/exercises')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('instructions', $payload);
        $this->assertArrayNotHasKey('common_mistakes', $payload);
    }
}
