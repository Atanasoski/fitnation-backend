<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exercise;
use App\Services\Guidance\ExerciseGuidance;
use App\Services\Guidance\GuidanceGenerationFailed;
use App\Services\Guidance\GuidanceGenerator;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI guidance backfill at the artisan seam (spec qr-stations, ticket 06),
 * with the generator faked through its container binding: it only ever writes
 * null columns, dry-run persists nothing, and a failed exercise is reported
 * and skipped while the run continues.
 */
class BackfillExerciseGuidanceCommandTest extends TestCase
{
    use RefreshDatabase;

    private const STEPS = ['Set the seat height.', 'Press to lockout.'];

    private const MISTAKES = ['Flaring the elbows.'];

    public function test_fills_both_columns_where_they_are_null(): void
    {
        $exercise = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => null]);
        $this->fakeGenerator();

        $this->artisan('exercises:backfill-guidance')
            ->expectsOutputToContain("[{$exercise->id}] {$exercise->name}: wrote instructions and common_mistakes")
            ->expectsOutputToContain('Done: 1 filled, 0 failed and skipped.')
            ->assertSuccessful();

        $exercise->refresh();
        $this->assertSame(self::STEPS, $exercise->instructions);
        $this->assertSame(self::MISTAKES, $exercise->common_mistakes);
    }

    public function test_never_overwrites_an_existing_column_and_skips_fully_filled_exercises(): void
    {
        $handWritten = ['Hand-written mistake.'];
        $partial = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => $handWritten]);
        $full = Exercise::factory()->create(['instructions' => ['Done.'], 'common_mistakes' => ['Done.']]);
        $generated = $this->fakeGenerator();

        $this->artisan('exercises:backfill-guidance')
            ->expectsOutputToContain("[{$partial->id}] {$partial->name}: wrote instructions")
            ->assertSuccessful();

        $partial->refresh();
        $this->assertSame(self::STEPS, $partial->instructions);
        $this->assertSame($handWritten, $partial->common_mistakes, 'existing content must never be overwritten');

        $full->refresh();
        $this->assertSame(['Done.'], $full->instructions);
        $this->assertSame(['Done.'], $full->common_mistakes);
        $this->assertSame([$partial->id], $generated->all(), 'a fully filled exercise must not even reach the generator');
    }

    public function test_dry_run_prints_what_would_be_written_and_persists_nothing(): void
    {
        $exercise = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => null]);
        $this->fakeGenerator();

        $this->artisan('exercises:backfill-guidance --dry-run')
            ->expectsOutputToContain("[{$exercise->id}] {$exercise->name}: would write instructions and common_mistakes")
            ->expectsOutputToContain('Set the seat height.')
            ->expectsOutputToContain('Flaring the elbows.')
            ->expectsOutputToContain('Done: 1 would be filled, 0 failed and skipped.')
            ->assertSuccessful();

        $exercise->refresh();
        $this->assertNull($exercise->instructions);
        $this->assertNull($exercise->common_mistakes);
    }

    public function test_a_failed_exercise_is_reported_and_skipped_while_the_run_continues(): void
    {
        $failing = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => null]);
        $fine = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => null]);

        $this->bindGenerator(function (Exercise $exercise) use ($failing) {
            if ($exercise->id === $failing->id) {
                throw new GuidanceGenerationFailed('Anthropic response was not valid JSON.');
            }

            return new ExerciseGuidance(self::STEPS, self::MISTAKES);
        });

        $this->artisan('exercises:backfill-guidance')
            ->expectsOutputToContain("[{$failing->id}] {$failing->name}: Anthropic response was not valid JSON. — skipped.")
            ->expectsOutputToContain('Done: 1 filled, 1 failed and skipped.')
            ->assertSuccessful();

        $this->assertNull($failing->refresh()->instructions);
        $this->assertSame(self::STEPS, $fine->refresh()->instructions);
    }

    public function test_targets_only_the_given_exercise_ids(): void
    {
        $wanted = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => null]);
        $other = Exercise::factory()->create(['instructions' => null, 'common_mistakes' => null]);
        $this->fakeGenerator();

        $this->artisan("exercises:backfill-guidance --exercise={$wanted->id}")
            ->expectsOutputToContain('Done: 1 filled, 0 failed and skipped.')
            ->assertSuccessful();

        $this->assertSame(self::STEPS, $wanted->refresh()->instructions);
        $this->assertNull($other->refresh()->instructions);
    }

    public function test_the_limit_caps_the_batch(): void
    {
        Exercise::factory()->count(3)->create(['instructions' => null, 'common_mistakes' => null]);
        $generated = $this->fakeGenerator();

        $this->artisan('exercises:backfill-guidance --limit=2')
            ->expectsOutputToContain('Done: 2 filled, 0 failed and skipped.')
            ->assertSuccessful();

        $this->assertCount(2, $generated);
        $this->assertSame(1, Exercise::whereNull('instructions')->count());
    }

    public function test_says_so_when_there_is_nothing_to_backfill(): void
    {
        Exercise::factory()->create(['instructions' => ['Done.'], 'common_mistakes' => ['Done.']]);
        $generated = $this->fakeGenerator();

        $this->artisan('exercises:backfill-guidance')
            ->expectsOutputToContain('Nothing to backfill')
            ->assertSuccessful();

        $this->assertCount(0, $generated);
    }

    /**
     * Binds a generator that answers every exercise with the canned guidance
     * and records which exercise ids it was asked about.
     *
     * @return \Illuminate\Support\Collection<int, int> the generated exercise ids
     */
    private function fakeGenerator()
    {
        $generated = collect();

        $this->bindGenerator(function (Exercise $exercise) use ($generated) {
            $generated->push($exercise->id);

            return new ExerciseGuidance(self::STEPS, self::MISTAKES);
        });

        return $generated;
    }

    private function bindGenerator(Closure $generate): void
    {
        $this->app->instance(GuidanceGenerator::class, new class($generate) implements GuidanceGenerator
        {
            public function __construct(private Closure $generate) {}

            public function generate(Exercise $exercise): ExerciseGuidance
            {
                return ($this->generate)($exercise);
            }
        });
    }
}
