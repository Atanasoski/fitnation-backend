<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Services\Guidance\GuidanceGenerationFailed;
use App\Services\Guidance\OpenAIGuidanceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The real generator's HTTP seam (spec qr-stations, ticket 06): what it sends
 * to the OpenAI API and how strictly it parses the answer — anything but
 * the demanded JSON shape is rejected, never half-written to the catalogue.
 */
class OpenAIGuidanceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.key' => 'test-key']);
    }

    public function test_sends_the_exercise_data_and_parses_a_valid_response(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'instructions' => ['Set the seat.', 'Press up.'],
            'common_mistakes' => ['Bouncing the weight.'],
        ]))]);

        $exercise = Exercise::factory()->create(['name' => 'Chest Press', 'description' => 'A pressing movement.']);

        $guidance = app(OpenAIGuidanceGenerator::class)->generate($exercise->load([
            'primaryMuscleGroups', 'secondaryMuscleGroups', 'equipmentType',
        ]));

        $this->assertSame(['Set the seat.', 'Press up.'], $guidance->instructions);
        $this->assertSame(['Bouncing the weight.'], $guidance->commonMistakes);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === config('services.openai.guidance_model')
                && $request['response_format'] === ['type' => 'json_object']
                && str_contains($request['messages'][1]['content'], 'Chest Press')
                && str_contains($request['messages'][1]['content'], 'A pressing movement.');
        });
    }

    public function test_rejects_a_response_that_is_not_json(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->rawAnswer('Here are some tips: bend your knees...'))]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('not valid JSON');

        app(OpenAIGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_rejects_json_with_an_empty_or_missing_array(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'instructions' => ['Step one.'],
            'common_mistakes' => [],
        ]))]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('common_mistakes');

        app(OpenAIGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_rejects_json_with_non_string_entries(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->apiAnswer([
            'instructions' => ['Step one.', 42],
            'common_mistakes' => ['Fine.'],
        ]))]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('instructions');

        app(OpenAIGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_rejects_an_api_error(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('HTTP 503');

        app(OpenAIGuidanceGenerator::class)->generate(Exercise::factory()->create());
    }

    public function test_refuses_to_run_without_an_api_key(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();

        $this->expectException(GuidanceGenerationFailed::class);
        $this->expectExceptionMessage('No OpenAI API key');

        app(OpenAIGuidanceGenerator::class)->generate(Exercise::factory()->create());

        Http::assertNothingSent();
    }

    /**
     * An OpenAI chat completion whose message content is the given payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function apiAnswer(array $payload): array
    {
        return $this->rawAnswer(json_encode($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function rawAnswer(string $content): array
    {
        return [
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ];
    }
}
