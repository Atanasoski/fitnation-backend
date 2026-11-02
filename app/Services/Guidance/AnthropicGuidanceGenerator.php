<?php

namespace App\Services\Guidance;

use App\Models\Exercise;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The real GuidanceGenerator: one Anthropic Messages API call per exercise,
 * mirroring the existing HTTP-client pattern (OpenAINutritionParser). The
 * response must be the strict JSON shape below — anything else is rejected
 * as GuidanceGenerationFailed, never half-parsed into the catalogue.
 */
class AnthropicGuidanceGenerator implements GuidanceGenerator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a certified strength and conditioning coach writing guidance for a public exercise page that gym-goers read on their phone, mid-workout, standing at the machine.

Return ONLY a valid JSON object with this exact structure and nothing else (no markdown, no code fences, no commentary):

{
  "instructions": ["Step one...", "Step two..."],
  "common_mistakes": ["Mistake one...", "Mistake two..."]
}

Rules:
1. "instructions" is 3 to 8 ordered steps: setup first, then execution, then the finish. One concrete action per step, imperative voice, no numbering inside the strings.
2. "common_mistakes" is 2 to 5 short items, each naming the mistake and why it hurts form or safety.
3. Plain language a beginner can follow; keep each string under 160 characters.
4. Base everything on the exercise data given; do not invent equipment the exercise doesn't use.
PROMPT;

    public function generate(Exercise $exercise): ExerciseGuidance
    {
        $response = $this->call($this->describe($exercise));

        return $this->parse($response);
    }

    private function call(string $exerciseDescription): string
    {
        $key = config('services.anthropic.key');

        if (empty($key)) {
            throw new GuidanceGenerationFailed('No Anthropic API key configured (services.anthropic.key / ANTHROPIC_API_KEY).');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post(config('services.anthropic.base_url').'/messages', [
                'model' => config('services.anthropic.guidance_model'),
                'max_tokens' => 2048,
                'system' => self::SYSTEM_PROMPT,
                'messages' => [
                    ['role' => 'user', 'content' => $exerciseDescription],
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new GuidanceGenerationFailed("Anthropic API unreachable: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new GuidanceGenerationFailed("Anthropic API error (HTTP {$response->status()}): ".$response->body());
        }

        // The model may emit thinking blocks before the text block.
        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new GuidanceGenerationFailed('Anthropic response contained no text block.');
        }

        return $text;
    }

    /**
     * What the model gets to work from: the exercise's catalogue data.
     */
    private function describe(Exercise $exercise): string
    {
        $lines = [
            "Exercise: {$exercise->name}",
            'Description: '.($exercise->description ?: '(none)'),
            'Primary muscles: '.($exercise->primaryMuscleGroups->pluck('name')->implode(', ') ?: '(unknown)'),
            'Secondary muscles: '.($exercise->secondaryMuscleGroups->pluck('name')->implode(', ') ?: '(none)'),
            'Equipment: '.($exercise->equipmentType?->name ?: '(none)'),
            'Difficulty: '.($exercise->difficulty?->value ?: '(unknown)'),
        ];

        return implode("\n", $lines);
    }

    private function parse(string $text): ExerciseGuidance
    {
        // Tolerate a fenced answer, nothing else.
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));

        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new GuidanceGenerationFailed('Anthropic response was not valid JSON: '.str($text)->limit(200));
        }

        return new ExerciseGuidance(
            $this->stringList($data, 'instructions'),
            $this->stringList($data, 'common_mistakes'),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            throw new GuidanceGenerationFailed("Anthropic response is missing a non-empty \"{$key}\" array.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new GuidanceGenerationFailed("Anthropic response has a non-string or empty entry in \"{$key}\".");
            }
        }

        return array_values(array_map('trim', $value));
    }
}
