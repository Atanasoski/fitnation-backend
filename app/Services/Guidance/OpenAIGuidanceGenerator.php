<?php

namespace App\Services\Guidance;

use App\Models\Exercise;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The real GuidanceGenerator: one OpenAI chat completion per exercise,
 * mirroring the codebase's existing OpenAI pattern (OpenAINutritionParser)
 * and reusing the same OPENAI_API_KEY. The response must be the strict JSON
 * shape below — anything else is rejected as GuidanceGenerationFailed, never
 * half-parsed into the catalogue.
 */
class OpenAIGuidanceGenerator implements GuidanceGenerator
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
        $key = config('services.openai.key');

        if (empty($key)) {
            throw new GuidanceGenerationFailed('No OpenAI API key configured (services.openai.key / OPENAI_API_KEY).');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(config('services.openai.base_url').'/chat/completions', [
                'model' => config('services.openai.guidance_model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $exerciseDescription],
                ],
                'temperature' => 0.2,
            ]);
        } catch (ConnectionException $e) {
            throw new GuidanceGenerationFailed("OpenAI API unreachable: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new GuidanceGenerationFailed("OpenAI API error (HTTP {$response->status()}): ".$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new GuidanceGenerationFailed('OpenAI response contained no message content.');
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
            throw new GuidanceGenerationFailed('OpenAI response was not valid JSON: '.str($text)->limit(200));
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
            throw new GuidanceGenerationFailed("OpenAI response is missing a non-empty \"{$key}\" array.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw new GuidanceGenerationFailed("OpenAI response has a non-string or empty entry in \"{$key}\".");
            }
        }

        return array_values(array_map('trim', $value));
    }
}
