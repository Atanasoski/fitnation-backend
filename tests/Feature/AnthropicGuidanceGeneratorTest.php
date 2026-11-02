<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Services\Guidance\AnthropicGuidanceGenerator;
use App\Services\Guidance\GuidanceGenerationFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The real generator's HTTP seam (spec qr-stations, ticket 06): what it sends
 * to the Anthropic API and how strictly it parses the answer — anything but
 * the demanded JSON shape is rejected, never half-written to the catalogue.
 */
class AnthropicGuidanceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key']);
    }

    public function test_sends_the_exercise_data_and_parses_a_valid_response(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->apiAnswer([
            'instructions' => ['Set the seat.', 'Press up.'],
            'common_mistakes' => ['Bouncing the weight.'],
        ]))]);

        $exercise = Exercise::factory()->create(['name' => 'Chest Press', 'description' => 'A pressing movement.']);

        $guidance = app(AnthropicGuidanceGenerator::class)->generate($exercise->load([
            'primaryMuscleGroups', 'secondaryMuscleGroups', 'equipmentType',
        ]));

        $this->assertSame(['Set the seat.', 'Press up.'], $guidance->instructions);
        $this->assertSame(['Bouncing the weight.'], $guidance->commonMistakes);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com/v1/messages')
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version')
                && $request['model'] === 'claude-sonnet-5-5'
                && str_contains($request['messages'][0]['content'], 'Chest Press')
                && str_contains($request['messages'][0]['content'], 'A pressing movement.');
        });
    }

    public function test_rejects_a_response_that_is_not_json(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Here are some tips: bend your knees...']],
        ])]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('not valid JSON');

        app(AnthropicGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_rejects_json_with_an_empty_or_missing_array(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->apiAnswer([
            'instructions' => ['Step one.'],
            'common_mistakes' => [],
        ]))]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('common_mistakes');

        app(AnthropicGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_rejects_json_with_non_string_entries(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->apiAnswer([
            'instructions' => ['Step one.', 42],
            'common_mistakes' => ['Fine.'],
        ]))]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('instructions');

        app(AnthropicGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_rejects_an_api_error(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('HTTP 529');

        app(AnthropicGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_refuses_to_run_without_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);
        Http::fake();

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('No Anthropic API key');

        app(AnthropicGuidanceGenerator::class)->generate(Exercise::factory()->create());

        Http::assertNothingSent();
    }

    /**
     * An Anthropic Messages API answer whose text block is the given payload,
     * preceded by a thinking block the parser must skip over.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function apiAnswer(array $payload): array
    {
        return [
            'content' => [
                ['type' => 'thinking', 'thinking' => ''],
                ['type' => 'text', 'text' => json_encode($payload)],
            ],
        ];
    }
}
