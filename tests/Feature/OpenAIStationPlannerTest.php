<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Services\Stations\OpenAIStationPlanner;
use App\Services\Stations\StationPlanningFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The real planner's HTTP seam: what it sends to the OpenAI API and how
 * strictly it validates the proposal — an id that was never offered, a
 * duplicate, or an empty plan rejects the whole thing.
 */
class OpenAIStationPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-key']);
    }

    public function test_sends_the_roster_and_parses_a_valid_plan(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Works']);
        [$a, $b] = Exercise::factory()->count(2)->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'stations' => [['name' => 'Cable Station', 'exercise_ids' => [$b->id, $a->id]]],
        ]))]);

        $plan = app(OpenAIStationPlanner::class)->plan(
            $partner,
            Exercise::with(['equipmentType', 'primaryMuscleGroups'])->get()
        );

        $this->assertCount(1, $plan);
        $this->assertSame('Cable Station', $plan[0]->name);
        $this->assertSame([$b->id, $a->id], $plan[0]->exerciseIds);

        Http::assertSent(function ($request) use ($a) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['response_format'] === ['type' => 'json_object']
                && str_contains($request['messages'][1]['content'], 'Iron Works')
                && str_contains($request['messages'][1]['content'], $a->name);
        });
    }

    public function test_rejects_an_exercise_id_that_was_never_offered(): void
    {
        $partner = Partner::factory()->create();
        $offered = Exercise::factory()->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'stations' => [['name' => 'Bench', 'exercise_ids' => [$offered->id, $offered->id + 999]]],
        ]))]);

        $this->expectException(StationPlanningFailed::class);
        $this->expectExceptionMessage('not offered');

        app(OpenAIStationPlanner::class)->plan($partner, Exercise::query()->get());
    }

    public function test_rejects_an_exercise_on_two_stations(): void
    {
        $partner = Partner::factory()->create();
        $exercise = Exercise::factory()->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'stations' => [
                ['name' => 'Bench A', 'exercise_ids' => [$exercise->id]],
                ['name' => 'Bench B', 'exercise_ids' => [$exercise->id]],
            ],
        ]))]);

        $this->expectException(StationPlanningFailed::class);
        $this->expectExceptionMessage('more than one');

        app(OpenAIStationPlanner::class)->plan($partner, Exercise::query()->get());
    }

    public function test_rejects_an_empty_or_malformed_plan(): void
    {
        $partner = Partner::factory()->create();
        Exercise::factory()->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer(['stations' => []]))]);

        $this->expectException(StationPlanningFailed::class);
        $this->expectExceptionMessage('no usable stations');

        app(OpenAIStationPlanner::class)->plan($partner, Exercise::query()->get());
    }

    public function test_skips_a_nameless_or_empty_station_instead_of_failing_the_plan(): void
    {
        $partner = Partner::factory()->create();
        $exercise = Exercise::factory()->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'stations' => [
                ['name' => 'Leg Press', 'exercise_ids' => []],
                ['name' => null, 'exercise_ids' => [$exercise->id]],
                ['exercise_ids' => [$exercise->id]],
                ['name' => 'Cable Station', 'exercise_ids' => [$exercise->id]],
            ],
        ]))]);

        $plan = app(OpenAIStationPlanner::class)->plan($partner, Exercise::query()->get());

        $this->assertCount(1, $plan);
        $this->assertSame('Cable Station', $plan[0]->name);
        $this->assertSame([$exercise->id], $plan[0]->exerciseIds);
    }

    public function test_rejects_a_plan_where_every_station_is_malformed(): void
    {
        $partner = Partner::factory()->create();
        Exercise::factory()->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'stations' => [['name' => 'Leg Press', 'exercise_ids' => []]],
        ]))]);

        $this->expectException(StationPlanningFailed::class);
        $this->expectExceptionMessage('no usable stations');

        app(OpenAIStationPlanner::class)->plan($partner, Exercise::query()->get());
    }

    public function test_accepts_exercise_ids_sent_as_numeric_strings(): void
    {
        $partner = Partner::factory()->create();
        $exercise = Exercise::factory()->create();

        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'stations' => [['name' => 'Bench', 'exercise_ids' => [(string) $exercise->id]]],
        ]))]);

        $plan = app(OpenAIStationPlanner::class)->plan($partner, Exercise::query()->get());

        $this->assertSame([$exercise->id], $plan[0]->exerciseIds);
    }

    public function test_refuses_to_run_without_an_api_key(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();

        $this->expectException(StationPlanningFailed::class);
        $this->expectExceptionMessage('No OpenAI API key');

        app(OpenAIStationPlanner::class)->plan(Partner::factory()->create(), collect());

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function apiAnswer(array $payload): array
    {
        return [
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => json_encode($payload)]],
            ],
        ];
    }
}
