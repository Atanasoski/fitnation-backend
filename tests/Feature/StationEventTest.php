<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anonymous scan analytics (ticket 04): the public pages record scans,
 * exercise opens and CTA clicks against a Station, with the benefit shown,
 * without any user identification.
 */
class StationEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_scan_event(): void
    {
        $station = Station::factory()->create();

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'scan',
            'benefit' => 'track_weight',
        ])->assertNoContent();

        $this->assertDatabaseHas('station_events', [
            'station_id' => $station->id,
            'type' => 'scan',
            'exercise_id' => null,
            'benefit' => 'track_weight',
        ]);
    }

    public function test_records_an_exercise_open_event_for_an_exercise_on_the_station(): void
    {
        $station = Station::factory()->create();
        $exercise = Exercise::factory()->create();
        $station->exercises()->attach($exercise->id, ['display_order' => 1]);

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'exercise_open',
            'exercise_id' => $exercise->id,
            'benefit' => 'progress_history',
        ])->assertNoContent();

        $this->assertDatabaseHas('station_events', [
            'station_id' => $station->id,
            'type' => 'exercise_open',
            'exercise_id' => $exercise->id,
            'benefit' => 'progress_history',
        ]);
    }

    public function test_records_a_cta_click_event(): void
    {
        $station = Station::factory()->create();

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'cta_click',
            'benefit' => 'track_weight',
        ])->assertNoContent();

        $this->assertDatabaseHas('station_events', [
            'station_id' => $station->id,
            'type' => 'cta_click',
            'benefit' => 'track_weight',
        ]);
    }

    public function test_benefit_is_optional(): void
    {
        $station = Station::factory()->create();

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'scan',
        ])->assertNoContent();

        $this->assertDatabaseHas('station_events', [
            'station_id' => $station->id,
            'type' => 'scan',
            'benefit' => null,
        ]);
    }

    public function test_rejects_an_unknown_event_type(): void
    {
        $station = Station::factory()->create();

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'page_view',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('station_events', 0);
    }

    public function test_rejects_an_exercise_that_is_not_on_the_station(): void
    {
        $station = Station::factory()->create();
        $elsewhere = Exercise::factory()->create();

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'exercise_open',
            'exercise_id' => $elsewhere->id,
        ])->assertNotFound()
            ->assertJsonPath('status', 'not_found');

        $this->assertDatabaseCount('station_events', 0);
    }

    public function test_unknown_code_returns_not_found_shape(): void
    {
        $this->postJson('/api/public/stations/nosuchcode/events', [
            'type' => 'scan',
        ])->assertNotFound()
            ->assertJsonPath('status', 'not_found');

        $this->assertDatabaseCount('station_events', 0);
    }

    public function test_soft_deleted_station_returns_gone_shape(): void
    {
        $station = Station::factory()->create();
        $code = $station->short_code;
        $station->delete();

        $this->postJson("/api/public/stations/{$code}/events", [
            'type' => 'scan',
        ])->assertStatus(410)
            ->assertJsonPath('status', 'gone');

        $this->assertDatabaseCount('station_events', 0);
    }

    public function test_requires_no_authentication_and_is_rate_limited(): void
    {
        $station = Station::factory()->create();

        $this->postJson("/api/public/stations/{$station->short_code}/events", [
            'type' => 'scan',
        ])->assertNoContent()
            ->assertHeader('X-RateLimit-Limit', 120);
    }
}
