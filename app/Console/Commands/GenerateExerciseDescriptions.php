<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Models\Partner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateExerciseDescriptions extends Command
{
    protected $signature = 'exercise:descriptions:generate
                            {--partner= : Partner slug to scope exercises (only exercises linked to that partner will be processed)}
                            {--force : Overwrite existing descriptions}';

    protected $description = 'Generate AI-powered how-to descriptions for exercises using OpenAI';

    public function handle(): int
    {
        $partnerSlug = $this->option('partner');
        $force = $this->option('force');

        $partner = null;

        if ($partnerSlug) {
            $partner = Partner::where('slug', $partnerSlug)->first();

            if (! $partner) {
                $this->error("Partner not found: {$partnerSlug}");

                return Command::FAILURE;
            }

            $this->info("Scoping to partner: {$partner->name} ({$partner->slug})");
        }

        $exercises = $this->loadExercises($partner, $force);

        if ($exercises->isEmpty()) {
            $this->warn('No exercises found to process.');

            return Command::SUCCESS;
        }

        $this->info('Found '.$exercises->count().' exercise(s) to process.');
        $this->newLine();

        $processed = 0;
        $updated = 0;
        $failed = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($exercises->count());
        $bar->start();

        foreach ($exercises as $exercise) {
            try {
                $processed++;

                if (! $force && ! empty($exercise->description)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $description = $this->generateDescription($exercise);

                if ($description) {
                    $exercise->update(['description' => $description]);
                    $updated++;

                    Log::info('[GenerateExerciseDescriptions] Updated exercise description', [
                        'exercise_id' => $exercise->id,
                        'exercise_name' => $exercise->name,
                    ]);
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("  No description returned for: {$exercise->name}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("  Error processing {$exercise->name}: {$e->getMessage()}");

                Log::error('[GenerateExerciseDescriptions] Error generating description', [
                    'exercise_id' => $exercise->id,
                    'exercise_name' => $exercise->name,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Summary:');
        $this->line("  Processed: {$processed}");
        $this->line("  Updated:   {$updated}");
        $this->line("  Skipped:   {$skipped}");
        $this->line("  Failed:    {$failed}");

        return Command::SUCCESS;
    }

    /**
     * Load exercises, optionally scoped to a partner, with all relevant relationships.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Exercise>
     */
    private function loadExercises(?Partner $partner, bool $force): \Illuminate\Database\Eloquent\Collection
    {
        $with = ['category', 'equipmentType', 'angle', 'movementPattern', 'primaryMuscleGroups', 'secondaryMuscleGroups'];

        $query = $partner
            ? $partner->exercises()->with($with)
            : Exercise::with($with);

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('workout_exercises.description')
                    ->orWhere('workout_exercises.description', '');
            });
        }

        return $query->get();
    }

    /**
     * Call OpenAI to generate a how-to description for the given exercise.
     */
    private function generateDescription(Exercise $exercise): ?string
    {
        $context = $this->buildExerciseContext($exercise);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('nutrition.openai.api_key'),
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('nutrition.openai.model', 'gpt-4o'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $context,
                ],
            ],
            'temperature' => 0.4,
        ]);

        if (! $response->successful()) {
            Log::error('[GenerateExerciseDescriptions] OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('OpenAI API request failed: '.$response->status());
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }

    /**
     * Build a context string describing the exercise for the AI prompt.
     */
    private function buildExerciseContext(Exercise $exercise): string
    {
        $parts = ["Exercise: {$exercise->name}"];

        if ($exercise->category) {
            $parts[] = "Category: {$exercise->category->name}";
        }

        if ($exercise->equipmentType) {
            $parts[] = "Equipment: {$exercise->equipmentType->name}";
        }

        if ($exercise->angle) {
            $parts[] = "Angle/Position: {$exercise->angle->name}";
        }

        if ($exercise->movementPattern) {
            $parts[] = "Movement Pattern: {$exercise->movementPattern->name}";
        }

        if ($exercise->difficulty) {
            $parts[] = "Difficulty: {$exercise->difficulty->value}";
        }

        $primaryMuscles = $exercise->primaryMuscleGroups->pluck('name')->join(', ');
        if ($primaryMuscles) {
            $parts[] = "Primary Muscles: {$primaryMuscles}";
        }

        $secondaryMuscles = $exercise->secondaryMuscleGroups->pluck('name')->join(', ');
        if ($secondaryMuscles) {
            $parts[] = "Secondary Muscles: {$secondaryMuscles}";
        }

        return implode("\n", $parts);
    }

    /**
     * Get the system prompt for the OpenAI request.
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert personal trainer and strength coach. Your job is to write a concise, practical how-to description for a given exercise.

The description should:
- Tell the user exactly how to set up and perform the exercise (setup, body position, angles, grip, range of motion, tempo)
- Highlight the most important cues for proper form and safety
- Be direct and actionable — written as if coaching someone in person
- Be 2–4 sentences maximum
- Sound natural and motivating, not robotic

Examples of the tone and style:
- "Position the bench at a 30-degree incline. Keep your back flat against the pad and your feet firmly on the floor. Press the dumbbells up at a 45-degree angle to your torso, controlling the weight on the way down — slow and deliberate reps beat heavy and sloppy every time."
- "Grip the bar just outside shoulder-width and keep your elbows tucked at about 45 degrees as you lower the bar to your chest. Drive through your heels and squeeze your glutes to keep your body tight throughout the press."

Return ONLY the description text. No labels, no markdown, no extra commentary.
PROMPT;
    }
}
